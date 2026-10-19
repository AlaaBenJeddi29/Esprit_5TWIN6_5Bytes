<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Détection des incidents similaires
    |--------------------------------------------------------------------------
    |
    | Ces valeurs pilotent la présélection SQL des candidats et le score local.
    | Aucune suggestion n'est appliquée automatiquement : un gestionnaire doit
    | toujours valider ou rejeter la suggestion.
    |
    */

    'window_days' => max(1, (int) env('INCIDENT_SIMILARITY_WINDOW_DAYS', 30)),

    'max_candidates' => max(1, (int) env('INCIDENT_SIMILARITY_MAX_CANDIDATES', 50)),

    'min_score' => max(0, min(100, (int) env('INCIDENT_SIMILARITY_MIN_SCORE', 60))),

    'max_suggestions' => max(1, (int) env('INCIDENT_SIMILARITY_MAX_SUGGESTIONS', 5)),

    /*
    | Seuil à partir duquel une zone est signalée comme problème récurrent
    | (nombre d'incidents similaires dans la même zone sur la période).
    */
    'recurring_threshold' => max(2, (int) env('INCIDENT_SIMILARITY_RECURRING_THRESHOLD', 3)),

    /*
    |--------------------------------------------------------------------------
    | Pondérations du score (total = 100)
    |--------------------------------------------------------------------------
    |
    | Le score final est la somme des sous-scores normalisés (0 à 1) multipliés
    | par leur pondération. La similarité textuelle reste le critère dominant.
    |
    */

    'weights' => [
        'text' => 50,
        'zone' => 15,
        'type' => 10,
        'severity' => 5,
        'geography' => 10,
        'time' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation IA optionnelle
    |--------------------------------------------------------------------------
    |
    | Désactivée par défaut. L'appel n'est effectué que si l'indicateur est
    | actif ET qu'une clé API est configurée (voir config/services.php).
    | En cas d'échec, de timeout ou de réponse invalide, le score local est
    | conservé sans bloquer la détection.
    |
    */

    'ai_enabled' => (bool) env('INCIDENT_SIMILARITY_AI_ENABLED', false),

    'ai_max_candidates' => 5,

];
