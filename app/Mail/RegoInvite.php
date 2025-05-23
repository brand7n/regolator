<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Headers;

class RegoInvite extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;
    public $name;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $url)
    {
        $this->user = $user;
        $this->url = $url;
        $this->name = $user->name;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nittany Rego Invite for ' . $this->name,
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
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function headers(): Headers
    {
        $unsubscribeUrl = $this->url . '?action=unsubscribe';
        return new Headers(
            text: [
                'List-Unsubscribe' => "<{$this->url}?action=unsubscribe>",
            ],
        );
    }
}