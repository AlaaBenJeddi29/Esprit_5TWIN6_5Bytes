<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
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
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $type = $request->string('type')->toString();
        $statut = $request->string('statut')->toString();
        $zoneId = $request->integer('zone_id');
        $perPage = $this->listingPerPage($request);

        $infrastructures = Infrastructure::query()
            ->with('zone')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nom', 'like', "%{$search}%")
                        ->orWhere('materiau', 'like', "%{$search}%");
                });
            })
            ->when(in_array($type, Infrastructure::TYPES, true), fn ($query) => $query->where('type', $type))
            ->when(in_array($statut, Infrastructure::STATUTS, true), fn ($query) => $query->where('statut', $statut))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->orderBy('nom')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.infrastructures.index', [
            'infrastructures' => $infrastructures,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Infrastructure::TYPES,
            'statuts' => Infrastructure::STATUTS,
            'search' => $search,
            'currentType' => $type,
            'currentStatut' => $statut,
            'currentZone' => $zoneId ?: null,
            'perPage' => $perPage,
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

    public function show(Request $request, Infrastructure $infrastructure): View
    {
        $infrastructure->load('zone');

        $maintenances = $infrastructure->maintenances()
            ->latest('date_intervention')
            ->paginate($this->listingPerPage($request), ['*'], 'maintenance_page')
            ->withQueryString();

        return view('back.infrastructures.show', compact('infrastructure', 'maintenances'));
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
