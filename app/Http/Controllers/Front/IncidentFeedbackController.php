<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StoreIncidentFeedbackRequest;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Notifications\Incident\ComplaintSubmittedNotification;
use App\Notifications\Incident\NewComplaintNotification;
use App\Services\ComplaintNotifier;
use App\Services\ComplaintWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncidentFeedbackController extends Controller
{
    public function __construct(
        private readonly ComplaintWorkflowService $complaints,
        private readonly ComplaintNotifier $notifier,
    ) {}

    /**
     * Incidents traités par le citoyen, avec l'état de leur évaluation.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $incidents = Incident::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($user) {
                $query->where('status', 'traite')
                    ->orWhereHas('feedbacks', fn ($query) => $query->where('user_id', $user->id));
            })
            ->with([
                'zone',
                'feedbacks' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->latest('resolved_at')
            ->latest('id')
            ->paginate(10);

        return view('front.feedbacks.index', compact('incidents'));
    }

    public function store(StoreIncidentFeedbackRequest $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('feedback', $incident);

        $data = $request->validated();
        $description = trim((string) ($data['complaint_description'] ?? ''));
        $hasComplaint = $description !== '';

        try {
            $feedback = DB::transaction(function () use ($request, $incident, $data, $description, $hasComplaint): IncidentFeedback {
                $locked = Incident::query()
                    ->whereKey($incident->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->status !== 'traite') {
                    throw ValidationException::withMessages([
                        'feedback' => 'L\'évaluation n\'est disponible que pour un incident traité.',
                    ]);
                }

                if (IncidentFeedback::query()
                    ->where('incident_id', $locked->id)
                    ->where('user_id', $request->user()->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'feedback' => 'Vous avez déjà évalué ce signalement.',
                    ]);
                }

                $feedback = IncidentFeedback::create([
                    'incident_id' => $locked->id,
                    'user_id' => $request->user()->id,
                    'rating' => (int) $data['rating'],
                    'comment' => $data['comment'] ?? null,
                    'resolution_confirmed' => (bool) $data['resolution_confirmed'],
                    'complaint_description' => $hasComplaint ? $description : null,
                    'reopen_requested' => $hasComplaint && $request->boolean('reopen_requested'),
                    'complaint_status' => $hasComplaint ? 'en_attente' : null,
                ]);

                $this->complaints->recordHistory(
                    $feedback,
                    $request->user(),
                    'depot',
                    $hasComplaint ? $description : 'Évaluation déposée sans réclamation.',
                    null,
                    $hasComplaint ? 'en_attente' : null,
                );

                return $feedback;
            }, 3);
        } catch (QueryException $exception) {
            // Filet de sécurité en cas de soumissions concurrentes : l'index
            // unique (incident_id, user_id) protège l'intégrité.
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            return back()
                ->withErrors(['feedback' => 'Vous avez déjà évalué ce signalement.'])
                ->withInput();
        }

        if ($hasComplaint) {
            $feedback->loadMissing('incident');
            $this->notifier->notifyCitizen(
                $request->user(),
                new ComplaintSubmittedNotification($feedback),
            );
            $this->notifier->notifyManagers(new NewComplaintNotification($feedback));

            return redirect()
                ->route('citizen.incidents.show', $incident)
                ->with('success', 'Votre évaluation et votre réclamation ont bien été enregistrées.');
        }

        return redirect()
            ->route('citizen.incidents.show', $incident)
            ->with('success', 'Merci pour votre évaluation.');
    }
}
