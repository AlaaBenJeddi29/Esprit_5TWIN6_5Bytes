<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Incident\Incident;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualitySample;
use App\Models\Report;
use App\Models\User;
use App\Services\IncidentStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly IncidentStatsService $incidentStats,
    ) {}

    /**
     * Tableau de bord : indicateurs calculés uniquement sur des données réelles.
     */
    public function __invoke(): View
    {
        $totalReports = Report::count();
        $pendingReports = Report::where('status', 'nouveau')->count();
        $totalUsers = User::count();

        $statusCounts = Report::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $latestReports = Report::query()
            ->with('user')
            ->latest()
            ->take(5)
            ->get();

        return view('back.dashboard', [
            'totalReports' => $totalReports,
            'pendingReports' => $pendingReports,
            'totalUsers' => $totalUsers,
            'statusCounts' => $statusCounts,
            'statuses' => Report::STATUSES,
            'latestReports' => $latestReports,
            'totalIncidents' => Incident::count(),
            'openIncidents' => Incident::open()->count(),
            'criticalOpenIncidents' => $this->incidentStats->criticalOpen(),
            'unassignedOpenIncidents' => $this->incidentStats->unassignedOpen(),
            'samplesThisMonth' => WaterQualitySample::where('date_prelevement', '>=', now()->startOfMonth())->count(),
            'activeQualityAlerts' => WaterQualityAlert::where('statut', 'active')->count(),
            'activeRestrictions' => Restriction::where('statut', 'active')->count(),
            'upcomingCuts' => ScheduledCut::whereIn('statut', ['planifiee', 'en_cours'])->count(),
            'latestIncidents' => Incident::query()
                ->with(['zone', 'user'])
                ->latest('reported_at')
                ->take(5)
                ->get(),
        ]);
    }
}
