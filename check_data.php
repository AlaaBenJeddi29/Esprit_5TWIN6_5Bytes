<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$consumption = \App\Models\Drought\ConsumptionReading::query()
    ->select('zone_id', \DB::raw('COUNT(*) as count'), \DB::raw('MIN(periode) as earliest'), \DB::raw('MAX(periode) as latest'))
    ->groupBy('zone_id')
    ->get();

echo "=== CONSUMPTION HISTORY ===\n";
echo str_pad('Zone', 10) . str_pad('Count', 12) . str_pad('From', 15) . str_pad('To', 15) . "\n";
echo str_repeat('-', 55) . "\n";
foreach ($consumption as $row) {
    echo str_pad($row->zone_id, 10) . str_pad($row->count, 12) . str_pad($row->earliest, 15) . str_pad($row->latest, 15) . "\n";
}

echo "\n=== WATER LEVELS HISTORY ===\n";
$waterLevels = \App\Models\Drought\WaterLevel::query()
    ->select(\DB::raw('COUNT(*) as count'), \DB::raw('MIN(date_mesure) as earliest'), \DB::raw('MAX(date_mesure) as latest'))
    ->first();
echo "Total readings: " . $waterLevels->count . "\n";
echo "From: " . $waterLevels->earliest . "\n";
echo "To: " . $waterLevels->latest . "\n";

echo "\n=== SUMMARY ===\n";
$totalConsumption = \App\Models\Drought\ConsumptionReading::count();
$totalZones = \App\Models\Drought\ConsumptionReading::distinct('zone_id')->count();
$avgPerZone = $totalConsumption / $totalZones;
echo "Total consumption readings: $totalConsumption\n";
echo "Total zones: $totalZones\n";
echo "Avg readings per zone: " . round($avgPerZone, 1) . "\n";
