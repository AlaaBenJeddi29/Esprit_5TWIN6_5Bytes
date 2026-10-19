<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi des notifications du module réclamations.
 *
 * Les erreurs d'envoi sont journalisées sans jamais remettre en cause
 * l'écriture métier déjà validée (évaluation, décision, réponse).
 */
class ComplaintNotifier
{
    public function notifyCitizen(?User $citizen, Notification $notification): void
    {
        if ($citizen === null) {
            return;
        }

        $this->send($citizen, $notification);
    }

    public function notifyManagers(Notification $notification): void
    {
        User::query()
            ->whereIn('role', ['gestionnaire', 'admin'])
            ->where('is_active', true)
            ->get()
            ->each(fn (User $manager) => $this->send($manager, $notification));
    }

    private function send(User $user, Notification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $exception) {
            Log::error('Échec de l\'envoi d\'une notification de réclamation.', [
                'user_id' => $user->id,
                'notification' => $notification::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
