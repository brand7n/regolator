<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use GuzzleHttp\Client;
use App\Mail\PaymentConfirmation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function user() : BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function verify() : bool
    {
        try {
            $client = new Client();

            $response = $client->post('https://api-m.paypal.com/v1/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'auth' => [
                    config('services.paypal.client_id'),
                    config('services.paypal.client_secret')
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ]
            ]);

            $bearer_token = json_decode($response->getBody(), true)['access_token'];

            $response = $client->get('https://api.paypal.com/v2/checkout/orders/' . $this->order_id, [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $bearer_token 
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                activity()->causedBy(Auth::user())->withProperties([
                    //'event' => $event,
                    'transaction' => $this->order_id,
                    'data' => $response->getBody()->getContents()
                ])->log('transaction verified');

                $this->handle_payment_success();
                return true;
            } else {
                Log::error("failed to verify order", [
                    'user' => Auth::user(),
                    'order' => $this->order_id,
                    'code' => $response->getStatusCode(),
                    'response' => $response->getReasonPhrase(),
                ]);
            }
        } catch (\Throwable $t) {
            Log::error("failed to verify order", [
                'user' => Auth::user(),
                'order' => $this->order_id,
                'error' => $t->getMessage(),
            ]);
        }
        return false;
    }

    protected function handle_payment_success()
    {
        $now = Carbon::now();

        $this->verified_at = $now;
        $this->save(); 

        /** @var User $user */
        $user = $this->user;
        $user->rego_paid_at = $now;
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
                'error' => $t->getMessage(),
            ]);
        } 
    }
}
