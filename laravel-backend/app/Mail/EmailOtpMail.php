<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public CarbonInterface $expiresAt
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'LNU Marketplace Email OTP'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.otp'
        );
    }
}
