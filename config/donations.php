<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dons citoyens — Stripe Checkout (mode test uniquement)
    |--------------------------------------------------------------------------
    |
    | La devise de l'application est l'euro : budgets, rapports et PDF sont
    | libellés en EUR. Seules les devises listées dans supported_currencies
    | sont acceptées ; n'y ajouter qu'une devise réellement prise en charge
    | par Stripe Checkout (les montants à plus de deux décimales ne sont pas
    | gérés par ce module).
    |
    */

    'currency' => strtoupper((string) env('DONATION_CURRENCY', 'EUR')),

    'supported_currencies' => ['EUR'],

    'min_amount' => (float) env('DONATION_MIN_AMOUNT', 1),

    'max_amount' => (float) env('DONATION_MAX_AMOUNT', 5000),

];
