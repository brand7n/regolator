<?php

namespace App\Jobs;

use App\Mail\EventMessage;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\MessageRecipientStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Message $message,
        public MessageRecipient $messageRecipient,
    ) {}

    public function handle(): void
    {
        $user = $this->messageRecipient->user;
        $order = $this->messageRecipient->order;
        $event = $order->event;

        try {
            $quickLogin = $user->getQuickLogin($event->ends_at);
            $eventUrl = route('events.show', $event);
            $url = url('/quicklogin/'.$quickLogin.'?action='.$eventUrl);

            $mailable = new EventMessage($this->message, $user, $order, $url);
            Mail::to($user)->send($mailable);

            $this->messageRecipient->status = MessageRecipientStatus::Sent;
            $this->messageRecipient->sent_at = now();
            $this->messageRecipient->save();
        } catch (\Throwable $e) {
            Log::error('failed to send event message', [
                'message_id' => $this->message->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->messageRecipient->status = MessageRecipientStatus::Failed;
            $this->messageRecipient->error = $e->getMessage();
            $this->messageRecipient->save();
        }

        $this->message->updateDeliveryCounts();
    }
}
