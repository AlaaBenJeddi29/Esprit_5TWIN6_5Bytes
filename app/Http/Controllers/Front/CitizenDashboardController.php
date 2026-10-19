<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\AlertSubscription;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Incident\Incident;
use App\Models\Project\Funding;
use App\Models\Quality\WaterQualityAlert;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CitizenDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $incidents = Incident::query()
            ->where('user_id', $user->id)
            ->with('zone')
            ->latest('reported_at')
            ->limit(10)
            ->get();

        $openIncidents = Incident::query()
            ->where('user_id', $user->id)
            ->open()
            ->count();

        $subscriptions = AlertSubscription::query()
            ->where('user_id', $user->id)
            ->with('zone')
            ->get();

        $zoneIds = $subscriptions->pluck('zone_id')->all();

        $qualityAlerts = WaterQualityAlert::query()
            ->with('zone')
            ->whereIn('zone_id', $zoneIds)
            ->where('statut', 'active')
            ->latest()
            ->limit(10)
            ->get();

        $restrictions = Restriction::query()
            ->with('zone')
            ->where(function ($query) use ($zoneIds) {
                $query->whereNull('zone_id')->orWhereIn('zone_id', $zoneIds);
            })
            ->where('statut', 'active')
            ->latest('date_debut')
            ->limit(10)
            ->get();

        $cuts = ScheduledCut::query()
            ->with('zone')
            ->whereIn('zone_id', $zoneIds)
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->orderBy('date_debut')
            ->limit(10)
            ->get();

        $donations = Funding::query()
            ->where('donateur_id', $user->id)
            ->where('source', 'don')
            ->with('project')
            ->latest('date_versement')
            ->limit(10)
            ->get();

        // Seuls les dons confirmés comptent dans le total personnel.
        $donationsTotal = (float) Funding::query()
            ->where('donateur_id', $user->id)
            ->where('source', 'don')
            ->confirmees()
            ->sum('montant');

        $notifications = $user->notifications()->latest()->limit(5)->get();
        $unreadNotifications = $user->unreadNotifications()->count();

        return view('front.citizen.dashboard', compact(
            'incidents',
            'openIncidents',
            'subscriptions',
            'qualityAlerts',
            'restrictions',
            'cuts',
            'donations',
            'donationsTotal',
            'notifications',
            'unreadNotifications',
        ));
    }
}
