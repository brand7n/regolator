<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use GuzzleHttp\Client;
use Auth;

class Paypal extends Component
{
    public $key;
    public $price = 160;
    public $description = "Rego for whomever";

    function __construct() {
        //parent::__construct();
        $this->key = env('PAYPAL_CLIENT_ID', '');  
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
            Log::error("failed to verify order", ['user' => Auth::user(), 'order' => $details['id'], 'error' => $t]);
            return;
        }

        if ($response->getStatusCode() === 200) {
            // TODO: mark paid
        }
    }

    public function cancel()
    {
        Log::warning("transaction cancelled", ['user' => Auth::user()]);
    }

    public function error($err)
    {
        Log::error("transaction error", ['user' => Auth::user(), 'error' => $err]);
    }
}
