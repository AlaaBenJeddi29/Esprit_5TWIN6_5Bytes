<?php

namespace Database\Seeders;

use App\Models\Infrastructure\Zone;
use Illuminate\Database\Seeder;

/**
 * Seeder de démonstration — Zones (Gestion 1 : Infrastructures et cartographie).
 *
 * Crée 5 zones avec des données réalistes. Ce seeder est idempotent :
 * relancer la commande actualise les enregistrements existants sans doublon.
 *
 * Utilisation :
 *   php artisan db:seed --class=ZoneSeeder
 */
class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('ZoneSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        $zones = [
            [
                'nom'        => 'Centre-Ville',
                'commune'    => 'Ville Haute',
                'code_postal'=> '75001',
                'population' => 48200,
                'latitude'   => 48.8566000,
                'longitude'  => 2.3522000,
            ],
            [
                'nom'        => 'Rives du Nord',
                'commune'    => 'Ville Haute',
                'code_postal'=> '75002',
                'population' => 36750,
                'latitude'   => 48.8701000,
                'longitude'  => 2.3612000,
            ],
            [
                'nom'        => 'Zone Industrielle Est',
                'commune'    => 'Est-sur-Loire',
                'code_postal'=> '75003',
                'population' => 12400,
                'latitude'   => 48.8425000,
                'longitude'  => 2.4110000,
            ],
            [
                'nom'        => 'Quartier Sud',
                'commune'    => 'Basse-Vallée',
                'code_postal'=> '75004',
                'population' => 22100,
                'latitude'   => 48.8300000,
                'longitude'  => 2.3400000,
            ],
            [
                'nom'        => 'Plateau Ouest',
                'commune'    => 'Ouest-Plaine',
                'code_postal'=> '75005',
                'population' => 31500,
                'latitude'   => 48.8600000,
                'longitude'  => 2.3100000,
            ],
        ];

        foreach ($zones as $donnees) {
            Zone::updateOrCreate(
                ['nom' => $donnees['nom']],
                $donnees,
            );
        }

        $this->command?->info('ZoneSeeder : 5 zones créées/actualisées.');
    }
}
