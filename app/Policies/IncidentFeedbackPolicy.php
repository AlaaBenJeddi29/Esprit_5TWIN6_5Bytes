<?php

namespace App\Policies;

use App\Models\Incident\IncidentFeedback;
use App\Models\User;

/**
 * Autorisations par enregistrement pour les évaluations et réclamations.
 */
class IncidentFeedbackPolicy
{
    public function view(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice() || $feedback->user_id === $user->id;
    }

    public function manage(User $user): bool
    {
        return $user->canAccessBackOffice();
    }

    public function review(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice()
            && $feedback->complaint_status === 'en_attente';
    }

    public function respond(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice()
            && $feedback->hasComplaint()
            && $feedback->complaint_status !== 'cloturee';
    }

    public function decide(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice()
            && $feedback->complaint_status === 'en_examen';
    }

    public function close(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice()
            && in_array($feedback->complaint_status, ['acceptee', 'rejetee'], true);
    }

    public function reopen(User $user, IncidentFeedback $feedback): bool
    {
        return $user->canAccessBackOffice()
            && $feedback->complaint_status === 'acceptee'
            && $feedback->reopen_requested
            && $feedback->reopened_at === null;
    }
}
