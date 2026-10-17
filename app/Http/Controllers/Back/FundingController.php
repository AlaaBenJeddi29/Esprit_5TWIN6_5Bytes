<?php

namespace App\Http\Controllers\Back;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreFundingRequest;
use App\Http\Requests\Project\UpdateFundingRequest;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FundingController extends Controller
{
    public function create(Project $project): View
    {
        return view('back.projects.fundings.create', [
            'project' => $project,
            'funding' => new Funding(),
            'sources' => Funding::SOURCES,
            'donateurs' => $this->donateurs(),
        ]);
    }

    public function store(StoreFundingRequest $request, Project $project): RedirectResponse
    {
        $funding = $project->fundings()->create($request->validated());

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le financement a été enregistré.');
    }

    public function edit(Project $project, Funding $funding): View
    {
        $this->ensureBelongsToProject($project, $funding);

        return view('back.projects.fundings.edit', [
            'project' => $project,
            'funding' => $funding,
            'sources' => Funding::SOURCES,
            'donateurs' => $this->donateurs(),
        ]);
    }

    public function update(UpdateFundingRequest $request, Project $project, Funding $funding): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $funding);

        $funding->update($request->validated());

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le financement a été mis à jour.');
    }

    public function destroy(Project $project, Funding $funding): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $funding);

        $funding->delete();

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le financement a été supprimé.');
    }

    private function donateurs()
    {
        return User::where('role', UserRole::Citoyen->value)->orderBy('name')->get();
    }

    private function ensureBelongsToProject(Project $project, Funding $funding): void
    {
        abort_unless($funding->project_id === $project->id, 404);
    }
}
