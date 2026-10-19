<?php

namespace App\Exceptions;

use App\Models\Incident\IncidentFeedback;
use RuntimeException;

/**
 * Transition de réclamation refusée par le workflow dédié.
 */
class InvalidComplaintTransitionException extends RuntimeException
{
    public static function between(?string $from, string $to): self
    {
        $fromLabel = $from === null
            ? 'aucune réclamation'
            : (IncidentFeedback::COMPLAINT_STATUSES[$from] ?? $from);

        return new self(sprintf(
            'Transition de réclamation non autorisée de « %s » vers « %s ».',
            $fromLabel,
            IncidentFeedback::COMPLAINT_STATUSES[$to] ?? $to,
        ));
    }

    public static function noComplaint(): self
    {
        return new self('Cette évaluation ne contient aucune réclamation à traiter.');
    }

    public static function justificationRequired(): self
    {
        return new self('Une justification est obligatoire pour accepter ou refuser une réclamation.');
    }

    public static function reopenNotAllowed(): self
    {
        return new self('La réouverture n\'est pas autorisée pour cette réclamation.');
    }

    public static function alreadyReopened(): self
    {
        return new self('L\'incident a déjà été réouvert à partir de cette réclamation.');
    }
}
