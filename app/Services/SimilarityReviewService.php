<?php

namespace App\Services;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Application d'une décision de gestionnaire sur une suggestion.
 *
 * Chaque décision est atomique et verrouille la suggestion : une seconde
 * décision sur la même ligne est refusée. Le statut de l'incident n'est jamais
 * modifié ici ; seule la marque de doublon existante (is_duplicate /
 * duplicate_of_id) est renseignée pour une décision « doublon ».
 */
class SimilarityReviewService
{
    /**
     * @throws ValidationException
     */
    public function decide(
        IncidentSimilarity $similarity,
        string $decision,
        ?string $note,
        User $reviewer,
    ): IncidentSimilarity {
        return DB::transaction(function () use ($similarity, $decision, $note, $reviewer): IncidentSimilarity {
            $locked = IncidentSimilarity::query()
                ->whereKey($similarity->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'decision' => 'Cette suggestion n\'existe plus.',
                ]);
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'decision' => 'Cette suggestion a déjà été traitée.',
                ]);
            }

            $targetStatus = IncidentSimilarity::DECISIONS[$decision] ?? null;

            if ($targetStatus === null) {
                throw ValidationException::withMessages([
                    'decision' => 'La décision demandée est invalide.',
                ]);
            }

            if ($decision === 'doublon') {
                $this->markAsDuplicate($locked);
            }

            $locked->fill([
                'status' => $targetStatus,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'decision_note' => $note === null || trim($note) === '' ? null : trim($note),
            ])->save();

            return $locked->refresh();
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    private function markAsDuplicate(IncidentSimilarity $similarity): void
    {
        $sourceId = (int) $similarity->incident_id;
        $targetId = (int) $similarity->similar_incident_id;

        if ($sourceId === $targetId) {
            throw ValidationException::withMessages([
                'decision' => 'Un incident ne peut pas être marqué comme doublon de lui-même.',
            ]);
        }

        $incidents = Incident::query()
            ->whereIn('id', [$sourceId, $targetId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        /** @var Incident|null $source */
        $source = $incidents->get($sourceId);

        /** @var Incident|null $target */
        $target = $incidents->get($targetId);

        if ($source === null || $target === null) {
            throw ValidationException::withMessages([
                'decision' => 'L\'incident source ou l\'incident cible n\'existe plus.',
            ]);
        }

        if ($source->is_duplicate && (int) $source->duplicate_of_id !== $targetId) {
            throw ValidationException::withMessages([
                'decision' => 'Cet incident est déjà marqué comme doublon d\'un autre signalement.',
            ]);
        }

        if ($this->wouldCreateDuplicateCycle($sourceId, $target)) {
            throw ValidationException::withMessages([
                'decision' => 'Cette décision créerait un cycle de doublons entre signalements.',
            ]);
        }

        if ($target->is_duplicate) {
            throw ValidationException::withMessages([
                'decision' => 'L\'incident cible est déjà marqué comme doublon : une chaîne de doublons serait créée.',
            ]);
        }

        if ($this->isDuplicateTarget($sourceId)) {
            throw ValidationException::withMessages([
                'decision' => 'Cet incident est déjà la cible d\'un autre signalement marqué comme doublon : une chaîne incohérente serait créée.',
            ]);
        }

        $source->fill([
            'is_duplicate' => true,
            'duplicate_of_id' => $targetId,
        ])->save();
    }

    private function wouldCreateDuplicateCycle(int $sourceId, Incident $target): bool
    {
        $visited = [];
        $current = $target;

        while ($current !== null) {
            $currentId = (int) $current->getKey();

            if ($currentId === $sourceId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return false;
            }

            $visited[$currentId] = true;

            if (! $current->is_duplicate || $current->duplicate_of_id === null) {
                return false;
            }

            $current = Incident::query()
                ->whereKey($current->duplicate_of_id)
                ->first();
        }

        return false;
    }

    private function isDuplicateTarget(int $incidentId): bool
    {
        return Incident::query()
            ->where('is_duplicate', true)
            ->where('duplicate_of_id', $incidentId)
            ->exists();
    }
}
