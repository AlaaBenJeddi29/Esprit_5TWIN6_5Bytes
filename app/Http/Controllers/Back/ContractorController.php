<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreContractorRequest;
use App\Http\Requests\Project\UpdateContractorRequest;
use App\Models\Project\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractorController extends Controller
{
    use ResolvesListingFilters;

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $specialite = $request->string('specialite')->toString();
        $perPage = $this->listingPerPage($request);

        $specialites = Contractor::query()
            ->select('specialite')
            ->whereNotNull('specialite')
            ->distinct()
            ->orderBy('specialite')
            ->pluck('specialite');

        $contractors = Contractor::query()
            ->withCount('projectPhases')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('specialite', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($specialites->contains($specialite), fn ($query) => $query->where('specialite', $specialite))
            ->orderBy('nom')
            ->paginate($perPage)
            ->withQueryString();

        return view('back.contractors.index', [
            'contractors' => $contractors,
            'search' => $search,
            'specialites' => $specialites,
            'currentSpecialite' => $specialite,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('back.contractors.create', ['contractor' => new Contractor()]);
    }

    public function store(StoreContractorRequest $request): RedirectResponse
    {
        $contractor = Contractor::create($request->validated());

        return redirect()
            ->route('back.contractors.show', $contractor)
            ->with('success', "L'entreprise « {$contractor->nom} » a été créée.");
    }

    public function show(Contractor $contractor): View
    {
        $contractor->load(['projectPhases' => fn ($query) => $query->with('project')->orderBy('date_debut')]);

        return view('back.contractors.show', compact('contractor'));
    }

    public function edit(Contractor $contractor): View
    {
        return view('back.contractors.edit', compact('contractor'));
    }

    public function update(UpdateContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($request->validated());

        return redirect()
            ->route('back.contractors.show', $contractor)
            ->with('success', "L'entreprise « {$contractor->nom} » a été mise à jour.");
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        if ($contractor->projectPhases()->exists()) {
            return redirect()
                ->route('back.contractors.show', $contractor)
                ->with('error', "Impossible de supprimer « {$contractor->nom} » : des phases de projet y sont rattachées.");
        }

        $nom = $contractor->nom;
        $contractor->delete();

        return redirect()
            ->route('back.contractors.index')
            ->with('success', "L'entreprise « {$nom} » a été supprimée.");
    }
}
