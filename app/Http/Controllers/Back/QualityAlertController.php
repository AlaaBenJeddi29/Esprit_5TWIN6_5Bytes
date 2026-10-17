<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\UpdateQualityAlertRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Quality\WaterQualityAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityAlertController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->string('statut')->toString();
        $niveau = $request->string('niveau')->toString();
        $zoneId = $request->integer('zone_id');

        $alerts = WaterQualityAlert::query()
            ->with(['zone', 'sample'])
            ->when(array_key_exists($statut, WaterQualityAlert::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when(array_key_exists($niveau, WaterQualityAlert::NIVEAUX), fn ($query) => $query->where('niveau', $niveau))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('back.quality.alerts.index', [
            'alerts' => $alerts,
            'statuts' => WaterQualityAlert::STATUTS,
            'niveaux' => WaterQualityAlert::NIVEAUX,
            'zones' => Zone::orderBy('nom')->get(),
            'currentStatut' => $statut,
            'currentNiveau' => $niveau,
            'currentZone' => $zoneId ?: null,
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
