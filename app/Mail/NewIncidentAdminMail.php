<?php

namespace App\Mail;

use App\Models\Incident\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification email sent to administrators after a citizen submits an incident.
 */
class NewIncidentAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Incident $incident,
    ) {
        $this->incident->loadMissing([
            'user',
            'zone',
            'infrastructure',
            'duplicateOf',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Nouveau signalement %s — %s',
                $this->incident->reference,
                $this->incident->typeLabel(),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.new-report',
            with: [
                'incident' => $this->incident,
                'citizen' => $this->incident->user,
                'detailUrl' => route('back.incidents.show', $this->incident),
                'submittedAt' => $this->incident->reported_at ?? $this->incident->created_at,
            ],
        );
    }
}
