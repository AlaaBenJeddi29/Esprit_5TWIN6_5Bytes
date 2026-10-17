<?php

namespace App\Http\Controllers\Back;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Infrastructure\StoreMaintenanceRequest;
use App\Http\Requests\Infrastructure\UpdateMaintenanceRequest;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $infrastructureId = $request->integer('infrastructure_id');

        $maintenances = Maintenance::query()
            ->with(['infrastructure', 'technicien'])
            ->when($search !== '', fn ($query) => $query->where('type', 'like', "%{$search}%"))
            ->when($infrastructureId, fn ($query) => $query->where('infrastructure_id', $infrastructureId))
            ->latest('date_intervention')
            ->paginate(15)
            ->withQueryString();

        return view('back.maintenances.index', [
            'maintenances' => $maintenances,
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'search' => $search,
            'currentInfrastructure' => $infrastructureId ?: null,
        ]);
    }

    public function create(Request $request): View
    {
        return view('back.maintenances.create', [
            'maintenance' => new Maintenance([
                'statut' => 'planifiee',
                'infrastructure_id' => $request->integer('infrastructure_id') ?: null,
            ]),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'techniciens' => $this->techniciens(),
        ]);
    }

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $maintenance = Maintenance::create($request->validated());

        return redirect()
            ->route('back.maintenances.show', $maintenance)
            ->with('success', 'La maintenance a été enregistrée.');
    }

    public function show(Maintenance $maintenance): View
    {
        $maintenance->load(['infrastructure.zone', 'technicien']);

        return view('back.maintenances.show', compact('maintenance'));
    }

    public function edit(Maintenance $maintenance): View
    {
        return view('back.maintenances.edit', [
            'maintenance' => $maintenance,
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'techniciens' => $this->techniciens(),
        ]);
    }

    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        $maintenance->update($request->validated());

        return redirect()
            ->route('back.maintenances.show', $maintenance)
            ->with('success', 'La maintenance a été mise à jour.');
    }

    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return redirect()
            ->route('back.maintenances.index')
            ->with('success', 'La maintenance a été supprimée.');
    }

    /**
     * Techniciens éligibles : personnel du Back Office (gestionnaires et admins).
     * Aucun rôle « technicien » distinct n'est défini dans le schéma confirmé.
     */
    private function techniciens()
    {
        return User::query()
            ->whereIn('role', UserRole::backOfficeValues())
            ->orderBy('name')
            ->get();
    }
}
