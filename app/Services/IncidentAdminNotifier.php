<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\NewIncidentAdminMail;
use App\Models\Incident\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the active administrators when a citizen submits a new incident.
 *
 * Delivery failures are isolated from the incident workflow: the report is
 * already persisted and the citizen must still receive the normal success
 * response.
 */
class IncidentAdminNotifier
{
    public function notifyNewIncident(Incident $incident): void
    {
        $administrators = User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->get(['id', 'email'])
            ->filter(fn (User $administrator): bool => filter_var($administrator->email, FILTER_VALIDATE_EMAIL) !== false);

        if ($administrators->isEmpty()) {
            Log::warning('Nouveau signalement : aucun administrateur actif et vérifié à notifier.', [
                'incident_id' => $incident->id,
                'reference' => $incident->reference,
            ]);

            return;
        }

        foreach ($administrators as $administrator) {
            if (! $this->claimNotification($incident, $administrator)) {
                continue;
            }

            try {
                Mail::to($administrator->email)->send(new NewIncidentAdminMail($incident));
            } catch (Throwable $exception) {
                $this->releaseNotification($incident, $administrator);

                Log::error('Nouveau signalement : échec de l\'envoi de la notification administrateur.', [
                    'incident_id' => $incident->id,
                    'reference' => $incident->reference,
                    'administrator_id' => $administrator->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function claimNotification(Incident $incident, User $administrator): bool
    {
        try {
            return Cache::add(
                $this->notificationKey($incident, $administrator),
                true,
                now()->addDays(30),
            );
        } catch (Throwable $exception) {
            Log::warning('Nouveau signalement : verrou anti-doublon indisponible.', [
                'incident_id' => $incident->id,
                'administrator_id' => $administrator->id,
                'error' => $exception->getMessage(),
            ]);

            return true;
        }
    }

    private function releaseNotification(Incident $incident, User $administrator): void
    {
        try {
            Cache::forget($this->notificationKey($incident, $administrator));
        } catch (Throwable $exception) {
            Log::warning('Nouveau signalement : impossible de libérer le verrou de notification.', [
                'incident_id' => $incident->id,
                'administrator_id' => $administrator->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notificationKey(Incident $incident, User $administrator): string
    {
        return sprintf('incident-admin-notified:%d:%d', $incident->id, $administrator->id);
    }
}
