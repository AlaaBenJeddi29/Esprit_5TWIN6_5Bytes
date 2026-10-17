<?php

namespace App\Policies;

use App\Models\Incident\Incident;
use App\Models\User;

/**
 * Autorisation par enregistrement pour les signalements citoyens.
 * Défense en profondeur en complément du middleware `role` et des
 * vérifications d'appartenance des contrôleurs.
 */
class IncidentPolicy
{
    public function view(User $user, Incident $incident): bool
    {
        return $user->canAccessBackOffice() || $incident->belongsToCitizen($user);
    }

    public function comment(User $user, Incident $incident): bool
    {
        return $user->canAccessBackOffice() || $incident->belongsToCitizen($user);
    }

    public function uploadPhoto(User $user, Incident $incident): bool
    {
        return $incident->belongsToCitizen($user);
    }

    public function manage(User $user): bool
    {
        return $user->canAccessBackOffice();
    }
}
