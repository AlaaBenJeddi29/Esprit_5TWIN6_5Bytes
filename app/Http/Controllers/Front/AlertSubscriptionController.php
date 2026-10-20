<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlertSubscriptionRequest;
use App\Models\AlertSubscription;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertSubscriptionController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $type = $request->string('type_alerte')->toString();
        $perPage = $this->listingPerPage($request);

        $subscriptions = AlertSubscription::query()
            ->where('user_id', $request->user()->id)
            ->when(array_key_exists($type, AlertSubscription::TYPES), fn ($query) => $query->where('type_alerte', $type))
            ->with('zone')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('front.citizen.subscriptions', [
            'subscriptions' => $subscriptions,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => AlertSubscription::TYPES,
            'currentType' => $type,
            'perPage' => $perPage,
        ]);
    }

    public function store(StoreAlertSubscriptionRequest $request): RedirectResponse
    {
        AlertSubscription::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'zone_id' => $request->validated('zone_id'),
            ],
            [
                'type_alerte' => $request->validated('type_alerte'),
                'actif' => true,
            ],
        );

        return back()->with('success', 'Votre abonnement aux alertes a été enregistré.');
    }

    public function destroy(Request $request, AlertSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->delete();

        return back()->with('success', 'Votre abonnement a été supprimé.');
    }
}
