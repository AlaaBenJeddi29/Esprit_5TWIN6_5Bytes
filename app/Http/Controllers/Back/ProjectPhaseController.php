<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectPhaseRequest;
use App\Http\Requests\Project\UpdateProjectPhaseRequest;
use App\Models\Project\Contractor;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectPhaseController extends Controller
{
    public function create(Project $project): View
    {
        return view('back.projects.phases.create', [
            'project' => $project,
            'phase' => new ProjectPhase,
            'contractors' => Contractor::orderBy('nom')->get(),
        ]);
    }

    public function store(StoreProjectPhaseRequest $request, Project $project): RedirectResponse
    {
        $phase = DB::transaction(fn () => $project->projectPhases()->create($request->validated()));

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "La phase « {$phase->nom} » a été ajoutée.");
    }

    public function edit(Project $project, ProjectPhase $phase): View
    {
        $this->ensureBelongsToProject($project, $phase);

        return view('back.projects.phases.edit', [
            'project' => $project,
            'phase' => $phase,
            'contractors' => Contractor::orderBy('nom')->get(),
        ]);
    }

    public function update(UpdateProjectPhaseRequest $request, Project $project, ProjectPhase $phase): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $phase);

        DB::transaction(fn () => $phase->update($request->validated()));

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "La phase « {$phase->nom} » a été mise à jour.");
    }

    public function destroy(Project $project, ProjectPhase $phase): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $phase);

        $nom = $phase->nom;
        DB::transaction(fn () => $phase->delete());

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "La phase « {$nom} » a été supprimée.");
    }

    private function ensureBelongsToProject(Project $project, ProjectPhase $phase): void
    {
        abort_unless($phase->project_id === $project->id, 404);
    }
}
