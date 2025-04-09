<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;
use Illuminate\Support\Carbon;

class Paypal extends Component
{
    public $key;
    public $price = 160;
    public $event = 'NVHHH1900';
    public $rego_paid_at;
    public $terms_accepted = false;
    public $name;

    function __construct() {
        //parent::__construct();
        $this->key = env('PAYPAL_CLIENT_ID', '');
        $this->rego_paid_at = Auth::user()->rego_paid_at;
        if ($this->rego_paid_at) {
            $this->rego_paid_at->setTimezone('US/Eastern');
        }
        $this->terms_accepted = session('terms_accepted', false);
        $this->name = Auth::user()->name;
    }

    public function render()
    {
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
            return redirect()->to('/user/profile');
        }

        if ($response->getStatusCode() === 200) {
            Auth::user()->rego_paid_at = Carbon::now();
            Auth::user()->save();

            activity()->causedBy(Auth::user())->withProperties([
                'event' => $this->event,
                'transaction' => $details['id'],
                'data' => $response->getBody()->getContents()
            ])->log('transaction verified');

            return redirect()->to('/user/profile');
        } else {
            Log::error("failed to verify order", [
                'user' => Auth::user(),
                'order' => $details['id'],
                'code' => $response->getStatusCode(),
                'response' => $response->getReasonPhrase(),
            ]);
        }
        return redirect()->to('/dashboard');
    }

    public function cancel()
    {
        Log::warning("transaction cancelled", ['user' => Auth::user()]);
    }

    public function error($err)
    {
        Log::error("transaction error", ['user' => Auth::user(), 'error' => $err]);
    }

    public function accept_terms()
    {
        session(['terms_accepted' => true]);
        activity()->causedBy(Auth::user())->log('terms accepted');
        return redirect()->to('/dashboard');
    }

    public function edit()
    {
        return redirect()->to('/user/profile');
    }
}
