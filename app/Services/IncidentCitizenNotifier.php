<?php

namespace App\Services;

use App\Mail\IncidentResolvedCitizenMail;
use App\Models\Incident\Incident;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifies the citizen who reported an incident when it is treated.
 *
 * Delivery failures are isolated from the workflow: the status transition is
 * already committed and must not be rolled back because the email failed.
 */
class IncidentCitizenNotifier
{
    public function notifyResolved(Incident $incident, int $statusHistoryId): void
    {
        $citizen = $incident->user;

        if ($citizen === null || ! $citizen->isCitoyen()) {
            Log::warning('Signalement traité : le créateur n\'est pas un citoyen notifiable.', [
                'incident_id' => $incident->id,
                'reference' => $incident->reference,
                'status_history_id' => $statusHistoryId,
                'creator_id' => $citizen?->id,
                'creator_role' => $citizen?->role?->value,
            ]);

            return;
        }

        $email = $citizen->email;

        if (! filled($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('Signalement traité : adresse e-mail du citoyen absente ou invalide.', [
                'incident_id' => $incident->id,
                'reference' => $incident->reference,
                'status_history_id' => $statusHistoryId,
                'citizen_id' => $citizen->id,
            ]);

            return;
        }

        if (! $this->claimNotification($incident, $statusHistoryId)) {
            return;
        }

        try {
            Mail::to($email)->send(new IncidentResolvedCitizenMail($incident));
        } catch (Throwable $exception) {
            $this->releaseNotification($incident, $statusHistoryId);

            Log::error('Signalement traité : échec de l\'envoi de la notification citoyen.', [
                'incident_id' => $incident->id,
                'reference' => $incident->reference,
                'status_history_id' => $statusHistoryId,
                'citizen_id' => $citizen->id,
                'error' => str_replace($email, '[adresse masquée]', $exception->getMessage()),
            ]);
        }
    }

    private function claimNotification(Incident $incident, int $statusHistoryId): bool
    {
        try {
            return Cache::add(
                $this->notificationKey($incident, $statusHistoryId),
                true,
                now()->addDays(30),
            );
        } catch (Throwable $exception) {
            Log::warning('Signalement traité : verrou anti-doublon indisponible.', [
                'incident_id' => $incident->id,
                'status_history_id' => $statusHistoryId,
                'error' => $exception->getMessage(),
            ]);

            return true;
        }
    }

    private function releaseNotification(Incident $incident, int $statusHistoryId): void
    {
        try {
            Cache::forget($this->notificationKey($incident, $statusHistoryId));
        } catch (Throwable $exception) {
            Log::warning('Signalement traité : impossible de libérer le verrou de notification.', [
                'incident_id' => $incident->id,
                'status_history_id' => $statusHistoryId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notificationKey(Incident $incident, int $statusHistoryId): string
    {
        // One key per treatment event so a later reopening and closing of the
        // same incident can notify the citizen again.
        return sprintf(
            'incident-resolved-notified:%d:%d',
            $incident->id,
            $statusHistoryId,
        );
    }
}
