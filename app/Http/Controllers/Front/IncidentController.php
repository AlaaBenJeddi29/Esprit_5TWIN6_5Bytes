<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Concerns\ResolvesListingFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreIncidentCommentRequest;
use App\Http\Requests\Incident\StoreIncidentPhotoRequest;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Jobs\DetectIncidentSimilarities;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Services\DuplicateDetector;
use App\Services\IncidentAdminNotifier;
use App\Services\IncidentClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentController extends Controller
{
    use ResolvesListingFilters;

    public function __construct(
        private readonly IncidentClassifier $classifier,
        private readonly DuplicateDetector $duplicateDetector,
        private readonly IncidentAdminNotifier $adminNotifier,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $statut = $request->string('statut')->toString();
        $type = $request->string('type')->toString();
        $perPage = $this->listingPerPage($request, 10);

        $incidents = Incident::query()
            ->where('user_id', $request->user()->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($statut, Incident::STATUSES), fn ($query) => $query->where('status', $statut))
            ->when(array_key_exists($type, Incident::TYPES), fn ($query) => $query->where('type', $type))
            ->with('zone')
            ->latest('reported_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('front.incidents.index', [
            'incidents' => $incidents,
            'statuses' => Incident::STATUSES,
            'types' => Incident::TYPES,
            'search' => $search,
            'currentStatut' => $statut,
            'currentType' => $type,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('front.incidents.create', [
            'zones' => Zone::orderBy('nom')->get(),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'types' => Incident::TYPES,
            'severities' => Incident::SEVERITIES,
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $classification = $this->classifier->classify($data);

        $severity = $this->strongestSeverity($classification['severity'], $data['severity'] ?? null);

        $incident = Incident::create([
            'user_id' => $request->user()->id,
            'zone_id' => $data['zone_id'] ?? null,
            'infrastructure_id' => $data['infrastructure_id'] ?? null,
            'type' => $classification['type'],
            'title' => $data['title'],
            'description' => $data['description'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'severity' => $severity,
            'status' => 'nouveau',
            'priority' => $this->priorityFor($severity),
            'ai_category' => $classification['type'],
            'ai_confidence' => $classification['confidence'],
            'ai_summary' => $classification['summary'],
            'reported_at' => now(),
        ]);

        $incident->statusHistories()->create([
            'user_id' => $request->user()->id,
            'ancien_statut' => null,
            'nouveau_statut' => 'nouveau',
            'comment' => 'Signalement créé par le citoyen.',
        ]);

        $duplicate = $this->duplicateDetector->findSimilar($incident);

        if ($duplicate) {
            $incident->update([
                'is_duplicate' => true,
                'duplicate_of_id' => $duplicate->id,
                'ai_summary' => $classification['summary'].' Doublon probable du signalement '.$duplicate->reference.'.',
            ]);
        }

        $this->adminNotifier->notifyNewIncident($incident);

        DetectIncidentSimilarities::dispatch($incident->id);

        return redirect()
            ->route('citizen.incidents.show', $incident)
            ->with('success', "Votre signalement {$incident->reference} a bien été enregistré.");
    }

    public function show(Request $request, Incident $incident): View
    {
        Gate::authorize('view', $incident);

        $incident->load([
            'zone',
            'infrastructure',
            'photos',
            'comments' => fn ($query) => $query->where('is_internal', false)->with('user')->latest(),
            'statusHistories.user',
            'feedbacks' => fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->with(['histories.user']),
        ]);

        return view('front.incidents.show', compact('incident'));
    }

    public function trackForm(): View
    {
        return view('front.incidents.track');
    }

    public function track(Request $request): View
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:30'],
        ]);

        $incident = Incident::query()
            ->where('reference', strtoupper(trim($validated['reference'])))
            ->with(['zone', 'statusHistories'])
            ->first();

        if (! $incident) {
            return view('front.incidents.track', [
                'notFound' => true,
                'reference' => $validated['reference'],
            ]);
        }

        return view('front.incidents.track', [
            'incident' => $incident,
        ]);
    }

    public function storeComment(StoreIncidentCommentRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('comment', $incident);

        $incident->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_internal' => false,
        ]);

        return back()->with('success', 'Votre commentaire a été ajouté.');
    }

    public function storePhoto(StoreIncidentPhotoRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('uploadPhoto', $incident);

        $file = $request->file('photo');
        $path = $file->store("incidents/{$incident->id}", 'local');

        $incident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'taille' => $file->getSize(),
        ]);

        return back()->with('success', 'La photo a été ajoutée au signalement.');
    }

    public function showPhoto(Request $request, Incident $incident, int $photoId): StreamedResponse
    {
        Gate::authorize('view', $incident);

        $photo = $incident->photos()->findOrFail($photoId);
        $disk = Storage::disk('local');

        abort_unless($disk->exists($photo->chemin_fichier), 404);

        return $disk->response($photo->chemin_fichier, $photo->nom_original ?? basename($photo->chemin_fichier), [
            'Content-Type' => $photo->mime_type ?: $disk->mimeType($photo->chemin_fichier),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadPhoto(Request $request, Incident $incident, int $photoId): StreamedResponse
    {
        Gate::authorize('view', $incident);

        $photo = $incident->photos()->findOrFail($photoId);

        abort_unless(Storage::disk('local')->exists($photo->chemin_fichier), 404);

        return Storage::disk('local')->download($photo->chemin_fichier, $photo->nom_original ?? basename($photo->chemin_fichier));
    }

    private function strongestSeverity(string $classified, ?string $declared): string
    {
        $rank = ['faible' => 1, 'moyenne' => 2, 'haute' => 3, 'critique' => 4];

        if ($declared === null || ! isset($rank[$declared])) {
            return $classified;
        }

        return $rank[$declared] > $rank[$classified] ? $declared : $classified;
    }

    private function priorityFor(string $severity): string
    {
        return match ($severity) {
            'critique' => 'urgente',
            'haute' => 'haute',
            'faible' => 'basse',
            default => 'normale',
        };
    }
}
