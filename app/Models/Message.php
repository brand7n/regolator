<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Message extends Model
{
    use LogsActivity;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'status' => MessageStatus::class,
        'recipient_filter' => 'array',
        'include_profile_fields' => 'array',
        'include_event_fields' => 'array',
        'sent_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MessageRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    /** @return Collection<int, Order> */
    public function resolveRecipients(): Collection
    {
        return Order::where('event_id', $this->event_id)
            ->whereIn('status', $this->recipient_filter)
            ->with('user')
            ->get();
    }

    public function updateDeliveryCounts(): void
    {
        $this->sent_count = $this->recipients()->where('status', MessageRecipientStatus::Sent)->count();
        $this->failed_count = $this->recipients()->where('status', MessageRecipientStatus::Failed)->count();

        $total = $this->recipients()->count();
        $processed = $this->sent_count + $this->failed_count;

        if ($processed >= $total) {
            $this->status = $this->failed_count > 0 ? MessageStatus::Failed : MessageStatus::Sent;
            $this->sent_at = now();
        }

        $this->save();
    }
}
