<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualitySample;
use App\Services\QualityAnalyzer;
use Illuminate\View\View;

class PublicQualityController extends Controller
{
    public function __construct(private readonly QualityAnalyzer $analyzer)
    {
    }

    public function index(): View
    {
        $zones = Zone::query()->orderBy('nom')->get();

        $summaries = $zones->map(fn (Zone $zone) => [
            'zone' => $zone,
            'summary' => $this->analyzer->zoneSummary($zone),
        ]);

        $activeAlerts = WaterQualityAlert::query()
            ->with('zone')
            ->where('statut', 'active')
            ->latest()
            ->limit(10)
            ->get();

        $latestSamples = WaterQualitySample::query()
            ->with(['zone', 'parameters'])
            ->whereIn('statut', ['conforme', 'non_conforme'])
            ->latest('date_prelevement')
            ->limit(8)
            ->get();

        return view('front.quality.index', compact('summaries', 'activeAlerts', 'latestSamples'));
    }

    public function zone(Zone $zone): View
    {
        $summary = $this->analyzer->zoneSummary($zone);

        $samples = WaterQualitySample::query()
            ->with('parameters')
            ->where('zone_id', $zone->id)
            ->whereIn('statut', ['conforme', 'non_conforme'])
            ->latest('date_prelevement')
            ->limit(12)
            ->get();

        $alerts = WaterQualityAlert::query()
            ->where('zone_id', $zone->id)
            ->where('statut', 'active')
            ->latest()
            ->get();

        $aiSummaries = $samples->mapWithKeys(fn (WaterQualitySample $sample) => [
            $sample->id => $this->analyzer->cachedSummary($sample),
        ]);

        $trends = $this->analyzer->parameterTrends($zone);

        return view('front.quality.zone', compact('zone', 'summary', 'samples', 'alerts', 'aiSummaries', 'trends'));
    }
}
