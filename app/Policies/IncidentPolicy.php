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

    /**
     * Évaluation initiale : réservée au citoyen propriétaire d'un incident
     * traité. L'unicité par couple (incident, citoyen) est vérifiée sous
     * verrou par le contrôleur afin de renvoyer un message explicite.
     */
    public function feedback(User $user, Incident $incident): bool
    {
        return $incident->belongsToCitizen($user)
            && $incident->status === 'traite';
    }

    public function manage(User $user): bool
    {
        return $user->canAccessBackOffice();
    }

    /**
     * Détection et décision sur les incidents similaires : réservées aux
     * gestionnaires et administrateurs.
     */
    public function manageSimilarities(User $user, Incident $incident): bool
    {
        return $user->canAccessBackOffice();
    }
}
