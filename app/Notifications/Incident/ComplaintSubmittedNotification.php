<?php

namespace App\Notifications\Incident;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintSubmittedNotification extends Notification
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
        return [
            'type' => 'complaint_submitted',
            'title' => 'Réclamation enregistrée',
            'message' => sprintf(
                'Votre réclamation concernant le signalement %s a bien été enregistrée. Elle sera examinée par les services techniques.',
                $this->feedback->incident->reference,
            ),
            'feedback_id' => $this->feedback->id,
            'incident_id' => $this->feedback->incident_id,
            'url' => route('citizen.incidents.show', $this->feedback->incident_id),
        ];
    }
}
