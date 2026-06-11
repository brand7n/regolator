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
use Illuminate\Support\Str;

class EventMessage extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    /** @var array<string, string|null> */
    public array $profileFields = [];

    /** @var array<string, string|null> */
    public array $eventInfoFields = [];

    public string $unsubscribeUrl;

    public string $plainText;

    public function __construct(
        public Message $message,
        public User $user,
        public Order $order,
        public string $url,
    ) {
        $this->name = $user->name;
        $this->unsubscribeUrl = preg_replace('/\?action=.*/', '?action=unsubscribe', $url);
        $this->buildProfileFields();
        $this->buildEventInfoFields();
        $this->plainText = $this->renderPlainText();
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
            text: 'emails.event_message_text',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }

    public function renderPlainText(): string
    {
        $bodyHtml = Str::markdown($this->message->body);
        $bodyHtml = preg_replace('/<a\s+href="([^"]+)"[^>]*>([^<]+)<\/a>/', '$2 ($1)', $bodyHtml);
        $body = strip_tags(str_replace(['<br>', '</p>', '</li>', '</h1>', '</h2>', '</h3>'], "\n", $bodyHtml));
        $body = html_entity_decode(trim(preg_replace('/\n{3,}/', "\n\n", $body)));

        $lines = [$body, ''];
        if (! empty($this->profileFields) || ! empty($this->eventInfoFields)) {
            $lines[] = 'Your Info:';
            foreach ($this->profileFields as $label => $value) {
                $lines[] = "- {$label}: {$value}";
            }
            foreach ($this->eventInfoFields as $label => $value) {
                $lines[] = "- {$label}: {$value}";
            }
            $lines[] = '';
        }
        $lines[] = "View Event: {$this->url}";
        $lines[] = '';
        $lines[] = "Unsubscribe: {$this->unsubscribeUrl}";

        return implode("\n", $lines);
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
