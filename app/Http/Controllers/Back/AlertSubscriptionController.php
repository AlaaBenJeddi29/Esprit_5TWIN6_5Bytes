<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\AlertSubscription;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');

        $subscriptions = AlertSubscription::query()
            ->with(['user', 'zone'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('back.drought.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'zones' => Zone::orderBy('nom')->get(),
            'currentZone' => $zoneId ?: null,
        ]);
    }
}
