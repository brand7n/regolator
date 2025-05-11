<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use GuzzleHttp\Client;
use App\Mail\PaymentConfirmation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'status' => OrderStatus::class,
        ];
    }

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function verify() : bool
    {
        $sandbox = config('services.paypal.sandbox');
        try {
            $client = new Client();

            $response = $client->post("https://api-m.{$sandbox}paypal.com/v1/oauth2/token", [
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

            $response = $client->get("https://api.{$sandbox}paypal.com/v2/checkout/orders/" . $this->order_id, [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $bearer_token
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['status']) && $data['status'] === 'COMPLETED') {
                    activity()
                        ->performedOn($this)
                        ->causedBy($this->user)
                        ->withProperties(['data' => $data])
                        ->log('transaction verified');
                    $this->handle_payment_success();
                    return true;
                } else {
                    activity()
                        ->performedOn($this)
                        ->causedBy($this->user)
                        ->withProperties(['data' => $data])
                        ->log('transaction retrieved');
                }
            } else {
                Log::error("failed to verify order", [
                    'order' => $this,
                    'code' => $response->getStatusCode(),
                    'response' => $response->getReasonPhrase(),
                ]);
            }
        } catch (\Throwable $t) {
            Log::error("failed to verify order", [
                'order' => $this,
                'error' => $t->getMessage(),
            ]);
        }
        return false;
    }

    protected function handle_payment_success()
    {
        $now = Carbon::now();

        $this->verified_at = $now;
        $this->status = OrderStatus::PaymentVerified;
        $this->save();

        /** @var User $user */
        $user = $this->user;

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
