<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Project;
use App\Models\Quality\WaterQualityAlert;
use App\Services\QualityAnalyzer;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly QualityAnalyzer $analyzer)
    {
    }

    public function index(): View
    {
        $zones = Zone::query()->orderBy('nom')->take(6)->get();

        $summaries = $zones->map(fn (Zone $zone) => [
            'zone' => $zone,
            'summary' => $this->analyzer->zoneSummary($zone),
        ]);

        $activeRestrictions = Restriction::query()
            ->with('zone')
            ->where('statut', 'active')
            ->latest('date_debut')
            ->limit(3)
            ->get();

        $upcomingCuts = ScheduledCut::query()
            ->with('zone')
            ->where('statut', 'planifiee')
            ->where('date_debut', '>=', now())
            ->orderBy('date_debut')
            ->limit(3)
            ->get();

        $projects = Project::query()
            ->with('zone')
            ->whereIn('statut', ['en_cours', 'planifié'])
            ->latest()
            ->limit(3)
            ->get();

        $recentIncidents = Incident::query()
            ->whereIn('status', ['nouveau', 'en_cours', 'en_attente'])
            ->latest('reported_at')
            ->limit(3)
            ->get();

        return view('front.home', [
            'summaries' => $summaries,
            'activeRestrictions' => $activeRestrictions,
            'upcomingCuts' => $upcomingCuts,
            'projects' => $projects,
            'recentIncidents' => $recentIncidents,
            'infrastructuresCount' => Infrastructure::count(),
            'zonesCount' => Zone::count(),
            'alertsCount' => WaterQualityAlert::where('statut', 'active')->count(),
        ]);
    }
}
