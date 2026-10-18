<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Premier compte administrateur
    |--------------------------------------------------------------------------
    |
    | Identifiants utilisés par AdminUserSeeder pour créer le tout premier
    | compte administrateur. Aucune valeur réelle n'est codée en dur : si ces
    | variables d'environnement sont absentes, le seeder s'arrête proprement
    | sans rien créer. Renseignez-les localement (jamais commitées).
    |
    */

    'admin' => [
        'name' => env('AQUASECURE_ADMIN_NAME', 'Administrateur'),
        'email' => env('AQUASECURE_ADMIN_EMAIL'),
        'password' => env('AQUASECURE_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Comptes de démonstration
    |--------------------------------------------------------------------------
    |
    | Utilisés uniquement par DemoUsersSeeder, qui doit être lancé
    | explicitement en local. Aucun mot de passe n'est codé en dur : si
    | AQUASECURE_DEMO_PASSWORD ou l'un des emails est absent, le seeder ne
    | crée aucun compte. Le seeder refuse de s'exécuter en production.
    |
    */

    'incident_map' => [
        /*
        | Nombre maximal de marqueurs renvoyés par l'endpoint JSON. Les
        | compteurs restent calculés sur l'ensemble filtré ; ce plafond
        | protège uniquement le rendu côté navigateur.
        */
        'max_markers' => env('AQUASECURE_INCIDENT_MAP_MAX_MARKERS', 2000),
    ],

    'demo' => [
        'password' => env('AQUASECURE_DEMO_PASSWORD'),
        'users' => [
            'admin' => [
                'name' => env('AQUASECURE_DEMO_ADMIN_NAME', 'Admin AquaSecure'),
                'email' => env('AQUASECURE_DEMO_ADMIN_EMAIL'),
            ],
            'gestionnaire' => [
                'name' => env('AQUASECURE_DEMO_GESTIONNAIRE_NAME', 'Gestionnaire AquaSecure'),
                'email' => env('AQUASECURE_DEMO_GESTIONNAIRE_EMAIL'),
            ],
            'citoyen' => [
                'name' => env('AQUASECURE_DEMO_CITOYEN_NAME', 'Citoyen Démo'),
                'email' => env('AQUASECURE_DEMO_CITOYEN_EMAIL'),
            ],
        ],
    ],

];
