<?php

namespace App\Services;

use App\Models\Project\Project;

/**
 * Indicateurs consolidés d'un projet, calculés côté serveur à partir des
 * données réelles — jamais via un LLM. Réutilisable dans le Back Office,
 * le Front Office et l'assistant.
 *
 * Trois notions financières distinctes :
 * - budgetPrevu     : budget planifié du projet (projects.budget_prevu) ;
 * - phasesCostTotal : somme des coûts déclarés des phases — une estimation
 *                     de travaux, PAS une dépense réelle constatée ;
 * - fundingTotal    : financements réellement enregistrés (versés).
 */
class ProjectMetrics
{
    public readonly float $budgetPrevu;

    public readonly float $phasesCostTotal;

    public readonly float $fundingTotal;

    /** Reste à financer : jamais négatif (un éventuel excédent devient du sur-financement). */
    public readonly float $fundingGap;

    public readonly float $fundingRate;

    /** Montant du sur-financement (0 tant que le budget prévu n'est pas dépassé). */
    public readonly float $overFunding;

    public readonly bool $overFunded;

    public readonly int $phaseCount;

    public readonly int $overduePhaseCount;

    public readonly string $deadlineStatus;

    public function __construct(Project $project)
    {
        $this->budgetPrevu = round((float) $project->budget_prevu, 2);
        $this->phasesCostTotal = round($project->budgetTotal(), 2);
        $this->fundingTotal = round($project->fundingTotal(), 2);

        $this->fundingGap = round(max(0.0, $this->budgetPrevu - $this->fundingTotal), 2);
        $this->overFunded = $this->budgetPrevu > 0.0 && $this->fundingTotal > $this->budgetPrevu;
        $this->overFunding = $this->overFunded
            ? round($this->fundingTotal - $this->budgetPrevu, 2)
            : 0.0;

        $this->fundingRate = round($project->fundingRate(), 1);

        $this->phaseCount = $project->projectPhases()->count();
        // Phases datées, incomplètes et échues. Un projet annulé n'a plus
        // d'obligation d'échéance : ses phases ne sont plus « en retard ».
        $this->overduePhaseCount = $project->statut === 'annulé'
            ? 0
            : $project->projectPhases()
                ->whereNotNull('date_fin')
                ->whereDate('date_fin', '<', now()->toDateString())
                ->where('avancement', '<', 100)
                ->count();

        $this->deadlineStatus = ProjectDeadlineIndicators::projectStatus($project);
    }
}
