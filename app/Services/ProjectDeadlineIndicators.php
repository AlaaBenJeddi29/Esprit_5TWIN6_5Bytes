<?php

namespace App\Services;

use App\Models\Project\Project;
use App\Models\Project\ProjectPhase;
use Illuminate\Support\Carbon;

/**
 * Indicateurs d'échéance et de risque de retard — 100 % déterministes,
 * dérivés des dates réelles et des statuts stockés. Rien n'est persisté
 * (ce sont des états calculés) et aucun LLM n'est impliqué.
 *
 * Règles :
 * - projet « en retard » : fin prévue strictement passée ET projet ni
 *   terminé ni annulé ;
 * - phase « en retard » : fin strictement passée ET avancement < 100 ;
 * - « proche » : échéance aujourd'hui ou dans les APPROACHING_DAYS jours ;
 * - terminé / annulé / phase à 100 % : les alertes sont neutralisées
 *   (l'échéance devient un fait historique).
 */
class ProjectDeadlineIndicators
{
    public const ECHEANCE_DEPASSEE = 'depassee';

    public const ECHEANCE_PROCHE = 'proche';

    public const ECHEANCE_A_JOUR = 'a_jour';

    public const ECHEANCE_AUCUNE = 'aucune';

    /** Nombre de jours avant l'échéance à partir duquel elle est signalée « proche ». */
    public const APPROACHING_DAYS = 14;

    /** Statuts de projet neutralisant les alertes d'échéance. */
    private const CLOSED_PROJECT_STATUTS = ['terminé', 'annulé'];

    public static function projectStatus(Project $project): string
    {
        if (! $project->date_fin_prevue) {
            return self::ECHEANCE_AUCUNE;
        }

        if (self::projectIsClosed($project)) {
            return self::ECHEANCE_A_JOUR;
        }

        return self::calendarStatus($project->date_fin_prevue);
    }

    public static function phaseStatus(ProjectPhase $phase): string
    {
        if (! $phase->date_fin) {
            return self::ECHEANCE_AUCUNE;
        }

        // Une phase terminée (avancement >= 100, cf. isCompleted) n'a plus
        // d'échéance à surveiller, même si sa date de fin est passée.
        if ($phase->isCompleted()) {
            return self::ECHEANCE_A_JOUR;
        }

        return self::calendarStatus($phase->date_fin);
    }

    /**
     * Explication humaine de l'alerte projet, avec la date et le statut
     * qui l'ont déclenchée — null quand il n'y a rien à signaler.
     */
    public static function projectExplanation(Project $project): ?string
    {
        return match (self::projectStatus($project)) {
            self::ECHEANCE_DEPASSEE => sprintf(
                'Fin prévue le %s — échéance dépassée de %d jour(s), statut « %s ».',
                $project->date_fin_prevue->format('d/m/Y'),
                self::dayDiff($project->date_fin_prevue),
                $project->statutLabel(),
            ),
            self::ECHEANCE_PROCHE => sprintf(
                'Fin prévue le %s — échéance %s, statut « %s ».',
                $project->date_fin_prevue->format('d/m/Y'),
                self::upcomingText($project->date_fin_prevue),
                $project->statutLabel(),
            ),
            default => null,
        };
    }

    /**
     * Explication humaine de l'alerte phase, avec la date et l'avancement
     * qui l'ont déclenchée — null quand il n'y a rien à signaler.
     */
    public static function phaseExplanation(ProjectPhase $phase): ?string
    {
        return match (self::phaseStatus($phase)) {
            self::ECHEANCE_DEPASSEE => sprintf(
                'Échéance du %s dépassée de %d jour(s) — avancement %d %%.',
                $phase->date_fin->format('d/m/Y'),
                self::dayDiff($phase->date_fin),
                (int) $phase->avancement,
            ),
            self::ECHEANCE_PROCHE => sprintf(
                'Échéance du %s %s — avancement %d %%.',
                $phase->date_fin->format('d/m/Y'),
                self::upcomingText($phase->date_fin),
                (int) $phase->avancement,
            ),
            default => null,
        };
    }

    /** Projet terminé ou annulé : aucune alerte d'échéance ne doit être affichée. */
    public static function projectIsClosed(Project $project): bool
    {
        return in_array($project->statut, self::CLOSED_PROJECT_STATUTS, true);
    }

    /**
     * Statut calculé sur les dates calendaires (pas les horodatages) : une
     * échéance prévue aujourd'hui n'est pas encore « dépassée ».
     */
    private static function calendarStatus(Carbon $date): string
    {
        $day = $date->copy()->startOfDay();
        $today = today()->startOfDay();

        if ($day->lt($today)) {
            return self::ECHEANCE_DEPASSEE;
        }

        if ($day->lte($today->copy()->addDays(self::APPROACHING_DAYS))) {
            return self::ECHEANCE_PROCHE;
        }

        return self::ECHEANCE_A_JOUR;
    }

    private static function dayDiff(Carbon $date): int
    {
        return abs((int) round($date->copy()->startOfDay()->diffInDays(today()->startOfDay())));
    }

    private static function upcomingText(Carbon $date): string
    {
        $days = self::dayDiff($date);

        return $days === 0 ? "aujourd'hui" : "dans {$days} jour(s)";
    }
}
