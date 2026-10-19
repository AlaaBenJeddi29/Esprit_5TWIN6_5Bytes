<?php

namespace App\Console\Commands;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Services\GroqAIService;
use App\Services\IncidentSimilarityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DetectSimilarIncidents extends Command
{
    protected $signature = 'incidents:detect-similar
        {--days=30 : Filtre les incidents à analyser par date de signalement ; la fenêtre de comparaison reste INCIDENT_SIMILARITY_WINDOW_DAYS}
        {--dry-run : Simule la détection sans écrire en base}
        {--retry-failed : Rejoue uniquement la confirmation IA des suggestions encore en attente et en repli local}';

    protected $description = 'Détecte et enregistre les incidents similaires pour les incidents de la période analysée.';

    protected $help = 'L\'option --days filtre uniquement les incidents à analyser selon leur date de signalement. '
        .'La fenêtre de comparaison entre incidents reste pilotée par INCIDENT_SIMILARITY_WINDOW_DAYS. '
        .'--retry-failed ne touche qu\'aux suggestions au statut « suggeree » dont la méthode est local_ai_unavailable.';

    public function handle(IncidentSimilarityService $service): int
    {
        $started = hrtime(true);

        if ((bool) $this->option('retry-failed')) {
            return $this->retryFailed($service, $started);
        }

        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $detected = 0;
        $failures = 0;
        $aiConfirmed = 0;
        $aiNegative = 0;
        $aiFallback = 0;
        $fallbackReasons = [];

        $query = Incident::query()
            ->where('reported_at', '>=', now()->subDays($days))
            ->where('status', '!=', 'rejete');

        foreach ($query->lazyById(100) as $incident) {
            $processed++;

            try {
                if ($dryRun) {
                    $suggestions = $service->analyze($incident, false);
                    $detected += count($suggestions);
                    $this->line(sprintf(
                        '[dry-run] %s : %d suggestion(s)',
                        $incident->reference,
                        count($suggestions),
                    ));
                } else {
                    $created = $service->detect($incident);
                    $detected += $created->count();

                    $aiConfirmed += $created->where('method', IncidentSimilarity::METHOD_LOCAL_AI)->count();
                    $aiNegative += $created->where('method', IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE)->count();

                    foreach ($created->where('method', IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE) as $suggestion) {
                        $aiFallback++;
                        $reason = GroqAIService::failureReasonFromJustification($suggestion->ai_justification);
                        $fallbackReasons[$reason] = ($fallbackReasons[$reason] ?? 0) + 1;
                    }
                }
            } catch (Throwable $exception) {
                $failures++;
                Log::error('Incident similarity command failed for one incident.', [
                    'incident_id' => $incident->getKey(),
                    'error' => $exception->getMessage(),
                ]);
                $this->warn(sprintf('Échec pour %s (voir les logs).', $incident->reference));
            }
        }

        $this->info(sprintf(
            '%d incident(s) analysé(s), %d suggestion(s) %s, %d échec(s).',
            $processed,
            $detected,
            $dryRun ? 'simulée(s)' : 'enregistrée(s)',
            $failures,
        ));

        if ($dryRun) {
            $this->line(sprintf(
                'Résumé : IA non exécutée en mode dry-run ; durée totale %s s.',
                $this->durationSeconds($started),
            ));
        } else {
            $this->line($this->aiSummaryLine(
                $detected,
                $aiConfirmed,
                $aiNegative,
                $aiFallback,
                $fallbackReasons,
                $started,
            ));
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function retryFailed(IncidentSimilarityService $service, int $started): int
    {
        $summary = $service->retryFailedAi();

        if (! $summary['enabled']) {
            $this->warn('IA désactivée ou clé absente : aucune suggestion rejouée.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d suggestion(s) rejouée(s) : %d confirmée(s) (local_ai), %d verdict(s) négatif(s) (local_ai_negative), %d repli(s) local_ai_unavailable.',
            $summary['processed'],
            $summary['confirmed'],
            $summary['negative'],
            $summary['fallback'],
        ));

        if ($summary['fallback'] > 0) {
            $this->line('Motifs de repli : '.$this->formatReasons($summary['reasons']).'.');
        }

        $this->line(sprintf('Durée totale : %s s.', $this->durationSeconds($started)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $fallbackReasons
     */
    private function aiSummaryLine(
        int $detected,
        int $confirmed,
        int $negative,
        int $fallback,
        array $fallbackReasons,
        int $started,
    ): string {
        $line = sprintf(
            'Résumé : %d suggestion(s) traitée(s) ; %d confirmée(s) (local_ai) ; %d verdict(s) négatif(s) (local_ai_negative) ; %d repli(s) local_ai_unavailable',
            $detected,
            $confirmed,
            $negative,
            $fallback,
        );

        if ($fallback > 0) {
            $line .= ' ('.$this->formatReasons($fallbackReasons).')';
        }

        return $line.' ; durée totale '.$this->durationSeconds($started).' s.';
    }

    /**
     * @param  array<string, int>  $reasons
     */
    private function formatReasons(array $reasons): string
    {
        if ($reasons === []) {
            return 'motif inconnu';
        }

        $parts = [];

        foreach ($reasons as $reason => $count) {
            $parts[] = (GroqAIService::failureLabel($reason) ?? $reason).' : '.$count;
        }

        return implode(', ', $parts);
    }

    private function durationSeconds(int $started): string
    {
        return number_format((hrtime(true) - $started) / 1_000_000_000, 1, ',', ' ');
    }
}
