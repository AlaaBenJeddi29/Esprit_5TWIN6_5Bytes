<?php

namespace App\Services;

use App\Models\Incident\Incident;
use Illuminate\Support\Str;

/**
 * Classification locale et déterministe des incidents.
 *
 * Aucun fournisseur d'IA n'est appelé : il s'agit d'une analyse par mots-clés,
 * explicitement présentée comme une aide heuristique dans l'interface.
 */
class IncidentClassifier
{
    /**
     * Mots-clés par catégorie (minuscules, sans accents stricts).
     *
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
        'fuite' => ['fuite', 'ecoulement', 'écoulement', 'rupture', 'perce', 'percée', 'coule', 'canalisation cassee', 'canalisation cassée', 'jet d eau', 'jet d\'eau'],
        'coupure' => ['coupure', 'pas d eau', 'pas d\'eau', 'robinet sec', 'interruption', 'plus d eau', 'plus d\'eau', 'privé d eau', 'privé d\'eau'],
        'qualite' => ['odeur', 'couleur', 'trouble', 'troubles', 'gout', 'goût', 'marron', 'brun', 'contamine', 'contaminé', 'boue', 'sale', 'mousse'],
        'pression' => ['pression', 'debit faible', 'débit faible', 'filet d eau', 'filet d\'eau', 'faible debit', 'faible débit'],
        'refoulement' => ['refoulement', 'inondation', 'inonde', 'inondé', 'debordement', 'débordement', 'egout', 'égout', 'cave inondee', 'cave inondée'],
    ];

    /**
     * @var list<string>
     */
    private const CRITICAL_KEYWORDS = ['inondation', 'inondé', 'effondrement', 'route', 'hôpital', 'hopital', 'école', 'ecole', 'immeuble', 'danger'];

    /**
     * @param  array{title?: string, description?: string, type?: string}  $data
     * @return array{type: string, severity: string, priority: string, confidence: float, summary: string}
     */
    public function classify(array $data): array
    {
        $text = Str::lower(trim(($data['title'] ?? '').' '.($data['description'] ?? '')));
        $haystack = $this->normalize($text);

        $requestedType = $data['type'] ?? null;
        $scores = [];

        foreach (self::KEYWORDS as $type => $keywords) {
            $scores[$type] = 0;

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $this->normalize($keyword))) {
                    $scores[$type]++;
                }
            }
        }

        arsort($scores);
        $bestType = array_key_first($scores);
        $bestScore = $scores[$bestType] ?? 0;

        $type = $bestScore > 0 ? $bestType : (is_string($requestedType) && array_key_exists($requestedType, Incident::TYPES) ? $requestedType : 'autre');

        $severity = $this->severity($haystack, $type, $bestScore);
        $priority = match ($severity) {
            'critique' => 'urgente',
            'haute' => 'haute',
            'faible' => 'basse',
            default => 'normale',
        };

        $confidence = $bestScore > 0
            ? min(0.95, 0.6 + ($bestScore * 0.1))
            : 0.45;

        $summary = sprintf(
            'Classification heuristique : « %s », gravité %s, priorité %s. %s',
            Incident::TYPES[$type] ?? $type,
            Incident::SEVERITIES[$severity],
            Incident::PRIORITIES[$priority],
            $bestScore > 0
                ? "Détection basée sur {$bestScore} mot(s)-clé(s) significatif(s)."
                : 'Aucun mot-clé discriminant ; catégorie déclarée par le citoyen conservée.',
        );

        return [
            'type' => $type,
            'severity' => $severity,
            'priority' => $priority,
            'confidence' => round($confidence, 2),
            'summary' => $summary,
        ];
    }

    private function severity(string $haystack, string $type, int $matches): string
    {
        foreach (self::CRITICAL_KEYWORDS as $keyword) {
            if (str_contains($haystack, $this->normalize($keyword))) {
                return 'critique';
            }
        }

        if ($type === 'refoulement') {
            return 'haute';
        }

        if ($type === 'fuite' && $matches >= 2) {
            return 'haute';
        }

        if (str_contains($haystack, 'legere') || str_contains($haystack, 'léger') || str_contains($haystack, 'goutte')) {
            return 'faible';
        }

        return match ($type) {
            'coupure', 'qualite' => 'moyenne',
            'fuite' => 'moyenne',
            default => 'moyenne',
        };
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }
}
