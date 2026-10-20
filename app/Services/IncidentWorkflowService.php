<?php

namespace App\Services;

use App\Exceptions\InvalidIncidentTransitionException;
use App\Models\Incident\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Workflow unique du cycle de vie des incidents.
 *
 * Toute modification de statut doit passer par ce service : il valide la
 * transition, les comptes rendus et motives, verrouille l'enregistrement,
 * applique la mise à jour et journalise la transition dans la même
 * transaction.
 */
class IncidentWorkflowService
{
    public function __construct(
        private readonly IncidentCitizenNotifier $citizenNotifier,
    ) {}

    /**
     * Transitions autorisées : statut actuel => statuts cibles.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'nouveau' => ['en_cours', 'rejete'],
        'en_cours' => ['en_attente', 'traite', 'rejete'],
        'en_attente' => ['en_cours', 'traite', 'rejete'],
        'traite' => ['en_cours'],
        'rejete' => ['nouveau'],
    ];

    /**
     * Réouvertures soumises à un motif obligatoire (10 caractères minimum).
     *
     * @var list<array{0: string, 1: string}>
     */
    private const REOPENINGS = [
        ['traite', 'en_cours'],
        ['rejete', 'nouveau'],
    ];

    public const MIN_NOTE_LENGTH = 10;

    public const MAX_NOTE_LENGTH = 2000;

    /**
     * @return array<string, string> Statut cible => libellé affichable.
     */
    public function allowedTransitions(Incident $incident): array
    {
        $targets = [];

        foreach (self::TRANSITIONS[$incident->status] ?? [] as $target) {
            $targets[$target] = $this->label($incident->status, $target);
        }

        return $targets;
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isReopening(string $from, string $to): bool
    {
        return in_array([$from, $to], self::REOPENINGS, true);
    }

    /**
     * Applique une transition de statut de manière atomique.
     *
     * @throws InvalidIncidentTransitionException
     */
    public function transition(
        Incident $incident,
        string $to,
        User $actor,
        ?string $comment = null,
        ?string $resolutionNote = null,
        ?string $rejectionReason = null,
    ): Incident {
        $comment = $this->normalize($comment);
        $resolutionNote = $this->normalize($resolutionNote);
        $rejectionReason = $this->normalize($rejectionReason);

        $resolvedHistoryId = null;

        $updated = DB::transaction(function () use ($incident, $to, $actor, $comment, $resolutionNote, $rejectionReason, &$resolvedHistoryId): Incident {
            $resolvedHistoryId = null;

            $locked = Incident::query()
                ->whereKey($incident->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;

            $this->assertValidTransition($from, $to, $comment, $resolutionNote, $rejectionReason);

            $snapshotResolution = null;
            $snapshotRejection = null;

            if ($to === 'traite') {
                $locked->resolution_note = $resolutionNote;
                $locked->rejection_reason = null;
                $locked->resolved_at = now();
                $snapshotResolution = $resolutionNote;
            } else {
                $locked->resolved_at = null;

                if ($from === 'traite') {
                    // Le compte rendu quitte l'incident mais reste dans l'historique.
                    $snapshotResolution = $locked->resolution_note;
                    $locked->resolution_note = null;
                }

                if ($to === 'rejete') {
                    $locked->resolution_note = null;
                    $locked->rejection_reason = $rejectionReason;
                    $snapshotRejection = $rejectionReason;
                } elseif ($from === 'rejete') {
                    $snapshotRejection = $locked->rejection_reason;
                    $locked->rejection_reason = null;
                }
            }

            $locked->status = $to;
            $locked->save();

            $history = $locked->statusHistories()->create([
                'user_id' => $actor->id,
                'ancien_statut' => $from,
                'nouveau_statut' => $to,
                'comment' => $comment,
                'resolution_note' => $snapshotResolution,
                'rejection_reason' => $snapshotRejection,
            ]);

            if ($from !== 'traite' && $to === 'traite') {
                $resolvedHistoryId = $history->id;
            }

            return $locked->refresh();
        }, 3);

        if ($resolvedHistoryId !== null) {
            $this->citizenNotifier->notifyResolved($updated, $resolvedHistoryId);
        }

        return $updated;
    }

    /**
     * @throws InvalidIncidentTransitionException
     */
    private function assertValidTransition(
        string $from,
        string $to,
        ?string $comment,
        ?string $resolutionNote,
        ?string $rejectionReason,
    ): void {
        if (! array_key_exists($to, Incident::STATUSES) || ! $this->canTransition($from, $to)) {
            throw InvalidIncidentTransitionException::between($from, $to);
        }

        if ($to === 'traite') {
            $this->assertNote($resolutionNote, 'compte rendu de résolution');
        }

        if ($to === 'rejete') {
            $this->assertNote($rejectionReason, 'motif de rejet');
        }

        if ($this->isReopening($from, $to)) {
            $this->assertNote($comment, 'motif de réouverture');
        }

        if ($comment !== null && mb_strlen($comment) > self::MAX_NOTE_LENGTH) {
            throw InvalidIncidentTransitionException::tooLong('commentaire', self::MAX_NOTE_LENGTH);
        }
    }

    /**
     * @throws InvalidIncidentTransitionException
     */
    private function assertNote(?string $value, string $label): void
    {
        $length = $value === null ? 0 : mb_strlen($value);

        if ($length < self::MIN_NOTE_LENGTH || $length > self::MAX_NOTE_LENGTH) {
            throw InvalidIncidentTransitionException::invalidNote(
                $label,
                self::MIN_NOTE_LENGTH,
                self::MAX_NOTE_LENGTH,
            );
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

    private function label(string $from, string $to): string
    {
        $label = Incident::STATUSES[$to] ?? ucfirst($to);

        return $this->isReopening($from, $to) ? $label.' (réouverture)' : $label;
    }
}
