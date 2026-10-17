<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectAssistantRequest;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Services\ProjectAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectAssistant $assistant)
    {
    }

    public function index(Request $request): View
    {
        $zoneId = $request->integer('zone_id');
        $statut = $request->string('statut')->toString();

        $projects = Project::query()
            ->with(['zone', 'responsable'])
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->when(in_array($statut, Project::STATUTS, true), fn ($query) => $query->where('statut', $statut))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('front.projects.index', [
            'projects' => $projects,
            'zones' => Zone::orderBy('nom')->get(),
            'statuts' => Project::STATUTS,
            'currentZone' => $zoneId ?: null,
            'currentStatut' => $statut,
        ]);
    }

    public function show(Project $project): View
    {
        $project->load([
            'zone',
            'infrastructure',
            'responsable',
            'projectPhases.contractor',
            'fundings.donateur',
            'publicDocuments',
        ]);

        $donationsCount = $project->fundings->where('source', 'don')->count();
        $donationsTotal = (float) $project->fundings->where('source', 'don')->sum('montant');

        return view('front.projects.show', [
            'project' => $project,
            'donationsCount' => $donationsCount,
            'donationsTotal' => $donationsTotal,
            'assistantAnswer' => session('assistant_answer'),
            'assistantQuestion' => session('assistant_question'),
            'assistantSuggestions' => $this->assistant->suggestions(),
        ]);
    }

    public function donate(StoreDonationRequest $request, Project $project): RedirectResponse
    {
        abort_if($project->statut === 'annulé', 404);

        $project->fundings()->create([
            'source' => 'don',
            'donateur_id' => $request->user()->id,
            'montant' => $request->validated('montant'),
            'date_versement' => now()->toDateString(),
        ]);

        return redirect()
            ->route('public.projects.show', $project)
            ->with('success', sprintf(
                'Don simulé de %s € enregistré. Aucun paiement réel n\'a été effectué.',
                number_format((float) $request->validated('montant'), 2, ',', ' '),
            ));
    }

    public function downloadDocument(Project $project, ProjectDocument $document): StreamedResponse
    {
        abort_unless($document->project_id === $project->id, 404);
        abort_unless($document->est_public, 404);
        abort_unless(Storage::disk('local')->exists($document->chemin_fichier), 404);

        return Storage::disk('local')->download($document->chemin_fichier, $document->titre);
    }

    public function assistant(ProjectAssistantRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $answer = $this->assistant->answer($project, $request->validated('question'));

        if ($request->expectsJson()) {
            return response()->json([
                'question' => $request->validated('question'),
                'answer' => $answer,
                'disclaimer' => 'Réponse générée localement à partir des données du projet, sans fournisseur d\'IA externe.',
            ]);
        }

        return redirect()
            ->route('public.projects.show', $project)
            ->with('assistant_answer', $answer)
            ->with('assistant_question', $request->validated('question'));
    }
}
