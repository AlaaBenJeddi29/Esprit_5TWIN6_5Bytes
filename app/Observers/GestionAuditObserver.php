<?php

namespace App\Observers;

use App\Models\Project\AuditEvent;
use App\Models\Project\Funding;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Project\ProjectPhase;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Piste d'audit Gestion 5 : journalise créations, modifications et
 * suppressions des projets, phases, financements et documents. L'acteur
 * est toujours lu côté serveur (session authentifiée) — jamais un champ
 * de formulaire. Les résumés ne contiennent que des métadonnées sûres :
 * jamais de contenu de fichier ni de données personnelles superflues.
 */
class GestionAuditObserver
{
    /** @var array<class-string<Model>, string> */
    private const SUBJECT_TYPES = [
        Project::class => 'project',
        ProjectPhase::class => 'phase',
        Funding::class => 'funding',
        ProjectDocument::class => 'document',
    ];

    /** Champs techniques exclus des résumés de création par modèle. */
    private const HIDDEN_ON_CREATE = [
        ProjectDocument::class => ['chemin_fichier'],
    ];

    public function created(Model $model): void
    {
        $subject = $this->subjectType($model);

        if ($subject === null) {
            return;
        }

        $hidden = self::HIDDEN_ON_CREATE[$model::class] ?? [];

        $summary = $this->attributesSummary($model, $hidden);

        $this->record($model, $subject.'.created', $summary !== '' ? $summary : $this->label($model), $this->projectLink($model));
    }

    public function updated(Model $model): void
    {
        $subject = $this->subjectType($model);

        if ($subject === null) {
            return;
        }

        // Seules les colonnes réellement modifiées (hors updated_at).
        $changes = collect($model->getChanges())->except('updated_at');

        if ($changes->isEmpty()) {
            return;
        }

        $lines = [];

        foreach ($changes as $field => $raw) {
            if ($model instanceof ProjectDocument && $field === 'chemin_fichier') {
                // Un remplacement de fichier ne doit pas fuiter les chemins
                // internes de stockage : on note le fait, pas la valeur.
                $lines[] = 'fichier : remplacé';

                continue;
            }

            // getAttribute applique les casts (dates en Carbon, etc.).
            $lines[] = $field.' : '.$this->formatValue($model->getOriginal($field)).' → '.$this->formatValue($model->getAttribute($field));
        }

        if ($lines === []) {
            return;
        }

        $this->record($model, $subject.'.updated', implode(' ; ', $lines), $this->projectLink($model));
    }

    public function deleted(Model $model): void
    {
        $subject = $this->subjectType($model);

        if ($subject === null) {
            return;
        }

        // Le projet vient d'être supprimé : la ligne d'audit ne peut plus le
        // référencer (clé étrangère). Les enfants gardent leur lien projet.
        $projectId = $model instanceof Project ? null : $model->getAttribute('project_id');

        $this->record($model, $subject.'.deleted', 'Suppression de '.$this->label($model), $projectId);
    }

    private function record(Model $model, string $action, string $summary, ?int $projectId): void
    {
        $actor = auth()->id();

        // Aucun acteur authentifié (seeders, console) : rien à journaliser.
        if ($actor === null) {
            return;
        }

        AuditEvent::create([
            'user_id' => $actor,
            'action' => $action,
            'subject_type' => $this->subjectType($model),
            'subject_id' => $model->getKey(),
            'project_id' => $projectId,
            'summary' => mb_substr($summary, 0, 1000),
        ]);
    }

    private function projectLink(Model $model): ?int
    {
        return $model instanceof Project
            ? $model->getKey()
            : $model->getAttribute('project_id');
    }

    private function subjectType(Model $model): ?string
    {
        return self::SUBJECT_TYPES[$model::class] ?? null;
    }

    /** Libellé concis et sûr de l'entité (jamais de contenu de fichier). */
    private function label(Model $model): string
    {
        if ($model instanceof Project) {
            return 'Projet « '.$model->titre.' »';
        }

        if ($model instanceof ProjectPhase) {
            return 'Phase « '.$model->nom.' »';
        }

        if ($model instanceof Funding) {
            return 'Financement '.$model->sourceLabel().' de '.$this->formatValue($model->montant).' €';
        }

        if ($model instanceof ProjectDocument) {
            return 'Document « '.$model->titre.' »';
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /** Résumé de création : valeurs des attributs initiaux, formatées. */
    private function attributesSummary(Model $model, array $hidden): string
    {
        return Collection::make($model->getAttributes())
            ->except(array_merge(['id', 'created_at', 'updated_at'], $hidden))
            ->map(fn ($value, $field) => $field.' : '.$this->formatValue($model->getAttribute($field)))
            ->implode(' ; ');
    }

    private function formatValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('d/m/Y');
        }

        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        if ($value === null) {
            return '—';
        }

        if (is_float($value)) {
            return number_format($value, 2, ',', ' ');
        }

        return (string) $value;
    }
}
