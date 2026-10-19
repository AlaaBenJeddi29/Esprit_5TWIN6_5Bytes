<?php

namespace App\Exceptions;

use App\Models\Incident\Incident;
use DomainException;

/**
 * Transition de statut refusée par le workflow des incidents.
 */
class InvalidIncidentTransitionException extends DomainException
{
    public static function between(string $from, string $to): self
    {
        return new self(sprintf(
            'Transition impossible : « %s » → « %s ».',
            Incident::STATUSES[$from] ?? $from,
            Incident::STATUSES[$to] ?? $to,
        ));
    }

    public static function invalidNote(string $label, int $min, int $max): self
    {
        return new self(sprintf(
            'Le %s doit contenir entre %d et %d caractères (espaces de début et de fin exclus).',
            $label,
            $min,
            $max,
        ));
    }

    public static function tooLong(string $label, int $max): self
    {
        return new self(sprintf('Le %s ne peut pas dépasser %d caractères.', $label, $max));
    }

    public static function actorRequired(): self
    {
        return new self('Un utilisateur authentifié est requis pour changer le statut d\'un incident.');
    }
}
