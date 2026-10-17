<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infrastructure\StoreInfrastructureRequest;
use App\Http\Requests\Infrastructure\UpdateInfrastructureRequest;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Services\InfrastructureRiskScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $zoneId = $request->integer('zone_id');

        $infrastructures = Infrastructure::query()
            ->with('zone')
            ->when($search !== '', fn ($query) => $query->where('nom', 'like', "%{$search}%"))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('back.infrastructures.index', [
            'infrastructures' => $infrastructures,
            'zones' => Zone::orderBy('nom')->get(),
            'search' => $search,
            'currentZone' => $zoneId ?: null,
        ]);
    }

    public function create(): View
    {
        return view('back.infrastructures.create', [
            'infrastructure' => new Infrastructure(['statut' => 'actif']),
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Infrastructure::TYPES,
            'statuts' => Infrastructure::STATUTS,
        ]);
    }

    public function store(StoreInfrastructureRequest $request): RedirectResponse
    {
        $infrastructure = Infrastructure::create($request->validated());

        return redirect()
            ->route('back.infrastructures.show', $infrastructure)
            ->with('success', "L'infrastructure « {$infrastructure->nom} » a été créée.");
    }

    public function show(Infrastructure $infrastructure): View
    {
        $infrastructure->load(['zone', 'maintenances' => fn ($query) => $query->latest('date_intervention')]);

        return view('back.infrastructures.show', compact('infrastructure'));
    }

    public function edit(Infrastructure $infrastructure): View
    {
        return view('back.infrastructures.edit', [
            'infrastructure' => $infrastructure,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Infrastructure::TYPES,
            'statuts' => Infrastructure::STATUTS,
        ]);
    }

    public function update(UpdateInfrastructureRequest $request, Infrastructure $infrastructure): RedirectResponse
    {
        $infrastructure->update($request->validated());

        return redirect()
            ->route('back.infrastructures.show', $infrastructure)
            ->with('success', "L'infrastructure « {$infrastructure->nom} » a été mise à jour.");
    }

    public function destroy(Infrastructure $infrastructure): RedirectResponse
    {
        $nom = $infrastructure->nom;
        $infrastructure->delete();

        return redirect()
            ->route('back.infrastructures.index')
            ->with('success', "L'infrastructure « {$nom} » a été supprimée.");
    }

    public function refreshRiskScore(Infrastructure $infrastructure, InfrastructureRiskScorer $scorer): RedirectResponse
    {
        $result = $scorer->evaluate($infrastructure);
        $scorer->refresh($infrastructure);

        return back()->with(
            'success',
            "Score de risque recalculé : {$result['score']} / 100 (niveau {$result['niveau']}).",
        );
    }
}
