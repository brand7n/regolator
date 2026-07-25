<?php

namespace App\Models;

use App\Mail\PaymentConfirmation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $order_id
 * @property int $user_id
 * @property Carbon|null $verified_at
 * @property int|null $event_id
 * @property OrderStatus|null $status
 * @property string|null $comment
 * @property array<array-key, mixed>|null $event_info
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Event|null $event
 * @property-read User|null $user
 *
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
 *
 * @mixin \Eloquent
 *
 * quiet phpstan error
 *
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
        'verified_at',
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
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName): string {
                $status = optional($this->status)->value ?? 'unknown';
                $userName = optional($this->user)->name ?? 'unknown';
                $event = optional($this->event)->name ?? 'unknown';

                return "order {$eventName}: {$userName} → {$event} ({$status})";
            });
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function verify(): bool
    {
        $sandbox = config('services.paypal.sandbox');
        try {
            $tokenResponse = Http::asForm()
                ->withBasicAuth(
                    config('services.paypal.client_id'),
                    config('services.paypal.client_secret'),
                )
                ->post("https://api-m.{$sandbox}paypal.com/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            $bearer_token = $tokenResponse->json('access_token');

            $orderResponse = Http::withToken($bearer_token)
                ->accept('application/json')
                ->get("https://api.{$sandbox}paypal.com/v2/checkout/orders/{$this->order_id}");

            if (! $orderResponse->successful()) {
                Log::error('failed to verify order', [
                    'order' => $this,
                    'code' => $orderResponse->status(),
                    'response' => $orderResponse->body(),
                ]);

                return false;
            }

            $data = $orderResponse->json();
            $status = $data['status'] ?? null;

            // Already captured (e.g. by verify-pending-orders cron)
            if ($status === 'COMPLETED') {
                $paidCents = (int) round((float) data_get($data, 'purchase_units.0.amount.value', 0) * 100);

                return $this->finalizeIfAmountOk($paidCents, $data);
            }

            // Not yet captured — server-side capture after limit check
            if ($status === 'APPROVED') {
                if ($this->event && $this->event->isFull()) {
                    activity()
                        ->performedOn($this)
                        ->causedBy($this->user)
                        ->withProperties(['max_regos' => $this->event->max_regos])
                        ->log('registration limit reached before capture');

                    return false;
                }

                $captureResponse = Http::withToken($bearer_token)
                    ->accept('application/json')
                    ->post("https://api.{$sandbox}paypal.com/v2/checkout/orders/{$this->order_id}/capture", [
                        'intent' => 'CAPTURE',
                    ]);

                if (! $captureResponse->successful()) {
                    Log::error('failed to capture paypal order', [
                        'order' => $this,
                        'code' => $captureResponse->status(),
                        'response' => $captureResponse->body(),
                    ]);

                    return false;
                }

                $captureData = $captureResponse->json();
                $captureStatus = $captureData['status'] ?? null;

                if ($captureStatus !== 'COMPLETED') {
                    activity()
                        ->performedOn($this)
                        ->causedBy($this->user)
                        ->withProperties(['data' => $captureData])
                        ->log('capture returned non-completed status');

                    return false;
                }

                $paidCents = (int) round((float) data_get($captureData, 'purchase_units.0.payments.captures.0.amount.value', 0) * 100);

                return $this->finalizeIfAmountOk($paidCents, $captureData);
            }

            activity()
                ->performedOn($this)
                ->causedBy($this->user)
                ->withProperties(['data' => $data])
                ->log('transaction retrieved');
        } catch (\Throwable $t) {
            Log::error('failed to verify order', [
                'order' => $this,
                'error' => $t->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function finalizeIfAmountOk(int $paidCents, array $data): bool
    {
        $expectedCents = $this->event->base_price;

        if ($paidCents < $expectedCents) {
            Log::error('payment amount mismatch', [
                'order_id' => $this->order_id,
                'paid_cents' => $paidCents,
                'expected_cents' => $expectedCents,
            ]);
            activity()
                ->performedOn($this)
                ->causedBy($this->user)
                ->withProperties(['data' => $data, 'expected_cents' => $expectedCents])
                ->log('payment amount mismatch');

            return false;
        }

        activity()
            ->performedOn($this)
            ->causedBy($this->user)
            ->withProperties(['data' => $data])
            ->log('transaction verified');

        $this->handle_payment_success();

        return true;
    }

    protected function handle_payment_success(): void
    {
        $now = Carbon::now();

        $this->verified_at = $now;
        $this->status = OrderStatus::PaymentVerified;
        $this->save();

        /** @var User $user */
        $user = $this->user;
        $event = $this->event;

        $quick_login = $user->getQuickLogin($event->ends_at);
        $eventUrl = route('events.show', $event);

        try {
            Mail::to($user)->send(new PaymentConfirmation($user, $event, url('/quicklogin/'.$quick_login.'?action='.$eventUrl)));
        } catch (\Throwable $t) {
            Log::error('failed to send payment confirmation email', [
                'user' => $user,
                'error' => $t->getMessage(),
            ]);
        }
    }
}
