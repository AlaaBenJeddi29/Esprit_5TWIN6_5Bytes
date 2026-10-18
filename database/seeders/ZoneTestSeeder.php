<?php

namespace Database\Seeders;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Seeder;

/**
 * Seeder de zones de test pour la Gestion 4 (Sécheresse).
 * 
 * Crée 5 zones prêtes à l'emploi pour remplir les formulaires
 * d'exportation (niveaux, consommation, restrictions, coupures).
 */
class ZoneTestSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'nom' => 'Centre-Ville',
                'commune' => 'Tunis',
                'code_postal' => '1000',
                'population' => 150000,
                'latitude' => 36.7869,
                'longitude' => 10.1754,
            ],
            [
                'nom' => 'Banlieue Nord',
                'commune' => 'Ariana',
                'code_postal' => '2080',
                'population' => 85000,
                'latitude' => 36.8621,
                'longitude' => 10.1947,
            ],
            [
                'nom' => 'Banlieue Sud',
                'commune' => 'Ben Arous',
                'code_postal' => '2013',
                'population' => 120000,
                'latitude' => 36.7344,
                'longitude' => 10.2239,
            ],
            [
                'nom' => 'Zone Côtière',
                'commune' => 'La Marsa',
                'code_postal' => '2070',
                'population' => 45000,
                'latitude' => 36.8626,
                'longitude' => 10.3234,
            ],
            [
                'nom' => 'Zone Industrielle',
                'commune' => 'Rades',
                'code_postal' => '2040',
                'population' => 65000,
                'latitude' => 36.7284,
                'longitude' => 10.3189,
            ],
        ];

        foreach ($zones as $zone) {
            Zone::firstOrCreate(
                ['nom' => $zone['nom']],
                $zone
            );
        }

        $this->command?->info('5 zones de test créées/actualisées avec succès.');
    }
}
