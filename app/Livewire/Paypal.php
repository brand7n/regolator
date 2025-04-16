<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentConfirmation;

class Paypal extends Component
{
    public $key;
    public $price;
    public $event;
    public $rego_paid_at;
    public $terms_accepted = false;
    public $bonus_accepted = false;
    public $name;

    function __construct()
    {
        //parent::__construct();
        $this->key = env('PAYPAL_CLIENT_ID', '');
        $this->rego_paid_at = Auth::user()->rego_paid_at;
        $this->name = Auth::user()->name;
    }

    protected function reinit()
    {
        $this->price = 165;
        $this->event = 'NVHHH1900';
        if ($this->bonus_accepted) {
            $this->price += 115;
            $this->event .= '_PLUS_EH3_32NDANAL';
        }
    }

    public function render()
    {
        $this->reinit();
        return view('livewire.paypal');
    }

    public function approve($details)
    {
        Log::info('transaction approved', ['user' => Auth::user(), 'details' => $details]);

        try {
            $client = new Client();

            $response = $client->post('https://api-m.paypal.com/v1/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'auth' => [
                    env('PAYPAL_CLIENT_ID', ''),
                    env('PAYPAL_CLIENT_SECRET', '')
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $bearer_token = json_decode($response->getBody(), true)['access_token'];

            $response = $client->get('https://api.paypal.com/v2/checkout/orders/' . $details['id'], [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $bearer_token 
                ]
            ]);
        } catch (\Throwable $t) {
            Log::error("failed to verify order", [
                'user' => Auth::user(),
                'order' => $details['id'],
                'error' => $t,
            ]);
        }

        if ($response->getStatusCode() === 200) {
            activity()->causedBy(Auth::user())->withProperties([
                'event' => $this->event,
                'transaction' => $details['id'],
                'data' => $response->getBody()->getContents()
            ])->log('transaction verified');

            $this->handle_payment_success();
        } else {
            Log::error("failed to verify order", [
                'user' => Auth::user(),
                'order' => $details['id'],
                'code' => $response->getStatusCode(),
                'response' => $response->getReasonPhrase(),
            ]);
        }
    }

    public function cancel()
    {
        Log::warning("transaction cancelled", ['user' => Auth::user()]);
        $this->dispatch('render-paypal');
    }

    public function error($err)
    {
        Log::error("transaction error", ['user' => Auth::user(), 'error' => $err]);
        $this->dispatch('render-paypal');
    }

    public function accept_terms()
    {
        $this->terms_accepted = true;
        activity()->causedBy(Auth::user())->log('terms accepted');
        $this->dispatch('render-paypal');
    }

    public function toggle_bonus()
    {
        $this->bonus_accepted = !$this->bonus_accepted;
        $this->dispatch('render-paypal');
    }

    // option to decline rego
    public function decline()
    {
        activity()->causedBy(Auth::user())->log('rego declined');
        return redirect()->to('https://hashrego.com');
    }

    public function edit()
    {
        return redirect()->to('/user/profile');
    }

    protected function handle_payment_success()
    {
        $user = Auth::user();

        // save payment time
        $this->rego_paid_at = Carbon::now();
        $user->rego_paid_at = $this->rego_paid_at;
        $user->save();

        // send confirmation email
        $user_data = json_encode([
            'id' => $user->id,
            'hash' => $user->password,
        ]);
        $quick_login = Crypt::encryptString($user_data);

        try {
            Mail::to($user)->send(new PaymentConfirmation($user, url('/quicklogin/' . $quick_login)));
        } catch (\Throwable $t) {
            Log::error("failed to send payment confirmation email", [
                'user' => $user,
                'error' => $t,
            ]);
        }
    }
}
