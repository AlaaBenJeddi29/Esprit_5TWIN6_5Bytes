<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertNotificationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public array $alertData,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->alertData['type_alerte']) {
            'qualite' => 'Alerte - Qualité de l\'eau',
            'restriction' => 'Alerte - Restriction d\'eau',
            'coupure' => 'Alerte - Coupure programmée',
            default => 'Alerte AquaSecure',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-notification',
            with: [
                'alertData' => $this->alertData,
            ],
        );
    }
}
