<?php

namespace Database\Seeders;

use App\Models\Drought\WaterLevel;
use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Infrastructure\Zone;
use Illuminate\Database\Seeder;

/**
 * Seeder de données de test pour la Gestion 4 (Sécheresse).
 * 
 * Crée des niveaux, consommations, restrictions et coupures
 * pour tester l'export PDF et Excel.
 */
class DroughtTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer les zones créées
        $zones = Zone::all();

        if ($zones->isEmpty()) {
            $this->command?->warn('Aucune zone trouvée. Lancez ZoneTestSeeder en premier.');
            return;
        }

        // === NIVEAUX DES RESSOURCES ===
        foreach ($zones as $zone) {
            WaterLevel::firstOrCreate(
                [
                    'zone_id' => $zone->id,
                    'nom_source' => "Barrage de {$zone->nom}",
                    'date_mesure' => now(),
                ],
                [
                    'type_source' => 'barrage',
                    'niveau' => rand(25, 85),
                    'unite' => '%',
                    'seuil_alerte' => 30,
                    'seuil_critique' => 15,
                ]
            );

            WaterLevel::firstOrCreate(
                [
                    'zone_id' => $zone->id,
                    'nom_source' => "Nappe phréatique {$zone->nom}",
                    'date_mesure' => now()->subDays(1),
                ],
                [
                    'type_source' => 'nappe',
                    'niveau' => rand(20, 70),
                    'unite' => '%',
                    'seuil_alerte' => 40,
                    'seuil_critique' => 20,
                ]
            );
        }

        // === RELEVÉS DE CONSOMMATION ===
        foreach ($zones as $zone) {
            // 3 mois de données historiques
            for ($i = 2; $i >= 0; $i--) {
                $periode = now()->subMonths($i)->firstOfMonth();
                
                ConsumptionReading::firstOrCreate(
                    [
                        'zone_id' => $zone->id,
                        'periode' => $periode,
                    ],
                    [
                        'consommation_m3' => rand(5000, 50000),
                        'nombre_abonnes' => $zone->population / 10, // ~1 abonné pour 10 habitants
                    ]
                );
            }
        }

        // === RESTRICTIONS ===
        $restrictionTypes = ['vigilance', 'alerte', 'alerte_renforcee', 'crise'];
        
        foreach ($zones->take(3) as $zone) {
            $type = $restrictionTypes[array_rand($restrictionTypes)];
            
            Restriction::firstOrCreate(
                [
                    'zone_id' => $zone->id,
                    'titre' => "Restriction - {$zone->nom}",
                    'date_debut' => now()->subDays(5),
                ],
                [
                    'type' => $type,
                    'description' => "Restriction de consommation d'eau en zone {$zone->nom}.",
                    'usages_interdits' => 'Arrosage des espaces verts, remplissage piscines, lessive excessive',
                    'date_fin' => now()->addDays(10),
                    'statut' => 'active',
                ]
            );
        }

        // === COUPURES PROGRAMMÉES ===
        foreach ($zones->take(2) as $zone) {
            ScheduledCut::firstOrCreate(
                [
                    'zone_id' => $zone->id,
                    'motif' => "Maintenance réseau - {$zone->nom}",
                    'date_debut' => now()->addDays(3),
                ],
                [
                    'date_fin' => now()->addDays(3)->addHours(6),
                    'statut' => 'planifiee',
                ]
            );
        }

        $this->command?->info('Données de test créées avec succès :');
        $this->command?->line("  ✓ " . WaterLevel::count() . " mesures de niveaux");
        $this->command?->line("  ✓ " . ConsumptionReading::count() . " relevés de consommation");
        $this->command?->line("  ✓ " . Restriction::count() . " restrictions");
        $this->command?->line("  ✓ " . ScheduledCut::count() . " coupures programmées");
    }
}
