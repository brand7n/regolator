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

/**
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $order_id
 * @property int $user_id
 * @property Carbon|null $verified_at
 * @property int|null $event_id
 * @property \App\Models\OrderStatus|null $status
 * @property string|null $comment
 * @property array<array-key, mixed>|null $event_info
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Event|null $event
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereEventInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVerifiedAt($value)
 * @mixin \Eloquent
 *
 * quiet phpstan error
 * @property string|null $user_name
 */

class Order extends Model
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_id',
	    'status',
	    'event_info',
    ];

    /** @var list<string> */
    protected $hidden = [];

    /** @var list<string> */
    protected $appends = [];

    /** @var array<string, mixed> */
    protected $attributes = [];

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
	        'event_info' => 'array',
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
