<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Support\Str;

/**
 * Score local de similarité entre deux incidents.
 *
 * Le texte est normalisé (minuscules, sans accents ni ponctuation, mots vides
 * français retirés) puis comparé par indice de Jaccard. Des bonus bornés
 * récompensent la même zone, le même type, la même gravité, la proximité
 * géographique et la proximité temporelle. Le résultat est un entier de 0 à
 * 100, pondéré par config/incident_similarity.php.
 *
 * Aucun appel réseau n'est effectué ici : le score est entièrement
 * déterministe et reproductible.
 */
class SimilarityScorer
{
    /**
     * @var array<string, int>
     */
    private const SEVERITY_RANK = [
        'faible' => 1,
        'moyenne' => 2,
        'haute' => 3,
        'critique' => 4,
    ];

    /**
     * Mots vides français, sous forme normalisée (sans accents).
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'a', 'ai', 'as', 'au', 'aux', 'avec', 'avons', 'avez', 'avoir',
        'bien', 'car', 'ce', 'cet', 'cette', 'ces', 'chez', 'comme',
        'dans', 'de', 'deja', 'depuis', 'des', 'donc', 'dont', 'du',
        'elle', 'elles', 'en', 'encore', 'entre', 'es', 'est', 'et',
        'etaient', 'etait', 'ete', 'etre', 'eu', 'fait', 'faire', 'fois',
        'hier', 'ici', 'il', 'ils', 'je', 'jour', 'jours', 'la', 'le',
        'les', 'leur', 'leurs', 'lors', 'lorsque', 'ma', 'mais', 'mal',
        'matin', 'mes', 'minute', 'minutes', 'mon', 'ne', 'nous', 'notre',
        'nos', 'nuit', 'on', 'ont', 'ou', 'oui', 'par', 'pas', 'peut',
        'peuvent', 'plus', 'pour', 'pouvoir', 'quand', 'que', 'qui',
        'quoi', 'sa', 'sans', 'sera', 'serait', 'seront', 'ses', 'si',
        'soir', 'sommes', 'son', 'sont', 'sous', 'suis', 'sur', 'ta',
        'tes', 'ton', 'tous', 'tout', 'toute', 'toutes', 'tres', 'tu',
        'un', 'une', 'vers', 'votre', 'vos', 'vous', 'y',
    ];

    public function score(Incident $incident, Incident $candidate): int
    {
        $text = $this->textSimilarity(
            $incident->title.' '.$incident->description,
            $candidate->title.' '.$candidate->description,
        );

        $score = $text * $this->weight('text')
            + $this->zoneScore($incident, $candidate) * $this->weight('zone')
            + $this->typeScore($incident, $candidate) * $this->weight('type')
            + $this->severityScore($incident, $candidate) * $this->weight('severity')
            + $this->geoScore($incident, $candidate) * $this->weight('geography')
            + $this->timeScore($incident, $candidate) * $this->weight('time');

        return (int) max(0, min(100, round($score)));
    }

    /**
     * Détail des points pondérés, pour l'audit et la démonstration.
     * Le total provient de score() et reste donc strictement identique.
     *
     * @return array{
     *     text_similarity: float,
     *     text: float,
     *     zone: float,
     *     type: float,
     *     severity: float,
     *     geography: float,
     *     time: float,
     *     total: int
     * }
     */
    public function explain(Incident $incident, Incident $candidate): array
    {
        $textSimilarity = $this->textSimilarity(
            $incident->title.' '.$incident->description,
            $candidate->title.' '.$candidate->description,
        );

        return [
            'text_similarity' => $textSimilarity,
            'text' => round($textSimilarity * $this->weight('text'), 2),
            'zone' => round($this->zoneScore($incident, $candidate) * $this->weight('zone'), 2),
            'type' => round($this->typeScore($incident, $candidate) * $this->weight('type'), 2),
            'severity' => round($this->severityScore($incident, $candidate) * $this->weight('severity'), 2),
            'geography' => round($this->geoScore($incident, $candidate) * $this->weight('geography'), 2),
            'time' => round($this->timeScore($incident, $candidate) * $this->weight('time'), 2),
            'total' => $this->score($incident, $candidate),
        ];
    }

    public function textSimilarity(string $textA, string $textB): float
    {
        $tokensA = $this->tokens($textA);
        $tokensB = $this->tokens($textB);

        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        if ($union === 0) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));

        return $intersection / $union;
    }

    /**
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';

        $tokens = preg_split('/\s+/', trim($normalized)) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token) => mb_strlen($token) >= 2
                && ! in_array($token, self::STOPWORDS, true),
        )));
    }

    public function distanceKm(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        $earth = 6371.0;
        $deltaLatitude = deg2rad($latitudeTo - $latitudeFrom);
        $deltaLongitude = deg2rad($longitudeTo - $longitudeFrom);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * sin($deltaLongitude / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    private function zoneScore(Incident $incident, Incident $candidate): float
    {
        if ($incident->zone_id !== null && $incident->zone_id === $candidate->zone_id) {
            return 1.0;
        }

        if ($incident->zone_id === null
            && $candidate->zone_id === null
            && $incident->infrastructure_id !== null
            && $incident->infrastructure_id === $candidate->infrastructure_id) {
            return 1.0;
        }

        return 0.0;
    }

    private function typeScore(Incident $incident, Incident $candidate): float
    {
        return $incident->type !== null && $incident->type === $candidate->type ? 1.0 : 0.0;
    }

    private function severityScore(Incident $incident, Incident $candidate): float
    {
        $rankA = self::SEVERITY_RANK[$incident->severity] ?? null;
        $rankB = self::SEVERITY_RANK[$candidate->severity] ?? null;

        if ($rankA === null || $rankB === null) {
            return 0.0;
        }

        $distance = abs($rankA - $rankB);

        return match (true) {
            $distance === 0 => 1.0,
            $distance === 1 => 0.4,
            default => 0.0,
        };
    }

    private function geoScore(Incident $incident, Incident $candidate): float
    {
        if (! is_numeric($incident->latitude) || ! is_numeric($incident->longitude)
            || ! is_numeric($candidate->latitude) || ! is_numeric($candidate->longitude)) {
            return 0.0;
        }

        $distance = $this->distanceKm(
            (float) $incident->latitude,
            (float) $incident->longitude,
            (float) $candidate->latitude,
            (float) $candidate->longitude,
        );

        return match (true) {
            $distance <= 0.5 => 1.0,
            $distance <= 2.0 => 0.6,
            $distance <= 5.0 => 0.3,
            default => 0.0,
        };
    }

    private function timeScore(Incident $incident, Incident $candidate): float
    {
        if ($incident->reported_at === null || $candidate->reported_at === null) {
            return 0.0;
        }

        $hours = abs($incident->reported_at->getTimestamp() - $candidate->reported_at->getTimestamp()) / 3600;
        $windowHours = max(1, (int) config('incident_similarity.window_days', 30)) * 24;

        return match (true) {
            $hours <= 24 => 1.0,
            $hours <= 72 => 0.7,
            $hours <= 168 => 0.4,
            $hours <= $windowHours => 0.1,
            default => 0.0,
        };
    }

    private function weight(string $key): float
    {
        return (float) config('incident_similarity.weights.'.$key, 0);
    }
}
