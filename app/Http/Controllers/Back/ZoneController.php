<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infrastructure\StoreZoneRequest;
use App\Http\Requests\Infrastructure\UpdateZoneRequest;
use App\Models\Infrastructure\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();

        $zones = Zone::query()
            ->withCount('infrastructures')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('commune', 'like', "%{$search}%")
                        ->orWhere('code_postal', 'like', "%{$search}%");
                });
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return view('back.zones.index', compact('zones', 'search'));
    }

    public function create(): View
    {
        return view('back.zones.create', ['zone' => new Zone()]);
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        $zone = Zone::create($request->validated());

        return redirect()
            ->route('back.zones.show', $zone)
            ->with('success', "La zone « {$zone->nom} » a été créée.");
    }

    public function show(Zone $zone): View
    {
        $zone->load(['infrastructures' => fn ($query) => $query->orderBy('nom')]);

        return view('back.zones.show', compact('zone'));
    }

    public function edit(Zone $zone): View
    {
        return view('back.zones.edit', compact('zone'));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return redirect()
            ->route('back.zones.show', $zone)
            ->with('success', "La zone « {$zone->nom} » a été mise à jour.");
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        $nom = $zone->nom;
        $zone->delete();

        return redirect()
            ->route('back.zones.index')
            ->with('success', "La zone « {$nom} » a été supprimée.");
    }
}
