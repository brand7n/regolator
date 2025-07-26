<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Headers;

class RegoReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;
    public $name;
    public $kennel;
    public $shirt_size;
    public $short_bus;
    public $nerd_name;
    public $phone;
    public $email;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $url)
    {
        $this->user = $user;
        $this->url = $url;
        $this->name = $user->name;
        $this->kennel = $user->kennel;
        $this->shirt_size = $user->shirt_size;
        $this->short_bus = $user->short_bus;
        $this->nerd_name = $user->nerd_name;
        $this->phone = $user->phone;
        $this->email = $user->email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NVHHH 1900th Weekend Event Guide for ' . $this->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rego_reminder',
        );
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

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}