<?php

namespace App\Services;

use App\Models\Project\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Assistant projet local : répond à des questions prédéfinies à partir des
 * données réelles du projet. Aucun fournisseur d'IA n'est connecté.
 */
class ProjectAssistant
{
    /**
     * @return list<string>
     */
    public function suggestions(): array
    {
        return [
            'Quel est le budget et son financement ?',
            'Où en est l\'avancement du projet ?',
            'Quelles sont les échéances prévues ?',
            'Quelles entreprises interviennent ?',
            'Quels documents sont disponibles ?',
            'Quels sont les risques identifiés ?',
        ];
    }

    public function answer(Project $project, string $question): string
    {
        $question = Str::lower(Str::ascii($question));

        $project->loadMissing(['zone', 'infrastructure', 'projectPhases.contractor', 'fundings', 'projectDocuments']);

        return match (true) {
            str_contains($question, 'budget'),
            str_contains($question, 'cout'),
            str_contains($question, 'finance'),
            str_contains($question, 'fonds'),
            str_contains($question, 'don') => $this->fundingAnswer($project),
            str_contains($question, 'avancement'),
            str_contains($question, 'etape'),
            str_contains($question, 'phase') => $this->progressAnswer($project),
            str_contains($question, 'delai'),
            str_contains($question, 'date'),
            str_contains($question, 'echeance'),
            str_contains($question, 'retard') => $this->timelineAnswer($project),
            str_contains($question, 'entreprise'),
            str_contains($question, 'prestataire'),
            str_contains($question, 'contractant') => $this->contractorAnswer($project),
            str_contains($question, 'document') => $this->documentAnswer($project),
            str_contains($question, 'risque') => $this->riskAnswer($project),
            str_contains($question, 'statut'),
            str_contains($question, 'etat') => $this->statusAnswer($project),
            default => $this->summaryAnswer($project),
        };
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', ' ').' €';
    }

    private function fundingAnswer(Project $project): string
    {
        $budget = (float) $project->budget_prevu;
        $fonds = $project->fundingTotal();
        $rate = $project->fundingRate();
        $reste = max(0, $budget - $fonds);

        return sprintf(
            'Le budget prévu est de %s. Les financements enregistrés totalisent %s, soit %.1f %% du budget. Reste à financer : %s.',
            $this->money($budget),
            $this->money($fonds),
            $rate,
            $this->money($reste),
        );
    }

    private function progressAnswer(Project $project): string
    {
        $phases = $project->projectPhases;
        $terminees = $phases->filter(fn ($phase) => $phase->isCompleted())->count();

        return sprintf(
            'L\'avancement déclaré est de %d %%. %d phase(s) sur %d sont terminées.',
            (int) $project->avancement_pourcentage,
            $terminees,
            $phases->count(),
        );
    }

    private function timelineAnswer(Project $project): string
    {
        $debut = $project->date_debut?->translatedFormat('d/m/Y') ?? 'non renseignée';
        $fin = $project->date_fin_prevue?->translatedFormat('d/m/Y') ?? 'non renseignée';
        $retard = '';

        if ($project->date_fin_prevue && $project->date_fin_prevue->isPast() && $project->statut !== 'terminé') {
            $jours = Carbon::now()->diffInDays($project->date_fin_prevue);
            $retard = sprintf(' Le projet a dépassé son échéance de %d jour(s).', $jours);
        }

        return "Début : {$debut}. Fin prévue : {$fin}.{$retard}";
    }

    private function contractorAnswer(Project $project): string
    {
        $contractors = $project->projectPhases
            ->pluck('contractor')
            ->filter()
            ->unique('id')
            ->map(fn ($contractor) => $contractor->nom)
            ->values();

        if ($contractors->isEmpty()) {
            return 'Aucune entreprise n\'est encore affectée aux phases de ce projet.';
        }

        return 'Entreprises intervenantes : '.$contractors->implode(', ').'.';
    }

    private function documentAnswer(Project $project): string
    {
        $total = $project->projectDocuments->count();
        $publics = $project->projectDocuments->where('est_public', true)->count();

        return sprintf(
            '%d document(s) sont attachés au projet, dont %d consultable(s) publiquement.',
            $total,
            $publics,
        );
    }

    private function riskAnswer(Project $project): string
    {
        $risques = [];

        if ($project->fundingRate() < 70) {
            $risques[] = 'financement inférieur à 70 % du budget';
        }

        if ($project->statut === 'suspendu') {
            $risques[] = 'projet suspendu';
        }

        if ($project->date_fin_prevue && $project->date_fin_prevue->isPast() && $project->statut !== 'terminé') {
            $risques[] = 'échéance dépassée';
        }

        if ((int) $project->avancement_pourcentage < 30 && $project->date_debut?->lt(now()->subMonths(6))) {
            $risques[] = 'avancement faible pour un projet démarré depuis plus de six mois';
        }

        if ($risques === []) {
            return 'Aucun risque majeur n\'est identifié à partir des données disponibles.';
        }

        return 'Risques identifiés : '.implode(' ; ', $risques).'.';
    }

    private function statusAnswer(Project $project): string
    {
        return "Le projet est actuellement « {$project->statutLabel()} », avec un avancement de {$project->avancement_pourcentage} %.";
    }

    private function summaryAnswer(Project $project): string
    {
        return sprintf(
            '%s — type %s, statut %s, avancement %d %%, budget %s, zone %s. Posez une question sur le budget, l\'avancement, les délais, les entreprises ou les risques.',
            $project->titre,
            $project->type,
            $project->statutLabel(),
            (int) $project->avancement_pourcentage,
            $this->money((float) $project->budget_prevu),
            $project->zone?->nom ?? 'non renseignée',
        );
    }
}
