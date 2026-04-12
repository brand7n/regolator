<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class RegoInvite extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public Event $event;

    public string $url;

    public string $name;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Event $event, string $url)
    {
        $this->user = $user;
        $this->event = $event;
        $this->url = $url;
        $this->name = $user->name;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->event->name.' Rego Invite for '.$this->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rego_invite',
            // with: [
            //     'name' => $this->user->name,
            //     'url' => $this->url,
            //     'unsubscribeUrl' => $unsubscribeUrl,
            //     'declineUrl' => $this->url . '?action=decline',
            // ]
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

    public function headers(): Headers
    {
        $unsubscribeUrl = $this->url.'?action=unsubscribe';

        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->url}?action=unsubscribe>",
            ],
        );
    }
}
