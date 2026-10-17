<?php

namespace App\Services;

use App\Models\Infrastructure\Infrastructure;

/**
 * Calcul local du score de risque d'une infrastructure.
 */
class InfrastructureRiskScorer
{
    /**
     * @return array{score: float, niveau: string, facteurs: list<string>}
     */
    public function evaluate(Infrastructure $infrastructure): array
    {
        $score = 0.0;
        $facteurs = [];

        if ($infrastructure->date_installation) {
            $annees = $infrastructure->date_installation->diffInYears(now());
            $ageScore = min(35, $annees * 1.5);

            if ($ageScore > 0) {
                $score += $ageScore;
                $facteurs[] = sprintf('Ancienneté de %d an(s).', $annees);
            }
        } else {
            $score += 8;
            $facteurs[] = 'Date d\'installation inconnue.';
        }

        $maintenances = $infrastructure->maintenances()->count();
        $penalty = min(20, $maintenances * 4);
        $score = max(0, $score - $penalty);

        if ($maintenances > 0) {
            $facteurs[] = "{$maintenances} maintenance(s) enregistrée(s).";
        }

        $lastMaintenance = $infrastructure->maintenances()->latest('date_intervention')->first();

        if (! $lastMaintenance) {
            $score += 10;
            $facteurs[] = 'Aucune maintenance enregistrée.';
        } elseif ($lastMaintenance->date_intervention && $lastMaintenance->date_intervention->lt(now()->subYear())) {
            $score += 10;
            $facteurs[] = 'Dernière maintenance il y a plus d\'un an.';
        }

        $incidents = $infrastructure->incidents()
            ->whereIn('status', ['nouveau', 'en_cours', 'en_attente'])
            ->count();

        if ($incidents > 0) {
            $score += min(25, $incidents * 7);
            $facteurs[] = "{$incidents} incident(s) ouvert(s) sur cet ouvrage.";
        }

        $statusAdd = match ($infrastructure->statut) {
            'hors_service' => 25,
            'maintenance' => 12,
            'en_construction' => 5,
            default => 0,
        };

        if ($statusAdd > 0) {
            $score += $statusAdd;
            $facteurs[] = 'Statut d\'exploitation : '.$infrastructure->statutLabel().'.';
        }

        $score = round(max(0, min(100, $score)), 2);

        $niveau = match (true) {
            $score >= 70 => 'critique',
            $score >= 45 => 'eleve',
            $score >= 20 => 'modere',
            default => 'faible',
        };

        return [
            'score' => $score,
            'niveau' => $niveau,
            'facteurs' => $facteurs,
        ];
    }

    public function refresh(Infrastructure $infrastructure): float
    {
        $score = $this->evaluate($infrastructure)['score'];
        $infrastructure->update(['score_risque' => $score]);

        return $score;
    }
}
