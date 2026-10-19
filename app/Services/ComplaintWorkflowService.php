<?php

namespace App\Services;

use App\Exceptions\InvalidComplaintTransitionException;
use App\Models\Incident\IncidentFeedback;
use App\Models\Incident\IncidentFeedbackHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Workflow dédié aux réclamations, indépendant du statut de l'incident.
 *
 * Une réclamation acceptée ne réouvre jamais l'incident à elle seule : la
 * réouverture effective reste une action explicite qui passe exclusivement
 * par IncidentWorkflowService.
 */
class ComplaintWorkflowService
{
    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'en_attente' => ['en_examen'],
        'en_examen' => ['acceptee', 'rejetee'],
        'acceptee' => ['cloturee'],
        'rejetee' => ['cloturee'],
    ];

    public const MIN_JUSTIFICATION_LENGTH = 10;

    public const MAX_JUSTIFICATION_LENGTH = 2000;

    private const ACTION_BY_STATUS = [
        'en_examen' => 'examen',
        'acceptee' => 'acceptation',
        'rejetee' => 'refus',
        'cloturee' => 'cloture',
    ];

    /**
     * @return array<string, string> Statut cible => libellé affichable.
     */
    public function allowedTransitions(IncidentFeedback $feedback): array
    {
        $targets = [];

        foreach (self::TRANSITIONS[$feedback->complaint_status] ?? [] as $target) {
            $targets[$target] = IncidentFeedback::COMPLAINT_STATUSES[$target] ?? ucfirst($target);
        }

        return $targets;
    }

    public function canTransition(?string $from, string $to): bool
    {
        return $from !== null && in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function requiresJustification(string $to): bool
    {
        return in_array($to, ['acceptee', 'rejetee'], true);
    }

    /**
     * @throws InvalidComplaintTransitionException
     */
    public function transition(
        IncidentFeedback $feedback,
        string $to,
        User $actor,
        ?string $justification = null,
    ): IncidentFeedback {
        $justification = $this->normalize($justification);

        return DB::transaction(function () use ($feedback, $to, $actor, $justification): IncidentFeedback {
            $locked = IncidentFeedback::query()
                ->whereKey($feedback->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->complaint_status === null) {
                throw InvalidComplaintTransitionException::noComplaint();
            }

            $from = $locked->complaint_status;

            if (! $this->canTransition($from, $to)) {
                throw InvalidComplaintTransitionException::between($from, $to);
            }

            if ($this->requiresJustification($to)) {
                $this->assertJustification($justification);
                $locked->complaint_decision = $to;
                $locked->decision_reason = $justification;
            }

            if ($to === 'en_examen') {
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = now();
            }

            if (in_array($to, ['acceptee', 'rejetee'], true)) {
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = now();
            }

            $locked->complaint_status = $to;
            $locked->save();

            $locked->histories()->create([
                'user_id' => $actor->id,
                'action' => self::ACTION_BY_STATUS[$to],
                'ancien_statut' => $from,
                'nouveau_statut' => $to,
                'comment' => $justification,
            ]);

            return $locked->refresh();
        }, 3);
    }

    /**
     * Journalise une action qui ne modifie pas le statut de la réclamation
     * (dépôt initial, réponse du gestionnaire, réouverture effective).
     */
    public function recordHistory(
        IncidentFeedback $feedback,
        User $actor,
        string $action,
        ?string $comment = null,
        ?string $from = null,
        ?string $to = null,
    ): IncidentFeedbackHistory {
        return $feedback->histories()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'ancien_statut' => $from,
            'nouveau_statut' => $to,
            'comment' => $comment,
        ]);
    }

    /**
     * @throws InvalidComplaintTransitionException
     */
    private function assertJustification(?string $justification): void
    {
        $length = $justification === null ? 0 : mb_strlen($justification);

        if ($length < self::MIN_JUSTIFICATION_LENGTH || $length > self::MAX_JUSTIFICATION_LENGTH) {
            throw InvalidComplaintTransitionException::justificationRequired();
        }
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
