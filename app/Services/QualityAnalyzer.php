<?php

namespace App\Services;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QualityAnalyzer
{
    /**
     * Seuils de secours utilisés lorsqu'aucun seuil n'est configuré.
     *
     * @var array<string, array{min: float|null, max: float|null, unite: string}>
     */
    public const DEFAULT_THRESHOLDS = [
        'ph' => ['min' => 6.5, 'max' => 8.5, 'unite' => 'pH'],
        'turbidite' => ['min' => 0, 'max' => 5, 'unite' => 'NTU'],
        'chlore' => ['min' => 0.1, 'max' => 1.0, 'unite' => 'mg/L'],
        'nitrates' => ['min' => 0, 'max' => 50, 'unite' => 'mg/L'],
        'plomb' => ['min' => 0, 'max' => 10, 'unite' => 'µg/L'],
        'bacteries' => ['min' => 0, 'max' => 0, 'unite' => 'UFC/100 mL'],
        'conductivite' => ['min' => 0, 'max' => 2500, 'unite' => 'µS/cm'],
    ];

    /**
     * Analyse un prélèvement : conformité par paramètre, score global et alertes.
     *
     * @param  list<array{parametre: string, valeur: float|int|string, unite?: string}>  $parameters
     * @return array{statut: string, score: float, non_conformes: int, alertes: int}
     */
    public function analyze(WaterQualitySample $sample, array $parameters, ?int $authorId = null): array
    {
        return DB::transaction(function () use ($sample, $parameters) {
            $sample->parameters()->delete();
            $sample->alerts()->where('statut', 'active')->update([
                'statut' => 'resolue',
                'resolue_at' => now(),
            ]);

            $nonConformes = 0;
            $alertes = 0;

            foreach ($parameters as $row) {
                $parametre = (string) $row['parametre'];
                $valeur = (float) $row['valeur'];
                $threshold = $this->thresholdFor($sample, $parametre);
                $defaults = self::DEFAULT_THRESHOLDS[$parametre] ?? ['min' => null, 'max' => null, 'unite' => $row['unite'] ?? ''];

                $seuilMin = $threshold?->valeur_min !== null ? (float) $threshold->valeur_min : $defaults['min'];
                $seuilMax = $threshold?->valeur_max !== null ? (float) $threshold->valeur_max : $defaults['max'];
                $unite = $threshold?->unite ?: ($row['unite'] ?? $defaults['unite']);

                $conforme = ($seuilMin === null || $valeur >= $seuilMin)
                    && ($seuilMax === null || $valeur <= $seuilMax);

                WaterQualityParameter::create([
                    'sample_id' => $sample->id,
                    'parametre' => $parametre,
                    'valeur' => $valeur,
                    'unite' => $unite,
                    'seuil_min' => $seuilMin,
                    'seuil_max' => $seuilMax,
                    'est_conforme' => $conforme,
                ]);

                if (! $conforme) {
                    $nonConformes++;

                    $niveau = $this->alertLevel($parametre, $valeur, $seuilMin, $seuilMax);

                    WaterQualityAlert::create([
                        'zone_id' => $sample->zone_id,
                        'sample_id' => $sample->id,
                        'parametre' => $parametre,
                        'niveau' => $niveau,
                        'message' => $this->alertMessage($parametre, $valeur, $seuilMin, $seuilMax, $unite, $sample->zone?->nom),
                        'statut' => 'active',
                    ]);

                    $alertes++;
                }
            }

            $total = count($parameters);
            $score = $total > 0 ? round((($total - $nonConformes) / $total) * 100, 2) : 0.0;
            $statut = match (true) {
                $total === 0 => 'en_attente',
                $nonConformes > 0 => 'non_conforme',
                default => 'conforme',
            };

            $sample->update(['score' => $score, 'statut' => $statut]);

            return [
                'statut' => $statut,
                'score' => (float) $score,
                'non_conformes' => $nonConformes,
                'alertes' => $alertes,
            ];
        });
    }

    /**
     * Synthèse publique d'une zone (dernier prélèvement, tendance, alertes).
     *
     * @return array{latest: WaterQualitySample|null, score_moyen: float|null, tendance: string, alertes_actives: int, conformite_90j: float|null}
     */
    public function zoneSummary(Zone $zone): array
    {
        $latest = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->with('parameters')
            ->latest('date_prelevement')
            ->first();

        $recent = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->whereNotNull('score')
            ->latest('date_prelevement')
            ->limit(6)
            ->get();

        $scoreMoyen = $recent->isNotEmpty() ? round((float) $recent->avg('score'), 2) : null;
        $tendance = 'stable';

        if ($recent->count() >= 3) {
            $half = intdiv($recent->count(), 2);
            $recentAvg = (float) $recent->take($half)->avg('score');
            $olderAvg = (float) $recent->slice($half)->avg('score');

            if ($recentAvg > $olderAvg + 2) {
                $tendance = 'amelioration';
            } elseif ($recentAvg < $olderAvg - 2) {
                $tendance = 'degradation';
            }
        }

        $total90 = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->where('date_prelevement', '>=', Carbon::now()->subDays(90))
            ->whereIn('statut', ['conforme', 'non_conforme'])
            ->count();

        $conformes90 = WaterQualitySample::query()
            ->where('zone_id', $zone->id)
            ->where('date_prelevement', '>=', Carbon::now()->subDays(90))
            ->where('statut', 'conforme')
            ->count();

        return [
            'latest' => $latest,
            'score_moyen' => $scoreMoyen,
            'tendance' => $tendance,
            'alertes_actives' => WaterQualityAlert::where('zone_id', $zone->id)->where('statut', 'active')->count(),
            'conformite_90j' => $total90 > 0 ? round(($conformes90 / $total90) * 100, 1) : null,
        ];
    }

    public function thresholdFor(WaterQualitySample $sample, string $parametre): ?QualityThreshold
    {
        return QualityThreshold::query()
            ->where('parametre', $parametre)
            ->where('actif', true)
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $sample->zone_id))
            ->orderByRaw('zone_id IS NULL')
            ->first();
    }

    private function alertLevel(string $parametre, float $valeur, ?float $min, ?float $max): string
    {
        if (in_array($parametre, ['bacteries', 'plomb'], true)) {
            return 'critique';
        }

        $reference = $max !== null && $max > 0 ? $max : ($min ?? 1);
        $depassement = $reference > 0 ? abs($valeur - $reference) / $reference : 0;

        return $depassement >= 0.5 ? 'critique' : 'avertissement';
    }

    private function alertMessage(string $parametre, float $valeur, ?float $min, ?float $max, string $unite, ?string $zone): string
    {
        $label = QualityThreshold::PARAMETRES[$parametre] ?? $parametre;
        $bornes = match (true) {
            $min !== null && $max !== null => "plage attendue {$min} – {$max} {$unite}",
            $min !== null => "minimum attendu {$min} {$unite}",
            $max !== null => "maximum attendu {$max} {$unite}",
            default => 'aucun seuil configuré',
        };

        return sprintf(
            '%s : valeur mesurée %s %s (%s)%s.',
            $label,
            rtrim(rtrim(number_format($valeur, 4, ',', ''), '0'), ','),
            $unite,
            $bornes,
            $zone ? " — zone {$zone}" : '',
        );
    }
}
