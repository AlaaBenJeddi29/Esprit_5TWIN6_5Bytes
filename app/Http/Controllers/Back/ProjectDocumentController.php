<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectDocumentRequest;
use App\Http\Requests\Project\UpdateProjectDocumentRequest;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProjectDocumentController extends Controller
{
    public function create(Project $project): View
    {
        return view('back.projects.documents.create', [
            'project' => $project,
            'document' => new ProjectDocument(),
            'types' => ProjectDocument::TYPES,
        ]);
    }

    public function store(StoreProjectDocumentRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $data['chemin_fichier'] = $request->file('fichier')
            ->store("projects/{$project->id}/documents", 'local');
        unset($data['fichier']);

        $project->projectDocuments()->create($data);

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le document a été téléversé.');
    }

    public function edit(Project $project, ProjectDocument $document): View
    {
        $this->ensureBelongsToProject($project, $document);

        return view('back.projects.documents.edit', [
            'project' => $project,
            'document' => $document,
            'types' => ProjectDocument::TYPES,
        ]);
    }

    public function update(UpdateProjectDocumentRequest $request, Project $project, ProjectDocument $document): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $document);

        $data = $request->validated();
        unset($data['fichier']);

        $previousPath = $document->chemin_fichier;

        if ($request->hasFile('fichier')) {
            $data['chemin_fichier'] = $request->file('fichier')
                ->store("projects/{$project->id}/documents", 'local');
        }

        $document->update($data);

        if (isset($data['chemin_fichier']) && $previousPath !== $data['chemin_fichier']) {
            Storage::disk('local')->delete($previousPath);
        }

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le document a été mis à jour.');
    }

    public function destroy(Project $project, ProjectDocument $document): RedirectResponse
    {
        $this->ensureBelongsToProject($project, $document);

        $path = $document->chemin_fichier;
        $document->delete();

        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', 'Le document a été supprimé.');
    }

    private function ensureBelongsToProject(Project $project, ProjectDocument $document): void
    {
        abort_unless($document->project_id === $project->id, 404);
    }
}
