<?php

namespace Database\Seeders;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Seeder;

/**
 * Seeder de démonstration — Infrastructures (Gestion 1 : Infrastructures et cartographie).
 *
 * Crée des infrastructures avec des scores de risque variés (faibles, modérés,
 * élevés et critiques) dans chacune des 5 zones. Dépend de ZoneSeeder.
 *
 * Ce seeder est idempotent : relancer la commande actualise les enregistrements
 * existants sans doublon (clé naturelle : nom de l'infrastructure).
 *
 * Utilisation :
 *   php artisan db:seed --class=InfrastructureSeeder
 *   ou, avec les zones :
 *   php artisan db:seed --class=ZoneSeeder
 *   php artisan db:seed --class=InfrastructureSeeder
 */
class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('InfrastructureSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        // Charger toutes les zones indexées par leur nom pour éviter N+1
        $zones = Zone::all()->keyBy('nom');

        if ($zones->isEmpty()) {
            $this->command?->warn(
                'InfrastructureSeeder ignoré : aucune zone trouvée. '
                .'Lancez d\'abord ZoneSeeder (php artisan db:seed --class=ZoneSeeder).'
            );

            return;
        }

        /*
         * Chaque infrastructure porte un score_risque représentatif de son
         * niveau afin de couvrir les quatre paliers :
         *   Faible   : 0 – 30
         *   Modéré   : 31 – 60
         *   Élevé    : 61 – 80
         *   Critique : 81 – 100
         */
        $infrastructures = [
            // --- Centre-Ville : risque moyen modéré (~40) ---
            [
                'zone'              => 'Centre-Ville',
                'nom'               => 'Réservoir Bellevue',
                'type'              => 'réservoir',
                'materiau'          => 'Béton',
                'date_installation' => '1998-06-15',
                'capacite'          => 5000.00,
                'latitude'          => 48.8512000,
                'longitude'         => 2.3480000,
                'statut'            => 'actif',
                'score_risque'      => 18.50, // faible
            ],
            [
                'zone'              => 'Centre-Ville',
                'nom'               => 'Canalisation République',
                'type'              => 'canalisation',
                'materiau'          => 'Fonte',
                'date_installation' => '1985-09-20',
                'capacite'          => 900.00,
                'latitude'          => 48.8580000,
                'longitude'         => 2.3555000,
                'statut'            => 'maintenance',
                'score_risque'      => 62.00, // élevé
            ],

            // --- Rives du Nord : risque moyen faible (~20) ---
            [
                'zone'              => 'Rives du Nord',
                'nom'               => 'Station de pompage Nord',
                'type'              => 'station_pompage',
                'materiau'          => 'Acier',
                'date_installation' => '2010-03-01',
                'capacite'          => 1200.00,
                'latitude'          => 48.8745000,
                'longitude'         => 2.3640000,
                'statut'            => 'actif',
                'score_risque'      => 15.00, // faible
            ],
            [
                'zone'              => 'Rives du Nord',
                'nom'               => 'Château d\'eau des Rives',
                'type'              => 'réservoir',
                'materiau'          => 'Béton',
                'date_installation' => '2005-11-10',
                'capacite'          => 2500.00,
                'latitude'          => 48.8680000,
                'longitude'         => 2.3590000,
                'statut'            => 'actif',
                'score_risque'      => 22.00, // faible
            ],

            // --- Zone Industrielle Est : risque moyen critique (~88) ---
            [
                'zone'              => 'Zone Industrielle Est',
                'nom'               => 'Captage de la Source Claire',
                'type'              => 'captage',
                'materiau'          => 'Béton',
                'date_installation' => '1975-04-05',
                'capacite'          => 450.00,
                'latitude'          => 48.8400000,
                'longitude'         => 2.4050000,
                'statut'            => 'actif',
                'score_risque'      => 76.00, // élevé
            ],
            [
                'zone'              => 'Zone Industrielle Est',
                'nom'               => 'Canalisation secondaire Rue des Écoles',
                'type'              => 'canalisation',
                'materiau'          => 'PVC',
                'date_installation' => '1979-07-22',
                'capacite'          => 300.00,
                'latitude'          => 48.8450000,
                'longitude'         => 2.4090000,
                'statut'            => 'hors_service',
                'score_risque'      => 93.00, // critique
            ],
            [
                'zone'              => 'Zone Industrielle Est',
                'nom'               => 'Pompe de relevage Est',
                'type'              => 'station_pompage',
                'materiau'          => 'Acier',
                'date_installation' => '1982-01-15',
                'capacite'          => 600.00,
                'latitude'          => 48.8430000,
                'longitude'         => 2.4070000,
                'statut'            => 'maintenance',
                'score_risque'      => 95.00, // critique
            ],

            // --- Quartier Sud : risque moyen élevé (~72) ---
            [
                'zone'              => 'Quartier Sud',
                'nom'               => 'Réservoir du Plateau Sud',
                'type'              => 'réservoir',
                'materiau'          => 'Acier',
                'date_installation' => '1991-03-10',
                'capacite'          => 1800.00,
                'latitude'          => 48.8285000,
                'longitude'         => 2.3380000,
                'statut'            => 'actif',
                'score_risque'      => 70.00, // élevé
            ],
            [
                'zone'              => 'Quartier Sud',
                'nom'               => 'Canalisation Principale Sud',
                'type'              => 'canalisation',
                'materiau'          => 'Fonte',
                'date_installation' => '1988-06-01',
                'capacite'          => 750.00,
                'latitude'          => 48.8310000,
                'longitude'         => 2.3420000,
                'statut'            => 'actif',
                'score_risque'      => 74.00, // élevé
            ],

            // --- Plateau Ouest : zone récente, non encore évaluée (score 0) ---
            [
                'zone'              => 'Plateau Ouest',
                'nom'               => 'Station de traitement Ouest',
                'type'              => 'station_pompage',
                'materiau'          => 'Acier',
                'date_installation' => '2018-09-15',
                'capacite'          => 3000.00,
                'latitude'          => 48.8610000,
                'longitude'         => 2.3080000,
                'statut'            => 'en_construction',
                'score_risque'      => 0.00, // non encore évalué (colonne NOT NULL, défaut 0)
            ],
        ];

        $nbCreees = 0;

        foreach ($infrastructures as $donnees) {
            // Résoudre la zone par son nom
            $nomZone = $donnees['zone'];
            unset($donnees['zone']);

            if (! isset($zones[$nomZone])) {
                $this->command?->warn("Zone « {$nomZone} » introuvable — infrastructure « {$donnees['nom']} » ignorée.");

                continue;
            }

            $donnees['zone_id'] = $zones[$nomZone]->id;

            Infrastructure::updateOrCreate(
                ['nom' => $donnees['nom']],
                $donnees,
            );

            $nbCreees++;
        }

        $this->command?->info("InfrastructureSeeder : {$nbCreees} infrastructures créées/actualisées.");
    }
}
