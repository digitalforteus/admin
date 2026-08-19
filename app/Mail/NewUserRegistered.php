<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserRegistered extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        /** @var string $app_name */
        $app_name = config('app.name');

        return new Envelope(
            subject: "New User $app_name: {$this->user->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-user-registered',
        );
    }
}
