<?php

namespace App\Notifications\Incident;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReopeningDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly IncidentFeedback $feedback,
        private readonly bool $accepted,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'reopening_decision',
            'title' => $this->accepted ? 'Demande de réouverture acceptée' : 'Demande de réouverture refusée',
            'message' => $this->accepted
                ? sprintf(
                    'Votre demande de réouverture concernant le signalement %s a été acceptée. L\'incident est de nouveau en cours de traitement.',
                    $this->feedback->incident->reference,
                )
                : sprintf(
                    'Votre demande de réouverture concernant le signalement %s a été refusée. L\'incident reste traité.',
                    $this->feedback->incident->reference,
                ),
            'accepted' => $this->accepted,
            'feedback_id' => $this->feedback->id,
            'incident_id' => $this->feedback->incident_id,
            'url' => route('citizen.incidents.show', $this->feedback->incident_id),
        ];
    }
}
