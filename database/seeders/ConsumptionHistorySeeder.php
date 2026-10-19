<?php

namespace Database\Seeders;

use App\Models\Drought\ConsumptionReading;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConsumptionHistorySeeder extends Seeder
{
    public function run(): void
    {
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->command->warn('Aucune zone trouvée. Créez d\'abord des zones.');
            return;
        }

        // 24 months: Jan 2025 - Dec 2026
        $startDate = Carbon::parse('2025-01-01');
        $endDate = Carbon::parse('2026-12-01');

        $zones->each(function (Zone $zone) use ($startDate, $endDate) {
            $baseConsumption = match ($zone->id) {
                1 => 45000,  // Zone 1: higher population
                2 => 32000,  // Zone 2: medium
                3 => 28000,  // Zone 3: medium
                4 => 38000,  // Zone 4: high
                5 => 25000,  // Zone 5: lower population
                default => 30000,
            };

            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // Seasonal multiplier
                $month = $currentDate->month;
                $seasonalMultiplier = match (true) {
                    $month >= 6 && $month <= 8 => 1.20,  // Summer: +20%
                    $month >= 3 && $month <= 5 => 1.0,   // Spring: baseline
                    $month >= 9 && $month <= 11 => 1.0,  // Fall: baseline
                    default => 0.90,                      // Winter: -10%
                };

                // Random noise: ±5%
                $randomNoise = 0.95 + (mt_rand(0, 100) / 1000);

                $consumption = $baseConsumption * $seasonalMultiplier * $randomNoise;

                ConsumptionReading::firstOrCreate(
                    [
                        'zone_id' => $zone->id,
                        'periode' => $currentDate->toDateString(),
                    ],
                    [
                        'consommation_m3' => round($consumption, 2),
                        'nombre_abonnes' => (int) ($zone->population / 3.5), // ~3.5 people per subscriber
                    ]
                );

                $currentDate->addMonth();
            }
        });

        $this->command->info('ConsumptionHistorySeeder exécuté : ' . (24 * $zones->count()) . ' relevés synthétiques créés.');
    }
}
