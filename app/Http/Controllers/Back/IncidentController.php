<?php

namespace App\Http\Controllers\Back;

use App\Exceptions\InvalidIncidentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreBackIncidentRequest;
use App\Http\Requests\Incident\StoreIncidentCommentRequest;
use App\Http\Requests\Incident\UpdateBackIncidentRequest;
use App\Http\Requests\Incident\UpdateIncidentStatusRequest;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentWorkflowService $workflow,
    ) {
    }
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $zoneId = $request->integer('zone_id');

        $incidents = Incident::query()
            ->with(['user', 'zone', 'assignee'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($status, Incident::STATUSES), fn ($query) => $query->where('status', $status))
            ->when(array_key_exists($type, Incident::TYPES), fn ($query) => $query->where('type', $type))
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();

        return view('back.incidents.index', [
            'incidents' => $incidents,
            'statuses' => Incident::STATUSES,
            'types' => Incident::TYPES,
            'zones' => Zone::orderBy('nom')->get(),
            'search' => $search,
            'currentStatus' => $status,
            'currentType' => $type,
            'currentZone' => $zoneId ?: null,
        ]);
    }

    public function create(): View
    {
        return view('back.incidents.create', $this->formData(new Incident([
            'status' => 'nouveau',
            'severity' => 'moyenne',
            'priority' => 'normale',
            'type' => 'fuite',
        ])));
    }

    public function store(StoreBackIncidentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['reported_at'] = now();
        $data['status'] = 'nouveau';
        $data['resolved_at'] = null;
        $data['resolution_note'] = null;
        $data['rejection_reason'] = null;

        $incident = Incident::create($data);

        $incident->statusHistories()->create([
            'user_id' => $request->user()->id,
            'ancien_statut' => null,
            'nouveau_statut' => $incident->status,
            'comment' => 'Incident créé depuis le Back Office.',
        ]);

        return redirect()
            ->route('back.incidents.show', $incident)
            ->with('success', "L'incident {$incident->reference} a été créé.");
    }

    public function show(Incident $incident): View
    {
        $incident->load([
            'user',
            'zone',
            'infrastructure',
            'assignee',
            'duplicateOf',
            'duplicates',
            'photos',
            'comments.user',
            'statusHistories.user',
        ]);

        return view('back.incidents.show', [
            'incident' => $incident,
            'allowedTransitions' => $this->workflow->allowedTransitions($incident),
        ]);
    }

    public function edit(Incident $incident): View
    {
        return view('back.incidents.edit', $this->formData($incident));
    }

    public function update(UpdateBackIncidentRequest $request, Incident $incident): RedirectResponse
    {
        // Le statut et les comptes rendus sont exclus du payload validé :
        // seule la route dédiée au workflow peut les modifier.
        $incident->update($request->validated());

        return redirect()
            ->route('back.incidents.show', $incident)
            ->with('success', 'L\'incident a été mis à jour.');
    }

    public function updateStatus(UpdateIncidentStatusRequest $request, Incident $incident): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->workflow->transition(
                $incident,
                $data['status'],
                $request->user(),
                $data['comment'] ?? null,
                $data['resolution_note'] ?? null,
                $data['rejection_reason'] ?? null,
            );
        } catch (InvalidIncidentTransitionException $exception) {
            return back()
                ->withErrors(['status' => $exception->getMessage()])
                ->withInput();
        }

        return back()->with('success', 'Le statut de l\'incident a été mis à jour.');
    }

    public function storeComment(StoreIncidentCommentRequest $request, Incident $incident): RedirectResponse
    {
        $incident->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_internal' => (bool) $request->boolean('is_internal'),
        ]);

        return back()->with('success', 'Le commentaire a été ajouté.');
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        foreach ($incident->photos as $photo) {
            Storage::disk('local')->delete($photo->chemin_fichier);
        }

        $reference = $incident->reference;
        $incident->delete();

        return redirect()
            ->route('back.incidents.index')
            ->with('success', "L'incident {$reference} a été supprimé.");
    }

    public function downloadPhoto(Incident $incident, int $photoId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $photo = $incident->photos()->findOrFail($photoId);

        abort_unless(Storage::disk('local')->exists($photo->chemin_fichier), 404);

        return Storage::disk('local')->download(
            $photo->chemin_fichier,
            $photo->nom_original ?? basename($photo->chemin_fichier),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Incident $incident): array
    {
        return [
            'incident' => $incident,
            'zones' => Zone::orderBy('nom')->get(),
            'infrastructures' => Infrastructure::orderBy('nom')->get(),
            'types' => Incident::TYPES,
            'severities' => Incident::SEVERITIES,
            'priorities' => Incident::PRIORITIES,
            'gestionnaires' => User::query()
                ->whereIn('role', ['gestionnaire', 'admin'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}
