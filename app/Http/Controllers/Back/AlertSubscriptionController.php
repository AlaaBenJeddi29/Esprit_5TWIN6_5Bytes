<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Models\AlertSubscription;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertSubscriptionController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $type = $request->string('type_alerte')->toString();
        $actif = $request->string('actif')->toString();
        $zoneId = $request->integer('zone_id');
        $perPage = $this->listingPerPage($request);

        $subscriptions = AlertSubscription::query()
            ->with(['user', 'zone'])
            ->when($search !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists($type, AlertSubscription::TYPES),
                fn ($query) => $query->where('type_alerte', $type),
            )
            ->when(in_array($actif, ['1', '0'], true), fn ($query) => $query->where('actif', $actif === '1'))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('back.drought.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => AlertSubscription::TYPES,
            'search' => $search,
            'currentType' => $type,
            'currentActif' => $actif,
            'currentZone' => $zoneId ?: null,
            'perPage' => $perPage,
        ]);
    }
}
