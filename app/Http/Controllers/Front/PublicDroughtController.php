<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Zone;
use App\Services\DroughtAnalyzer;
use Illuminate\View\View;

class PublicDroughtController extends Controller
{
    public function __construct(private readonly DroughtAnalyzer $analyzer)
    {
    }

    public function index(): View
    {
        $zones = Zone::query()->orderBy('nom')->get();

        $zoneRisks = $zones->map(fn (Zone $zone) => [
            'zone' => $zone,
            'risk' => $this->analyzer->riskScore($zone),
            'forecast' => $this->analyzer->forecast($zone),
        ]);

        $restrictions = Restriction::query()
            ->with('zone')
            ->where('statut', 'active')
            ->latest('date_debut')
            ->get();

        $cuts = ScheduledCut::query()
            ->with(['zone', 'infrastructure'])
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->orderBy('date_debut')
            ->limit(15)
            ->get();

        return view('front.drought.index', compact('zoneRisks', 'restrictions', 'cuts'));
    }

    public function zone(Zone $zone): View
    {
        $risk = $this->analyzer->riskScore($zone);
        $forecast = $this->analyzer->forecast($zone);

        $levels = WaterLevel::query()
            ->where('zone_id', $zone->id)
            ->latest('date_mesure')
            ->limit(24)
            ->get();

        $restrictions = Restriction::query()
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $zone->id))
            ->whereIn('statut', ['active', 'planifiee'])
            ->latest('date_debut')
            ->get();

        $cuts = ScheduledCut::query()
            ->where('zone_id', $zone->id)
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->orderBy('date_debut')
            ->get();

        return view('front.drought.zone', compact('zone', 'risk', 'forecast', 'levels', 'restrictions', 'cuts'));
    }
}
