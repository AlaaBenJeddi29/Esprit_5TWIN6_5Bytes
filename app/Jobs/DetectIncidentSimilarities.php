<?php

namespace App\Jobs;

use App\Models\Incident\Incident;
use App\Services\IncidentSimilarityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Détection asynchrone des incidents similaires après la création d'un
 * incident. Toute erreur est journalisée et absorbée afin de ne jamais
 * bloquer le signalement citoyen (le driver « sync » exécute ce job dans la
 * requête mais conserve la même protection).
 */
class DetectIncidentSimilarities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $incidentId) {}

    public function handle(IncidentSimilarityService $service): void
    {
        try {
            if (! Schema::hasTable('incident_similarities')) {
                Log::warning('Incident similarity detection skipped: migration not applied.', [
                    'incident_id' => $this->incidentId,
                ]);

                return;
            }

            $incident = Incident::query()->find($this->incidentId);

            if ($incident === null || $incident->status === 'rejete') {
                return;
            }

            $service->detect($incident);
        } catch (Throwable $exception) {
            Log::error('Incident similarity detection failed.', [
                'incident_id' => $this->incidentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
