<?php

namespace App\Http\Controllers\Back;

use App\Exceptions\InvalidComplaintTransitionException;
use App\Exceptions\InvalidIncidentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\CloseComplaintRequest;
use App\Http\Requests\Feedback\ComplaintDecisionRequest;
use App\Http\Requests\Feedback\ComplaintFilterRequest;
use App\Http\Requests\Feedback\ReopenIncidentRequest;
use App\Http\Requests\Feedback\RespondComplaintRequest;
use App\Http\Requests\Incident\IncidentStatsFilterRequest;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\Infrastructure\Zone;
use App\Notifications\Incident\ComplaintDecisionNotification;
use App\Notifications\Incident\ComplaintUnderReviewNotification;
use App\Notifications\Incident\ManagerResponseNotification;
use App\Notifications\Incident\ReopeningDecisionNotification;
use App\Services\ComplaintNotifier;
use App\Services\ComplaintWorkflowService;
use App\Services\IncidentWorkflowService;
use App\Services\SatisfactionStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintWorkflowService $complaints,
        private readonly IncidentWorkflowService $incidentWorkflow,
        private readonly ComplaintNotifier $notifier,
        private readonly SatisfactionStatsService $stats,
    ) {}

    public function index(ComplaintFilterRequest $request): View
    {
        $data = $request->validated();
        $search = trim((string) ($data['search'] ?? ''));

        $feedbacks = IncidentFeedback::query()
            ->whereNotNull('complaint_status')
            ->with(['incident.zone', 'user', 'reviewer'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('incident', function ($query) use ($search) {
                        $query->where('reference', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    })->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                filled($data['status'] ?? null),
                fn ($query) => $query->where('complaint_status', $data['status']),
            )
            ->when(
                filled($data['rating'] ?? null),
                fn ($query) => $query->where('rating', (int) $data['rating']),
            )
            ->when(
                filled($data['zone_id'] ?? null),
                fn ($query) => $query->whereHas(
                    'incident',
                    fn ($query) => $query->where('zone_id', (int) $data['zone_id']),
                ),
            )
            ->when(
                filled($data['from'] ?? null),
                fn ($query) => $query->where('created_at', '>=', Carbon::parse($data['from'])->startOfDay()),
            )
            ->when(
                filled($data['to'] ?? null),
                fn ($query) => $query->where('created_at', '<=', Carbon::parse($data['to'])->endOfDay()),
            )
            ->when(
                ! empty($data['reopen']),
                fn ($query) => $query->where('reopen_requested', true),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('back.complaints.index', [
            'feedbacks' => $feedbacks,
            'statuses' => IncidentFeedback::COMPLAINT_STATUSES,
            'zones' => Zone::orderBy('nom')->get(),
            'filters' => $data,
        ]);
    }

    public function show(IncidentFeedback $feedback): View
    {
        abort_unless($feedback->hasComplaint(), 404);

        Gate::authorize('view', $feedback);

        $feedback->load([
            'incident.user',
            'incident.zone',
            'user',
            'reviewer',
            'histories.user',
        ]);

        return view('back.complaints.show', [
            'feedback' => $feedback,
            'allowedTransitions' => $this->complaints->allowedTransitions($feedback),
        ]);
    }

    public function review(Request $request, IncidentFeedback $feedback): RedirectResponse
    {
        Gate::authorize('review', $feedback);

        try {
            $updated = $this->complaints->transition($feedback, 'en_examen', $request->user());
        } catch (InvalidComplaintTransitionException $exception) {
            return back()->withErrors(['complaint' => $exception->getMessage()]);
        }

        $this->notifier->notifyCitizen(
            $updated->user,
            new ComplaintUnderReviewNotification($updated->loadMissing('incident')),
        );

        return back()->with('success', 'La réclamation est maintenant en cours d\'examen.');
    }

    public function respond(RespondComplaintRequest $request, IncidentFeedback $feedback): RedirectResponse
    {
        Gate::authorize('respond', $feedback);

        $response = trim((string) $request->validated('response'));

        $updated = DB::transaction(function () use ($request, $feedback, $response): IncidentFeedback {
            $locked = IncidentFeedback::query()
                ->whereKey($feedback->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->complaint_status === 'cloturee') {
                throw ValidationException::withMessages([
                    'response' => 'Une réclamation clôturée ne peut plus recevoir de réponse.',
                ]);
            }

            $locked->manager_response = $response;
            $locked->save();

            $this->complaints->recordHistory(
                $locked,
                $request->user(),
                'reponse',
                $response,
            );

            return $locked->refresh();
        }, 3);

        $this->notifier->notifyCitizen(
            $updated->user,
            new ManagerResponseNotification($updated->loadMissing('incident')),
        );

        return back()->with('success', 'La réponse a été transmise au citoyen.');
    }

    public function decide(ComplaintDecisionRequest $request, IncidentFeedback $feedback): RedirectResponse
    {
        Gate::authorize('decide', $feedback);

        $data = $request->validated();

        try {
            $updated = $this->complaints->transition(
                $feedback,
                $data['decision'],
                $request->user(),
                $data['justification'],
            );
        } catch (InvalidComplaintTransitionException $exception) {
            return back()
                ->withErrors(['decision' => $exception->getMessage()])
                ->withInput();
        }

        $updated->loadMissing('incident');

        $this->notifier->notifyCitizen(
            $updated->user,
            new ComplaintDecisionNotification($updated),
        );

        if ($updated->reopen_requested && $updated->complaint_decision === 'rejetee') {
            $this->notifier->notifyCitizen(
                $updated->user,
                new ReopeningDecisionNotification($updated, false),
            );
        }

        return back()->with(
            'success',
            $data['decision'] === 'acceptee'
                ? 'La réclamation a été acceptée.'
                : 'La réclamation a été refusée.',
        );
    }

    public function close(CloseComplaintRequest $request, IncidentFeedback $feedback): RedirectResponse
    {
        Gate::authorize('close', $feedback);

        try {
            $this->complaints->transition(
                $feedback,
                'cloturee',
                $request->user(),
                $request->validated('justification'),
            );
        } catch (InvalidComplaintTransitionException $exception) {
            return back()->withErrors(['complaint' => $exception->getMessage()]);
        }

        return back()->with('success', 'La réclamation a été clôturée.');
    }

    /**
     * Réouverture effective de l'incident après acceptation de la demande.
     *
     * Le statut de l'incident est vérifié à nouveau sous verrou juste avant
     * la transition, qui passe exclusivement par IncidentWorkflowService.
     */
    public function reopen(ReopenIncidentRequest $request, IncidentFeedback $feedback): RedirectResponse
    {
        Gate::authorize('reopen', $feedback);

        $justification = trim((string) $request->validated('justification'));

        try {
            $updated = DB::transaction(function () use ($request, $feedback, $justification): IncidentFeedback {
                $locked = IncidentFeedback::query()
                    ->whereKey($feedback->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->complaint_status !== 'acceptee' || ! $locked->reopen_requested) {
                    throw InvalidComplaintTransitionException::reopenNotAllowed();
                }

                if ($locked->reopened_at !== null) {
                    throw InvalidComplaintTransitionException::alreadyReopened();
                }

                $incident = Incident::query()
                    ->whereKey($locked->incident_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($incident->status !== 'traite') {
                    throw InvalidComplaintTransitionException::reopenNotAllowed();
                }

                $this->incidentWorkflow->transition(
                    $incident,
                    'en_cours',
                    $request->user(),
                    $justification,
                );

                $locked->reopened_at = now();
                $locked->reopen_decision_reason = $justification;
                $locked->save();

                $this->complaints->recordHistory(
                    $locked,
                    $request->user(),
                    'reouverture',
                    $justification,
                    'traite',
                    'en_cours',
                );

                return $locked->refresh();
            }, 3);
        } catch (InvalidComplaintTransitionException|InvalidIncidentTransitionException $exception) {
            return back()
                ->withErrors(['reopen' => $exception->getMessage()])
                ->withInput();
        }

        $updated->loadMissing('incident');

        $this->notifier->notifyCitizen(
            $updated->user,
            new ReopeningDecisionNotification($updated, true),
        );

        return back()->with('success', 'L\'incident a été réouvert. Le citoyen a été notifié.');
    }

    public function stats(IncidentStatsFilterRequest $request): View
    {
        $period = $request->periodKey();
        [$from, $to] = $this->resolveRange($request, $period);

        return view('back.complaints.stats', [
            'summary' => $this->stats->summary($from, $to),
            'periods' => IncidentStatsFilterRequest::PERIODS,
            'currentPeriod' => $period,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $this->periodLabel($period, $from, $to),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRange(IncidentStatsFilterRequest $request, string $period): array
    {
        return match ($period) {
            '90d' => [now()->subDays(89)->toDateString(), now()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'custom' => [
                (string) $request->validated('from'),
                (string) $request->validated('to'),
            ],
            default => [now()->subDays(29)->toDateString(), now()->toDateString()],
        };
    }

    private function periodLabel(string $period, ?string $from, ?string $to): string
    {
        if ($period === 'custom') {
            return sprintf(
                'Du %s au %s',
                $from ? Carbon::parse($from)->format('d/m/Y') : '—',
                $to ? Carbon::parse($to)->format('d/m/Y') : '—',
            );
        }

        if ($period === 'year') {
            return 'Année '.now()->year;
        }

        return IncidentStatsFilterRequest::PERIODS[$period] ?? '30 derniers jours';
    }
}
