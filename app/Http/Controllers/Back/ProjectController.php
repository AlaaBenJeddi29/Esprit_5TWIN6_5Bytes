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
use App\Services\ProjectMetrics;
use App\Services\ProjectReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            ->withCount('projectPhases')
            ->withCount(['projectPhases as overdue_phases_count' => function ($query) {
                $query->whereNotNull('date_fin')
                    ->whereDate('date_fin', '<', now()->toDateString())
                    ->where('avancement', '<', 100);
            }])
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
        return view('back.projects.create', $this->formData(new Project));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(fn () => Project::create($request->validated()));

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
            'metrics' => new ProjectMetrics($project),
        ]);
    }

    /**
     * Gestion 5 — Rapport IA du projet (PDF, Dompdf + IA locale Ollama).
     *
     * Action strictement en lecture : aucune donnée du projet n'est modifiée
     * et aucun événement d'audit n'est créé — seul le texte IA est mis en
     * cache une heure (clé basée sur le hash du contexte). Sans Ollama, le
     * PDF affiche un bandeau explicite « sans rédaction IA ».
     */
    public function rapport(Request $request, Project $project, ProjectReportService $service): Response
    {
        // La génération IA sans carte graphique peut dépasser les 30 s PHP par défaut.
        set_time_limit(240);

        $rapport = $service->genererRapport($project);

        $pdf = Pdf::loadView('back.rapports.gestion-5-projet-pdf', [
            'project' => $project,
            'rapport' => $rapport,
        ])->setPaper('a4');

        $nom = 'rapport-projet-'.$project->id.'-'.now()->format('Y-m-d').'.pdf';

        // Par défaut : affichage navigateur (imprimable). ?telecharger=1 : téléchargement direct.
        return $request->boolean('telecharger') ? $pdf->download($nom) : $pdf->stream($nom);
    }

    public function edit(Project $project): View
    {
        return view('back.projects.edit', $this->formData($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(fn () => $project->update($request->validated()));

        return redirect()
            ->route('back.projects.show', $project)
            ->with('success', "Le projet « {$project->titre} » a été mis à jour.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $titre = $project->titre;

        DB::transaction(function () use ($project) {
            $project->projectDocuments()
                ->pluck('chemin_fichier')
                ->filter()
                ->each(fn (string $path) => Storage::disk('local')->delete($path));

            $project->delete();
        });

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
