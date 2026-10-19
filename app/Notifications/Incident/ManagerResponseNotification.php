<?php

namespace App\Notifications\Incident;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ManagerResponseNotification extends Notification
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
            'type' => 'manager_response',
            'title' => 'Réponse à votre réclamation',
            'message' => sprintf(
                'Un gestionnaire a répondu à votre réclamation concernant le signalement %s.',
                $this->feedback->incident->reference,
            ),
            'feedback_id' => $this->feedback->id,
            'incident_id' => $this->feedback->incident_id,
            'url' => route('citizen.incidents.show', $this->feedback->incident_id),
        ];
    }
}
