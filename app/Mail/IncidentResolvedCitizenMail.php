<?php

namespace App\Mail;

use App\Models\Incident\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification email sent to the citizen who reported an incident once it is
 * marked as treated.
 */
class IncidentResolvedCitizenMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Incident $incident,
    ) {
        $this->incident->loadMissing([
            'user',
            'zone',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Votre signalement %s a été traité — AquaSecure',
                $this->incident->reference,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.resolved-citizen',
            with: [
                'incident' => $this->incident,
                'citizen' => $this->incident->user,
                'detailUrl' => route('citizen.incidents.show', $this->incident),
                'resolvedAt' => $this->incident->resolved_at,
            ],
        );
    }
}
