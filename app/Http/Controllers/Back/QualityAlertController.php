<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\UpdateQualityAlertRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityAlertController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $statut = $request->string('statut')->toString();
        $niveau = $request->string('niveau')->toString();
        $zoneId = $request->integer('zone_id');
        $search = $request->string('search')->toString();
        $dates = $this->listingDateRange($request);
        $perPage = $this->listingPerPage($request);

        $alerts = WaterQualityAlert::query()
            ->with(['zone', 'sample'])
            ->when($search !== '', fn ($query) => $query->where('message', 'like', "%{$search}%"))
            ->when(array_key_exists($statut, WaterQualityAlert::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when(array_key_exists($niveau, WaterQualityAlert::NIVEAUX), fn ($query) => $query->where('niveau', $niveau))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when($dates['from'], fn ($query) => $query->whereDate('created_at', '>=', $dates['from']))
            ->when($dates['to'], fn ($query) => $query->whereDate('created_at', '<=', $dates['to']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('back.quality.alerts.index', [
            'alerts' => $alerts,
            'statuts' => WaterQualityAlert::STATUTS,
            'niveaux' => WaterQualityAlert::NIVEAUX,
            'zones' => Zone::orderBy('nom')->get(),
            'search' => $search,
            'currentStatut' => $statut,
            'currentNiveau' => $niveau,
            'currentZone' => $zoneId ?: null,
            'from' => $dates['from'],
            'to' => $dates['to'],
            'perPage' => $perPage,
        ]);
    }

    public function update(UpdateQualityAlertRequest $request, WaterQualityAlert $alert): RedirectResponse
    {
        $statut = $request->validated('statut');

        $alert->update([
            'statut' => $statut,
            'resolue_at' => $statut === 'resolue' ? now() : null,
        ]);

        return back()->with('success', 'Le statut de l\'alerte a été mis à jour.');
    }

    public function destroy(WaterQualityAlert $alert): RedirectResponse
    {
        $alert->delete();

        return back()->with('success', 'L\'alerte a été supprimée.');
    }
}
