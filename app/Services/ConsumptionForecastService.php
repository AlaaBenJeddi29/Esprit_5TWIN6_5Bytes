<?php

namespace App\Services;

use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use Illuminate\Support\Carbon;

class ConsumptionForecastService
{
    /**
     * Confidence interval width as a percentage. Used for upper and lower bound calculations.
     * Domain assumption: ±15% represents the acceptable margin for forecast reliability.
     * Adjust based on water authority policy or historical forecast accuracy.
     * @var float
     */
    private const CONFIDENCE_INTERVAL_PERCENTAGE = 0.15;

    /**
     * Holt-Winters exponential smoothing algorithm with seasonal components.
     * Returns predictions with confidence intervals and MAE.
     *
     * @param  int  $months  Number of months to forecast (default 6)
     * @return array{predictions: array<int, float>, lower_bounds: array<int, float>, upper_bounds: array<int, float>, mae: float, model: string}
     */
    public function predictNextMonths(Zone $zone, int $months = 6): array
    {
        $readings = ConsumptionReading::query()
            ->where('zone_id', $zone->id)
            ->orderBy('periode')
            ->get()
            ->pluck('consommation_m3')
            ->map(fn ($value) => (float) $value)
            ->values()
            ->all();

        if (count($readings) < 12) {
            \Illuminate\Support\Facades\Log::warning(
                "Insufficient historical data for zone {$zone->id} ({$zone->nom}): ".count($readings).' months available, 12+ required for Holt-Winters.',
                ['zone_id' => $zone->id, 'data_points' => count($readings)]
            );

            return [
                'predictions' => [],
                'lower_bounds' => [],
                'upper_bounds' => [],
                'mae' => 0,
                'model' => 'holt_winters',
            ];
        }

        // Use last 24 months if available, otherwise all available
        $trainingData = array_slice($readings, -24);

        // Holt-Winters parameters
        $alpha = 0.3;   // Level smoothing
        $beta = 0.1;    // Trend smoothing
        $gamma = 0.1;   // Seasonal smoothing
        $seasonalPeriod = 12;

        // Initialize
        $L = array_sum(array_slice($trainingData, 0, $seasonalPeriod)) / $seasonalPeriod;
        $T = (array_sum(array_slice($trainingData, $seasonalPeriod, $seasonalPeriod)) -
              array_sum(array_slice($trainingData, 0, $seasonalPeriod))) / ($seasonalPeriod ** 2);

        $S = [];
        for ($i = 0; $i < $seasonalPeriod; $i++) {
            $S[$i] = $trainingData[$i] / $L;
        }

        // Update levels, trends, and seasonality
        $levels = [$L];
        $trends = [$T];
        $seasonals = $S;

        foreach ($trainingData as $idx => $value) {
            if ($idx == 0) {
                continue;
            }

            $seasonalIdx = ($idx - 1) % $seasonalPeriod;
            $lastL = $levels[count($levels) - 1];
            $lastT = $trends[count($trends) - 1];

            // Ensure we don't divide by zero
            $divisor = $seasonals[$seasonalIdx] ?? 1;
            if ($divisor == 0) {
                $divisor = 1;
            }

            $L_new = $alpha * ($value / $divisor) + (1 - $alpha) * ($lastL + $lastT);
            $T_new = $beta * ($L_new - $lastL) + (1 - $beta) * $lastT;
            $S_new = $gamma * ($value / $L_new) + (1 - $gamma) * ($seasonals[$seasonalIdx] ?? 1);

            $levels[] = $L_new;
            $trends[] = $T_new;
            $seasonals[$seasonalIdx] = $S_new;
        }

        // Forecast
        $L_final = $levels[count($levels) - 1];
        $T_final = $trends[count($trends) - 1];

        $predictions = [];
        $lowerBounds = [];
        $upperBounds = [];

        for ($m = 1; $m <= $months; $m++) {
            $seasonalIdx = (count($trainingData) + $m - 1) % $seasonalPeriod;
            $seasonalFactor = $seasonals[$seasonalIdx] ?? 1;

            $pred = ($L_final + $m * $T_final) * $seasonalFactor;
            $pred = max(0, $pred);

            // Confidence intervals: ±15% of predicted value
            $lower = $pred * (1 - self::CONFIDENCE_INTERVAL_PERCENTAGE);
            $upper = $pred * (1 + self::CONFIDENCE_INTERVAL_PERCENTAGE);

            $predictions[] = round($pred, 2);
            $lowerBounds[] = round($lower, 2);
            $upperBounds[] = round($upper, 2);
        }

        // Calculate MAE from hold-out validation (last 3 months of training data)
        $mae = $this->calculateMAE($trainingData, $levels, $trends, $seasonals, $seasonalPeriod);

        return [
            'predictions' => $predictions,
            'lower_bounds' => $lowerBounds,
            'upper_bounds' => $upperBounds,
            'mae' => round($mae, 2),
            'model' => 'holt_winters',
        ];
    }

    /**
     * Calculate Mean Absolute Error using last 3 months of training data.
     */
    private function calculateMAE(array $trainingData, array $levels, array $trends, array $seasonals, int $seasonalPeriod): float
    {
        if (count($trainingData) < 4) {
            return 0;
        }

        // Use last 3 points for validation
        $validationStart = count($trainingData) - 3;
        $errors = 0;

        for ($i = $validationStart; $i < count($trainingData); $i++) {
            $seasonalIdx = $i % $seasonalPeriod;
            $seasonalFactor = $seasonals[$seasonalIdx] ?? 1;
            $L = $levels[$i] ?? 0;
            $T = $trends[$i] ?? 0;

            if ($L > 0) {
                $predicted = $L * $seasonalFactor;
                $actual = $trainingData[$i];
                $errors += abs($predicted - $actual);
            }
        }

        return $errors / 3;
    }
}