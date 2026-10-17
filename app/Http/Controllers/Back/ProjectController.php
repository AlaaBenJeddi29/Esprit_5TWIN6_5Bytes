<?php

namespace App\Http\Controllers\Back;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $statut = $request->string('statut')->toString();

        $projects = Project::query()
            ->with(['zone', 'responsable'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('titre', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($statut, Project::STATUTS, true), fn ($query) => $query->where('statut', $statut))
            ->orderByDesc('date_debut')
            ->paginate(15)
            ->withQueryString();

        return view('back.projects.index', [
            'projects' => $projects,
            'search' => $search,
            'statut' => $statut,
            'statuts' => Project::STATUTS,
        ]);
    }

    public function create(): View
    {
        return view('back.projects.create', $this->formData(new Project()));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "Le projet « {$project->titre} » a été créé.");
    }

    public function show(Project $project): View
    {
        $project->load([
            'zone',
            'infrastructure',
            'responsable',
            'projectPhases.contractor',
            'fundings.donateur',
            'projectDocuments',
        ]);

        return view('back.projects.show', [
            'project' => $project,
            'budgetTotal' => $project->budgetTotal(),
            'fundingTotal' => $project->fundingTotal(),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('back.projects.edit', $this->formData($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "Le projet « {$project->titre} » a été mis à jour.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $titre = $project->titre;

        $project->projectDocuments()
            ->pluck('chemin_fichier')
            ->filter()
            ->each(fn (string $path) => Storage::disk('local')->delete($path));

        $project->delete();

        return redirect()
            ->route('back.projects.index')
            ->with('success', "Le projet « {$titre} » a été supprimé.");
    }

    /**
     * Données de formulaire partagées entre create et edit.
     *
     * @return array<string, mixed>
     */
    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'zones' => Zone::orderBy('nom')->get(),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'responsables' => User::whereIn('role', [
                UserRole::Gestionnaire->value,
                UserRole::Admin->value,
            ])->orderBy('name')->get(),
            'types' => Project::TYPES,
            'statuts' => Project::STATUTS,
        ];
    }
}
