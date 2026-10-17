<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Drought\StoreRestrictionRequest;
use App\Http\Requests\Drought\UpdateRestrictionRequest;
use App\Models\Drought\Restriction;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestrictionController extends Controller
{
    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $statut = $request->string('statut')->toString();
        $type = $request->string('type')->toString();

        $restrictions = Restriction::query()
            ->with('zone')
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(array_key_exists($statut, Restriction::STATUTS), fn ($query) => $query->where('statut', $statut))
            ->when(array_key_exists($type, Restriction::TYPES), fn ($query) => $query->where('type', $type))
            ->latest('date_debut')
            ->paginate(15)
            ->withQueryString();

        return view('back.drought.restrictions.index', [
            'restrictions' => $restrictions,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Restriction::TYPES,
            'statuts' => Restriction::STATUTS,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
            'currentType' => $type,
        ]);
    }

    public function create(): View
    {
        return view('back.drought.restrictions.create', $this->formData(new Restriction([
            'type' => 'vigilance',
            'statut' => 'active',
            'date_debut' => now(),
        ])));
    }

    public function store(StoreRestrictionRequest $request): RedirectResponse
    {
        Restriction::create($request->validated());

        return redirect()
            ->route('back.restrictions.index')
            ->with('success', 'La restriction a été créée.');
    }

    public function edit(Restriction $restriction): View
    {
        return view('back.drought.restrictions.edit', $this->formData($restriction));
    }

    public function update(UpdateRestrictionRequest $request, Restriction $restriction): RedirectResponse
    {
        $restriction->update($request->validated());

        return redirect()
            ->route('back.restrictions.index')
            ->with('success', 'La restriction a été mise à jour.');
    }

    public function destroy(Restriction $restriction): RedirectResponse
    {
        $restriction->delete();

        return redirect()
            ->route('back.restrictions.index')
            ->with('success', 'La restriction a été supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Restriction $restriction): array
    {
        return [
            'restriction' => $restriction,
            'zones' => Zone::orderBy('nom')->get(),
            'types' => Restriction::TYPES,
            'statuts' => Restriction::STATUTS,
        ];
    }
}
