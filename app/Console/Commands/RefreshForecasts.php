<?php

namespace App\Console\Commands;

use App\Jobs\GenerateConsumptionForecasts;
use App\Models\Infrastructure\Zone;
use Illuminate\Console\Command;

class RefreshForecasts extends Command
{
    protected $signature = 'drought:refresh-forecasts';

    protected $description = 'Régénère les prévisions de consommation pour toutes les zones.';

    public function handle(): int
    {
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->warn('Aucune zone trouvée.');
            return 0;
        }

        $this->info("Génération des prévisions pour {$zones->count()} zone(s)...");

        $zonesWithInsufficientData = 0;
        $zonesProcessed = 0;

        foreach ($zones as $zone) {
            $readingCount = \App\Models\Drought\ConsumptionReading::where('zone_id', $zone->id)->count();
            
            if ($readingCount < 12) {
                $this->warn("Zone {$zone->nom} ({$zone->id}): données insuffisantes ({$readingCount} mois, 12+ requis).");
                $zonesWithInsufficientData++;
                continue;
            }

            GenerateConsumptionForecasts::dispatch($zone, 6);
            $zonesProcessed++;
        }

        if ($zonesProcessed > 0) {
            $this->info("✓ {$zonesProcessed} job(s) en attente de traitement.");
        }

        if ($zonesWithInsufficientData > 0) {
            $this->warn("⚠ {$zonesWithInsufficientData} zone(s) avec données insuffisantes — prévisions ignorées.");
        }

        return 0;
    }
}
