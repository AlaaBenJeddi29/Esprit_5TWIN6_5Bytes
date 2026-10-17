<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Détection locale de doublons : même zone, même catégorie et similarité
 * lexicale / proximité géographique. Aucun appel à un service d'IA externe.
 */
class DuplicateDetector
{
    public function findSimilar(Incident $incident): ?Incident
    {
        $candidates = Incident::query()
            ->whereKeyNot($incident->id)
            ->open()
            ->where('type', $incident->type)
            ->where('reported_at', '>=', Carbon::now()->subDays(30))
            ->when($incident->zone_id, fn ($query) => $query->where('zone_id', $incident->zone_id))
            ->get();

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = $this->score($incident, $candidate);

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore >= 0.55 ? $best : null;
    }

    public function score(Incident $incident, Incident $candidate): float
    {
        $textA = $this->normalize($incident->title.' '.$incident->description);
        $textB = $this->normalize($candidate->title.' '.$candidate->description);

        similar_text($textA, $textB, $percent);

        $score = $percent / 100;

        if ($incident->zone_id && $incident->zone_id === $candidate->zone_id) {
            $score += 0.15;
        }

        if ($incident->address && $candidate->address && $this->normalize($incident->address) === $this->normalize($candidate->address)) {
            $score += 0.15;
        }

        if ($incident->latitude && $incident->longitude && $candidate->latitude && $candidate->longitude) {
            $distance = $this->distanceKm(
                (float) $incident->latitude,
                (float) $incident->longitude,
                (float) $candidate->latitude,
                (float) $candidate->longitude,
            );

            if ($distance <= 0.5) {
                $score += 0.30;
            } elseif ($distance <= 2.0) {
                $score += 0.15;
            }
        }

        $hours = abs($incident->reported_at?->diffInHours($candidate->reported_at) ?? 999);

        if ($hours <= 24) {
            $score += 0.10;
        } elseif ($hours <= 72) {
            $score += 0.05;
        }

        return min(1.0, round($score, 4));
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }
}
