<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public Event $event;

    public string $url;

    public string $name;

    public ?string $kennel;

    public ?string $nerd_name;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Event $event, string $url)
    {
        $this->user = $user;
        $this->event = $event;
        $this->url = $url;
        $this->name = $user->name;
        $this->kennel = $user->kennel;
        $this->nerd_name = $user->nerd_name;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->event->name.' Payment Confirmation for '.$this->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment_confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
