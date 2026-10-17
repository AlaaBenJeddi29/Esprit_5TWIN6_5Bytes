<?php

namespace App\Services;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\WaterLevel;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use Illuminate\Support\Carbon;

class DroughtAnalyzer
{
    /**
     * Prévision de consommation par régression linéaire simple sur les
     * derniers relevés mensuels. Heuristique locale, sans service externe.
     *
     * @return array{history: list<array{label: string, valeur: float}>, forecast: list<array{label: string, valeur: float}>, tendance: string, message: string}
     */
    public function forecast(Zone $zone, int $months = 3): array
    {
        $readings = ConsumptionReading::query()
            ->where('zone_id', $zone->id)
            ->orderBy('periode')
            ->get()
            ->take(-12)
            ->values();

        $history = $readings->map(fn (ConsumptionReading $reading) => [
            'label' => Carbon::parse($reading->periode)->translatedFormat('M Y'),
            'valeur' => (float) $reading->consommation_m3,
        ])->all();

        if ($readings->count() < 2) {
            return [
                'history' => $history,
                'forecast' => [],
                'tendance' => 'indisponible',
                'message' => 'Au moins deux relevés mensuels sont nécessaires pour produire une prévision.',
            ];
        }

        $values = $readings->pluck('consommation_m3')->map(fn ($value) => (float) $value)->values();
        [$slope, $intercept] = $this->linearRegression($values->all());

        $lastDate = Carbon::parse($readings->last()->periode);
        $forecast = [];

        for ($i = 1; $i <= $months; $i++) {
            $x = $values->count() - 1 + $i;
            $prediction = max(0, $intercept + ($slope * $x));

            $forecast[] = [
                'label' => $lastDate->copy()->addMonthsNoOverflow($i)->translatedFormat('M Y'),
                'valeur' => round($prediction, 2),
            ];
        }

        $first = $values->first();
        $last = $values->last();
        $variation = $first > 0 ? (($last - $first) / $first) * 100 : 0;

        $tendance = match (true) {
            $variation > 5 => 'hausse',
            $variation < -5 => 'baisse',
            default => 'stable',
        };

        $message = match ($tendance) {
            'hausse' => sprintf('La consommation a augmenté de %.1f %% sur la période analysée ; la prévision anticipe une poursuite de la hausse.', abs($variation)),
            'baisse' => sprintf('La consommation a diminué de %.1f %% sur la période analysée ; la prévision anticipe une poursuite de la baisse.', abs($variation)),
            default => 'La consommation est globalement stable sur la période analysée.',
        };

        return [
            'history' => $history,
            'forecast' => $forecast,
            'tendance' => $tendance,
            'message' => $message,
        ];
    }

    /**
     * Indice de risque sécheresse (0–100) et facteurs contributifs.
     *
     * @return array{score: int, niveau: string, facteurs: list<string>}
     */
    public function riskScore(Zone $zone): array
    {
        $score = 0;
        $facteurs = [];

        $latestLevel = WaterLevel::query()
            ->where('zone_id', $zone->id)
            ->latest('date_mesure')
            ->first();

        if ($latestLevel) {
            if ((float) $latestLevel->niveau <= (float) $latestLevel->seuil_critique) {
                $score += 40;
                $facteurs[] = "Niveau {$latestLevel->nom_source} sous le seuil critique.";
            } elseif ((float) $latestLevel->niveau <= (float) $latestLevel->seuil_alerte) {
                $score += 25;
                $facteurs[] = "Niveau {$latestLevel->nom_source} sous le seuil d'alerte.";
            }
        } else {
            $score += 10;
            $facteurs[] = 'Aucune mesure de niveau disponible pour cette zone.';
        }

        $forecast = $this->forecast($zone);
        $variation = $this->variationFromForecast($forecast);

        if ($variation > 10) {
            $score += 20;
            $facteurs[] = 'Consommation en hausse de plus de 10 %.';
        } elseif ($variation > 3) {
            $score += 10;
            $facteurs[] = 'Consommation légèrement orientée à la hausse.';
        }

        $activeRestrictions = Restriction::query()
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $zone->id))
            ->where('statut', 'active')
            ->count();

        if ($activeRestrictions > 0) {
            $score += min(25, 10 + ($activeRestrictions * 5));
            $facteurs[] = "{$activeRestrictions} restriction(s) active(s).";
        }

        $qualityAlerts = WaterQualityAlert::query()
            ->where('zone_id', $zone->id)
            ->where('statut', 'active')
            ->count();

        if ($qualityAlerts > 0) {
            $score += 10;
            $facteurs[] = "{$qualityAlerts} alerte(s) qualité active(s).";
        }

        $incidents = Incident::query()
            ->where('zone_id', $zone->id)
            ->open()
            ->whereIn('type', ['fuite', 'coupure'])
            ->count();

        if ($incidents > 0) {
            $score += min(10, $incidents * 3);
            $facteurs[] = "{$incidents} incident(s) réseau ouvert(s).";
        }

        $score = max(0, min(100, $score));

        $niveau = match (true) {
            $score >= 75 => 'critique',
            $score >= 50 => 'eleve',
            $score >= 25 => 'modere',
            default => 'faible',
        };

        if ($facteurs === []) {
            $facteurs[] = 'Aucun facteur de risque notable détecté.';
        }

        return [
            'score' => $score,
            'niveau' => $niveau,
            'facteurs' => $facteurs,
        ];
    }

    /**
     * @param  array{history: list<array{label: string, valeur: float}>, forecast: list<array{label: string, valeur: float}>, tendance: string}  $forecast
     */
    private function variationFromForecast(array $forecast): float
    {
        $history = $forecast['history'] ?? [];

        if (count($history) < 2) {
            return 0;
        }

        $first = $history[0]['valeur'];
        $last = $history[count($history) - 1]['valeur'];

        return $first > 0 ? (($last - $first) / $first) * 100 : 0;
    }

    /**
     * @param  list<float>  $values
     * @return array{0: float, 1: float}
     */
    private function linearRegression(array $values): array
    {
        $n = count($values);
        $sumX = $sumY = $sumXY = $sumXX = 0.0;

        foreach ($values as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);

        if ($denominator === 0.0) {
            return [0.0, $sumY / max(1, $n)];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return [$slope, $intercept];
    }
}
