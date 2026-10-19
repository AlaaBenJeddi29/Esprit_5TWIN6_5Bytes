<?php

namespace App\Notifications\Incident;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly IncidentFeedback $feedback) {}

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
        $accepted = $this->feedback->complaint_decision === 'acceptee';

        return [
            'type' => 'complaint_decision',
            'title' => $accepted ? 'Réclamation acceptée' : 'Réclamation refusée',
            'message' => sprintf(
                'Une décision a été rendue concernant votre réclamation relative au signalement %s : %s.',
                $this->feedback->incident->reference,
                $accepted ? 'acceptée' : 'refusée',
            ),
            'decision' => $this->feedback->complaint_decision,
            'decision_reason' => $this->feedback->decision_reason,
            'feedback_id' => $this->feedback->id,
            'incident_id' => $this->feedback->incident_id,
            'url' => route('citizen.incidents.show', $this->feedback->incident_id),
        ];
    }
}
