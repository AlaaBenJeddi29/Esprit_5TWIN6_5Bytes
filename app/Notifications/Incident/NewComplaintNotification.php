<?php

namespace App\Notifications\Incident;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewComplaintNotification extends Notification
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
            'type' => 'new_complaint',
            'title' => 'Nouvelle réclamation',
            'message' => sprintf(
                'Une nouvelle réclamation a été déposée pour le signalement %s.',
                $this->feedback->incident->reference,
            ),
            'feedback_id' => $this->feedback->id,
            'incident_id' => $this->feedback->incident_id,
            'url' => route('back.complaints.show', $this->feedback->id),
        ];
    }
}
