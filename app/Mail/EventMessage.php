<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class EventMessage extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    /** @var array<string, string|null> */
    public array $profileFields = [];

    /** @var array<string, string|null> */
    public array $eventInfoFields = [];

    public function __construct(
        public Message $message,
        public User $user,
        public Order $order,
        public string $url,
    ) {
        $this->name = $user->name;
        $this->buildProfileFields();
        $this->buildEventInfoFields();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event_message',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->url}?action=unsubscribe>",
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }

    private function buildProfileFields(): void
    {
        $fields = $this->message->include_profile_fields ?? [];
        $labels = [
            'name' => 'Name',
            'email' => 'Email',
            'kennel' => 'Kennel',
            'nerd_name' => 'Nerd Name',
            'shirt_size' => 'Shirt Size',
            'short_bus' => 'Short Bus',
            'phone' => 'Phone',
        ];

        foreach ($fields as $field) {
            if ($field === 'order_status') {
                $this->profileFields['Registration Status'] = $this->order->status->value ?? 'N/A';
            } elseif (isset($labels[$field])) {
                $this->profileFields[$labels[$field]] = $this->user->{$field};
            }
        }
    }

    private function buildEventInfoFields(): void
    {
        $fields = $this->message->include_event_fields ?? [];
        $eventFields = data_get($this->message->event->properties, 'fields', []);
        /** @var array<int, array{name: string, label: string}> $eventFields */
        $labelMap = collect($eventFields)->pluck('label', 'name')->toArray();
        $eventInfo = $this->order->event_info ?? [];

        foreach ($fields as $field) {
            $label = $labelMap[$field] ?? $field;
            $this->eventInfoFields[$label] = $eventInfo[$field] ?? null;
        }
    }
}
