<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the demo dataset.
     *
     * Les seeders sont appelés dans l'ordre des dépendances :
     * utilisateurs et zones référentielles d'abord, puis infrastructures,
     * maintenances et enfin les jeux de données qui référencent ces entités.
     * Chaque seeder s'auto-protège et s'arrête sans erreur si sa configuration
     * est absente ou en production.
     */
    public function run(): void
    {
        $this->call([
            // Utilisateurs (comptes de démonstration et administrateur)
            AdminUserSeeder::class,
            DemoUsersSeeder::class,

            // Référentiels : zones puis infrastructures rattachées aux zones
            ZoneSeeder::class,
            InfrastructureSeeder::class,

            // Maintenances : dépendent des infrastructures et d'un utilisateur back-office
            MaintenanceSeeder::class,

            // Jeu de données complet toutes gestions (projets, incidents, qualité, sécheresse)
            DemoDataSeeder::class,

            // Signalements de démonstration
            ReportSeeder::class,

            // Jeux de démonstration IA et détection d'incidents similaires
            // (références stables, idempotents, dépendent des zones et infrastructures)
            IncidentAiDemoSeeder::class,
            IncidentSimilarityDemoSeeder::class,

            // Données de test de la gestion sécheresse (dépendent des zones)
            ZoneTestSeeder::class,
            DroughtTestDataSeeder::class,
            ConsumptionHistorySeeder::class,
        ]);
    }
}
