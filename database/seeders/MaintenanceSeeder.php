<?php

namespace Database\Seeders;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder de démonstration — Maintenances (Gestion 1 : Infrastructures et cartographie).
 *
 * Crée des maintenances étalées sur les 6 derniers mois avec des coûts variés
 * pour permettre de tester la prévision du coût de maintenance par zone.
 * Au moins une zone a une infrastructure critique pour valider le classement.
 *
 * Dépend de ZoneSeeder et InfrastructureSeeder.
 * Idempotent : updateOrCreate sur (infrastructure_id, description).
 *
 * Utilisation :
 *   php artisan db:seed --class=MaintenanceSeeder
 */
class MaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('MaintenanceSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        // Récupérer un technicien (gestionnaire ou admin) pour les maintenances
        $technicien = User::whereIn('role', UserRole::backOfficeValues())->first();

        if (! $technicien) {
            $this->command?->warn(
                'MaintenanceSeeder ignoré : aucun utilisateur back-office trouvé. '
                . 'Lancez d\'abord DemoUsersSeeder.'
            );

            return;
        }

        // Indexer les infrastructures par nom pour éviter les N+1
        $infrastructures = Infrastructure::all()->keyBy('nom');

        if ($infrastructures->isEmpty()) {
            $this->command?->warn(
                'MaintenanceSeeder ignoré : aucune infrastructure trouvée. '
                . 'Lancez d\'abord InfrastructureSeeder.'
            );

            return;
        }

        /*
         * Maintenances réparties sur les 6 derniers mois, statut 'terminee'
         * pour être comptabilisées dans la prévision des coûts.
         * Les offsets négatifs = mois passés depuis aujourd'hui (0 = mois en cours).
         */
        $maintenances = [
            // --- Centre-Ville : coûts croissants (tendance haussière) ---
            [
                'infrastructure' => 'Réservoir Bellevue',
                'description'    => 'Inspection annuelle et nettoyage de la cuve — M-5',
                'type'           => 'préventive',
                'mois_offset'    => -5,
                'cout'           => 1200.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Réservoir Bellevue',
                'description'    => 'Remplacement joints d\'étanchéité — M-4',
                'type'           => 'corrective',
                'mois_offset'    => -4,
                'cout'           => 1850.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation République',
                'description'    => 'Réparation fuite tronçon sud — M-3',
                'type'           => 'corrective',
                'mois_offset'    => -3,
                'cout'           => 4200.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation République',
                'description'    => 'Renforcement revêtement interne — M-2',
                'type'           => 'préventive',
                'mois_offset'    => -2,
                'cout'           => 3600.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Réservoir Bellevue',
                'description'    => 'Contrôle capteurs de pression — M-1',
                'type'           => 'préventive',
                'mois_offset'    => -1,
                'cout'           => 2100.00,
                'statut'         => 'terminee',
            ],

            // --- Rives du Nord : coûts stables/faibles ---
            [
                'infrastructure' => 'Station de pompage Nord',
                'description'    => 'Contrôle pompes et filtres — M-5',
                'type'           => 'préventive',
                'mois_offset'    => -5,
                'cout'           => 900.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Château d\'eau des Rives',
                'description'    => 'Inspection structurelle — M-4',
                'type'           => 'préventive',
                'mois_offset'    => -4,
                'cout'           => 750.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Station de pompage Nord',
                'description'    => 'Lubrification engrenages — M-2',
                'type'           => 'préventive',
                'mois_offset'    => -2,
                'cout'           => 480.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Château d\'eau des Rives',
                'description'    => 'Remplacement flotteur — M-1',
                'type'           => 'corrective',
                'mois_offset'    => -1,
                'cout'           => 620.00,
                'statut'         => 'terminee',
            ],

            // --- Zone Industrielle Est : coûts élevés (zone critique) ---
            [
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'description'    => 'Évaluation état général tronçon — M-5',
                'type'           => 'corrective',
                'mois_offset'    => -5,
                'cout'           => 8500.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Pompe de relevage Est',
                'description'    => 'Remplacement rotor — M-4',
                'type'           => 'corrective',
                'mois_offset'    => -4,
                'cout'           => 12000.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'description'    => 'Réparation fissures béton — M-3',
                'type'           => 'corrective',
                'mois_offset'    => -3,
                'cout'           => 9800.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Captage de la Source Claire',
                'description'    => 'Curage et désenvasement — M-3',
                'type'           => 'préventive',
                'mois_offset'    => -3,
                'cout'           => 3200.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Pompe de relevage Est',
                'description'    => 'Rebobinage moteur électrique — M-2',
                'type'           => 'corrective',
                'mois_offset'    => -2,
                'cout'           => 15000.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'description'    => 'Sécurisation tronçon hors service — M-1',
                'type'           => 'corrective',
                'mois_offset'    => -1,
                'cout'           => 11200.00,
                'statut'         => 'terminee',
            ],

            // --- Quartier Sud : coûts modérés ---
            [
                'infrastructure' => 'Réservoir du Plateau Sud',
                'description'    => 'Peinture anticorrosion cuve — M-5',
                'type'           => 'préventive',
                'mois_offset'    => -5,
                'cout'           => 4500.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation Principale Sud',
                'description'    => 'Inspection caméra réseau — M-4',
                'type'           => 'préventive',
                'mois_offset'    => -4,
                'cout'           => 2800.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Réservoir du Plateau Sud',
                'description'    => 'Remplacement vanne de sortie — M-2',
                'type'           => 'corrective',
                'mois_offset'    => -2,
                'cout'           => 6200.00,
                'statut'         => 'terminee',
            ],
            [
                'infrastructure' => 'Canalisation Principale Sud',
                'description'    => 'Réparation joint bride DN300 — M-1',
                'type'           => 'corrective',
                'mois_offset'    => -1,
                'cout'           => 3100.00,
                'statut'         => 'terminee',
            ],

            // --- Maintenances planifiées (non terminées, non comptabilisées) ---
            [
                'infrastructure' => 'Réservoir Bellevue',
                'description'    => 'Nettoyage programmé M+1',
                'type'           => 'préventive',
                'mois_offset'    => 1,
                'cout'           => 1800.00,
                'statut'         => 'planifiee',
            ],
            [
                'infrastructure' => 'Station de pompage Nord',
                'description'    => 'Révision générale M+2',
                'type'           => 'préventive',
                'mois_offset'    => 2,
                'cout'           => 2600.00,
                'statut'         => 'planifiee',
            ],
        ];

        $nbCreees = 0;

        foreach ($maintenances as $donnees) {
            $nomInfrastructure = $donnees['infrastructure'];

            if (! isset($infrastructures[$nomInfrastructure])) {
                $this->command?->warn("Infrastructure « {$nomInfrastructure} » introuvable — maintenance ignorée.");
                continue;
            }

            $infrastructure = $infrastructures[$nomInfrastructure];

            // Calculer la date : 15 du mois correspondant à l'offset
            $dateIntervention = Carbon::now()
                ->addMonths($donnees['mois_offset'])
                ->startOfMonth()
                ->addDays(14) // le 15 du mois
                ->toDateString();

            Maintenance::updateOrCreate(
                [
                    'infrastructure_id' => $infrastructure->id,
                    'description'       => $donnees['description'],
                ],
                [
                    'technicien_id'     => $technicien->id,
                    'type'              => $donnees['type'],
                    'date_intervention' => $dateIntervention,
                    'cout'              => $donnees['cout'],
                    'statut'            => $donnees['statut'],
                ],
            );

            $nbCreees++;
        }

        $this->command?->info("MaintenanceSeeder : {$nbCreees} maintenances créées/actualisées.");
    }
}
