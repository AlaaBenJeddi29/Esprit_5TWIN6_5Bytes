<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AlertSubscription;
use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentComment;
use App\Models\Incident\IncidentStatusHistory;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Contractor;
use App\Models\Project\Project;
use App\Models\Project\ProjectDocument;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Jeu de données relationnelles de DÉMONSTRATION (toutes les gestions).
 *
 * Sécurité et sûreté :
 * - refuse de s'exécuter en production ;
 * - ne crée aucun compte : utilise les comptes existants (idéalement ceux
 *   créés par DemoUsersSeeder) et s'arrête si aucun gestionnaire/admin
 *   n'existe ;
 * - idempotent : les enregistrements sont recherchés par clé naturelle puis
 *   actualisés, jamais dupliqués ;
 * - les documents de démonstration sont de simples fichiers texte écrits
 *   dans le disque `local`, jamais des données réelles ;
 * - aucun paiement réel : les dons sont des lignes Funding simulées.
 *
 * Appelé par DatabaseSeeder (php artisan db:seed) ; exécutable aussi seul :
 * php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        $gestionnaire = $this->findBackOfficeUser();

        if (! $gestionnaire) {
            $this->command?->warn(
                'DemoDataSeeder ignoré : aucun gestionnaire ou administrateur trouvé. '
                .'Lancez d\'abord DemoUsersSeeder (ou AdminUserSeeder).'
            );

            return;
        }

        $citoyen = $this->findCitizen();

        $zones = $this->seedZones();
        $infrastructures = $this->seedInfrastructures($zones);
        $this->seedMaintenances($infrastructures, $gestionnaire);
        $contractors = $this->seedContractors();
        $projects = $this->seedProjects($zones, $infrastructures, $gestionnaire);
        $this->seedProjectPhases($projects, $contractors);
        $this->seedFundings($projects, $citoyen);
        $this->seedProjectDocuments($projects);
        $this->seedIncidents($zones, $infrastructures, $gestionnaire, $citoyen);
        $this->seedQuality($zones, $infrastructures, $gestionnaire);
        $this->seedWaterLevels($zones);
        $this->seedConsumption($zones);
        $this->seedRestrictions($zones);
        $this->seedScheduledCuts($zones, $infrastructures);
        $this->seedSubscriptions($citoyen, $zones);

        $this->command?->info('Jeu de démonstration AquaSecure créé/actualisé (zones, infrastructures, projets, incidents, qualité, sécheresse).');
    }

    private function findBackOfficeUser(): ?User
    {
        $email = config('aquasecure.demo.users.gestionnaire.email');
        $user = $email ? User::where('email', $email)->first() : null;

        return $user
            ?? User::where('role', UserRole::Gestionnaire)->first()
            ?? User::where('role', UserRole::Admin)->first();
    }

    private function findCitizen(): ?User
    {
        $email = config('aquasecure.demo.users.citoyen.email');
        $user = $email ? User::where('email', $email)->first() : null;

        return $user ?? User::where('role', UserRole::Citoyen)->first();
    }

    /**
     * @return array<string, Zone>
     */
    private function seedZones(): array
    {
        $rows = [
            [
                'nom' => 'Centre-Ville',
                'commune' => 'Ville Haute',
                'code_postal' => '75001',
                'population' => 48200,
                'latitude' => 48.8566000,
                'longitude' => 2.3522000,
            ],
            [
                'nom' => 'Rives du Nord',
                'commune' => 'Ville Haute',
                'code_postal' => '75002',
                'population' => 36750,
                'latitude' => 48.8701000,
                'longitude' => 2.3612000,
            ],
            [
                'nom' => 'Zone Industrielle Est',
                'commune' => 'Est-sur-Loire',
                'code_postal' => '75003',
                'population' => 12400,
                'latitude' => 48.8425000,
                'longitude' => 2.4110000,
            ],
        ];

        $zones = [];

        foreach ($rows as $row) {
            $zones[$row['nom']] = Zone::updateOrCreate(['nom' => $row['nom']], $row);
        }

        return $zones;
    }

    /**
     * @param  array<string, Zone>  $zones
     * @return array<string, Infrastructure>
     */
    private function seedInfrastructures(array $zones): array
    {
        $rows = [
            [
                'nom' => 'Réservoir Bellevue',
                'zone' => 'Centre-Ville',
                'type' => 'réservoir',
                'materiau' => 'Béton',
                'date_installation' => '1998-06-15',
                'capacite' => 5000,
                'latitude' => 48.8512000,
                'longitude' => 2.3480000,
                'statut' => 'actif',
                'score_risque' => 3.2,
            ],
            [
                'nom' => 'Station de pompage Nord',
                'zone' => 'Rives du Nord',
                'type' => 'station_pompage',
                'materiau' => 'Acier',
                'date_installation' => '2010-03-01',
                'capacite' => 1200,
                'latitude' => 48.8745000,
                'longitude' => 2.3640000,
                'statut' => 'actif',
                'score_risque' => 2.1,
            ],
            [
                'nom' => 'Canalisation République',
                'zone' => 'Centre-Ville',
                'type' => 'canalisation',
                'materiau' => 'Fonte',
                'date_installation' => '1985-09-20',
                'capacite' => 900,
                'latitude' => 48.8580000,
                'longitude' => 2.3555000,
                'statut' => 'maintenance',
                'score_risque' => 7.4,
            ],
            [
                'nom' => 'Château d\'eau des Rives',
                'zone' => 'Rives du Nord',
                'type' => 'réservoir',
                'materiau' => 'Béton',
                'date_installation' => '2005-11-10',
                'capacite' => 2500,
                'latitude' => 48.8680000,
                'longitude' => 2.3590000,
                'statut' => 'actif',
                'score_risque' => 1.8,
            ],
            [
                'nom' => 'Captage de la Source Claire',
                'zone' => 'Zone Industrielle Est',
                'type' => 'captage',
                'materiau' => 'Béton',
                'date_installation' => '1975-04-05',
                'capacite' => 450,
                'latitude' => 48.8400000,
                'longitude' => 2.4050000,
                'statut' => 'actif',
                'score_risque' => 6.1,
            ],
            [
                'nom' => 'Canalisation secondaire Rue des Écoles',
                'zone' => 'Zone Industrielle Est',
                'type' => 'canalisation',
                'materiau' => 'PVC',
                'date_installation' => '2015-07-22',
                'capacite' => 300,
                'latitude' => 48.8450000,
                'longitude' => 2.4090000,
                'statut' => 'hors_service',
                'score_risque' => 8.7,
            ],
        ];

        $infrastructures = [];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            unset($row['zone']);

            $row['zone_id'] = $zone->id;

            $infrastructures[$row['nom']] = Infrastructure::updateOrCreate(
                ['nom' => $row['nom']],
                $row,
            );
        }

        return $infrastructures;
    }

    /**
     * @param  array<string, Infrastructure>  $infrastructures
     */
    private function seedMaintenances(array $infrastructures, User $gestionnaire): void
    {
        $rows = [
            [
                'infrastructure' => 'Canalisation République',
                'technicien' => $gestionnaire,
                'type' => 'curative',
                'description' => 'Réparation d\'une fuite sur un joint défaillant.',
                'date_intervention' => now()->subDays(12)->toDateString(),
                'cout' => 4200,
                'statut' => 'terminee',
            ],
            [
                'infrastructure' => 'Réservoir Bellevue',
                'technicien' => $gestionnaire,
                'type' => 'préventive',
                'description' => 'Nettoyage et inspection annuelle de la cuve.',
                'date_intervention' => now()->addDays(18)->toDateString(),
                'cout' => 7800,
                'statut' => 'planifiee',
            ],
            [
                'infrastructure' => 'Station de pompage Nord',
                'technicien' => null,
                'type' => 'préventive',
                'description' => 'Contrôle des pompes et remplacement des filtres.',
                'date_intervention' => now()->addDays(5)->toDateString(),
                'cout' => 2600,
                'statut' => 'planifiee',
            ],
            [
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'technicien' => $gestionnaire,
                'type' => 'curative',
                'description' => 'Tronçon hors service en attente de remplacement.',
                'date_intervention' => now()->subDays(4)->toDateString(),
                'cout' => 12500,
                'statut' => 'en_cours',
            ],
        ];

        foreach ($rows as $row) {
            $infrastructure = $infrastructures[$row['infrastructure']];

            Maintenance::updateOrCreate(
                [
                    'infrastructure_id' => $infrastructure->id,
                    'description' => $row['description'],
                ],
                [
                    'technicien_id' => $row['technicien']?->id,
                    'type' => $row['type'],
                    'date_intervention' => $row['date_intervention'],
                    'cout' => $row['cout'],
                    'statut' => $row['statut'],
                ],
            );
        }
    }

    /**
     * @return array<string, Contractor>
     */
    private function seedContractors(): array
    {
        $rows = [
            [
                'nom' => 'BTP Hydro Sud',
                'specialite' => 'Terrassement et réseaux',
                'email' => 'contact@btp-hydro.test',
                'telephone' => '01 23 45 67 89',
                'adresse' => '14 avenue des Travaux, 75001 Ville Haute',
            ],
            [
                'nom' => 'CanalPro Ingénierie',
                'specialite' => 'Études et maîtrise d\'œuvre',
                'email' => 'etudes@canalpro.test',
                'telephone' => '01 98 76 54 32',
                'adresse' => '8 rue des Ingénieurs, 75002 Ville Haute',
            ],
            [
                'nom' => 'AquaTech Services',
                'specialite' => 'Maintenance électromécanique',
                'email' => 'service@aquatech.test',
                'telephone' => '01 55 44 33 22',
                'adresse' => '2 zone artisanale, 75003 Est-sur-Loire',
            ],
        ];

        $contractors = [];

        foreach ($rows as $row) {
            $contractors[$row['nom']] = Contractor::updateOrCreate(
                ['email' => $row['email']],
                $row,
            );
        }

        return $contractors;
    }

    /**
     * @param  array<string, Zone>  $zones
     * @param  array<string, Infrastructure>  $infrastructures
     * @return array<string, Project>
     */
    private function seedProjects(array $zones, array $infrastructures, User $responsable): array
    {
        $rows = [
            [
                'titre' => 'Réhabilitation de la canalisation République',
                'type' => 'réparation',
                'description' => 'Remplacement des tronçons les plus dégradés de la canalisation principale du centre-ville, avec reprise des branchements.',
                'budget_prevu' => 420000,
                'date_debut' => now()->subMonths(4)->toDateString(),
                'date_fin_prevue' => now()->addMonths(4)->toDateString(),
                'statut' => 'en_cours',
                'avancement_pourcentage' => 45,
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
            ],
            [
                'titre' => 'Modernisation de la station de pompage Nord',
                'type' => 'modernisation',
                'description' => 'Remplacement des groupes de pompage et automatisation de la télégestion.',
                'budget_prevu' => 680000,
                'date_debut' => now()->subMonths(2)->toDateString(),
                'date_fin_prevue' => now()->addMonths(8)->toDateString(),
                'statut' => 'en_cours',
                'avancement_pourcentage' => 30,
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Station de pompage Nord',
            ],
            [
                'titre' => 'Extension du réseau Zone Industrielle Est',
                'type' => 'extension',
                'description' => 'Création de 3,2 km de conduites pour desservir les nouvelles parcelles industrielles.',
                'budget_prevu' => 950000,
                'date_debut' => now()->addMonth()->toDateString(),
                'date_fin_prevue' => now()->addMonths(14)->toDateString(),
                'statut' => 'planifié',
                'avancement_pourcentage' => 5,
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => null,
            ],
            [
                'titre' => 'Rénovation du réservoir Bellevue',
                'type' => 'modernisation',
                'description' => 'Réfection de l\'étanchéité intérieure et mise à niveau des équipements de mesure.',
                'budget_prevu' => 310000,
                'date_debut' => now()->subMonths(10)->toDateString(),
                'date_fin_prevue' => now()->subMonth()->toDateString(),
                'statut' => 'terminé',
                'avancement_pourcentage' => 100,
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Réservoir Bellevue',
            ],
        ];

        $projects = [];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            $infrastructure = $row['infrastructure'] ? $infrastructures[$row['infrastructure']] : null;

            unset($row['zone'], $row['infrastructure']);

            $projects[$row['titre']] = Project::updateOrCreate(
                ['titre' => $row['titre']],
                $row + [
                    'zone_id' => $zone->id,
                    'infrastructure_id' => $infrastructure?->id,
                    'responsable_id' => $responsable->id,
                ],
            );
        }

        return $projects;
    }

    /**
     * @param  array<string, Project>  $projects
     * @param  array<string, Contractor>  $contractors
     */
    private function seedProjectPhases(array $projects, array $contractors): void
    {
        $rows = [
            ['project' => 'Réhabilitation de la canalisation République', 'nom' => 'Diagnostic et travaux préparatoires', 'contractor' => 'CanalPro Ingénierie', 'debut' => -4, 'fin' => -2, 'cout' => 85000, 'avancement' => 100],
            ['project' => 'Réhabilitation de la canalisation République', 'nom' => 'Remplacement des tronçons', 'contractor' => 'BTP Hydro Sud', 'debut' => -2, 'fin' => 4, 'cout' => 300000, 'avancement' => 40],
            ['project' => 'Modernisation de la station de pompage Nord', 'nom' => 'Études techniques', 'contractor' => 'CanalPro Ingénierie', 'debut' => -2, 'fin' => 0, 'cout' => 60000, 'avancement' => 100],
            ['project' => 'Modernisation de la station de pompage Nord', 'nom' => 'Remplacement des pompes', 'contractor' => 'AquaTech Services', 'debut' => 0, 'fin' => 8, 'cout' => 420000, 'avancement' => 25],
            ['project' => 'Extension du réseau Zone Industrielle Est', 'nom' => 'Études d\'impact', 'contractor' => 'CanalPro Ingénierie', 'debut' => 1, 'fin' => 3, 'cout' => 90000, 'avancement' => 0],
            ['project' => 'Extension du réseau Zone Industrielle Est', 'nom' => 'Terrassement et pose', 'contractor' => 'BTP Hydro Sud', 'debut' => 3, 'fin' => 14, 'cout' => 700000, 'avancement' => 0],
            ['project' => 'Rénovation du réservoir Bellevue', 'nom' => 'Inspection structurelle', 'contractor' => 'CanalPro Ingénierie', 'debut' => -10, 'fin' => -7, 'cout' => 45000, 'avancement' => 100],
            ['project' => 'Rénovation du réservoir Bellevue', 'nom' => 'Étanchéité et équipements', 'contractor' => 'AquaTech Services', 'debut' => -7, 'fin' => -1, 'cout' => 210000, 'avancement' => 100],
        ];

        foreach ($rows as $row) {
            $project = $projects[$row['project']];
            $contractor = $contractors[$row['contractor']];

            $project->projectPhases()->updateOrCreate(
                ['nom' => $row['nom']],
                [
                    'contractor_id' => $contractor->id,
                    'date_debut' => now()->addMonths($row['debut'])->toDateString(),
                    'date_fin' => now()->addMonths($row['fin'])->toDateString(),
                    'cout' => $row['cout'],
                    'avancement' => $row['avancement'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Project>  $projects
     */
    private function seedFundings(array $projects, ?User $citoyen): void
    {
        $rows = [
            ['project' => 'Réhabilitation de la canalisation République', 'source' => 'municipal', 'montant' => 200000, 'mois' => -4],
            ['project' => 'Réhabilitation de la canalisation République', 'source' => 'régional', 'montant' => 120000, 'mois' => -3],
            ['project' => 'Modernisation de la station de pompage Nord', 'source' => 'municipal', 'montant' => 300000, 'mois' => -2],
            ['project' => 'Modernisation de la station de pompage Nord', 'source' => 'européen', 'montant' => 200000, 'mois' => -1],
            ['project' => 'Extension du réseau Zone Industrielle Est', 'source' => 'régional', 'montant' => 400000, 'mois' => 0],
            ['project' => 'Extension du réseau Zone Industrielle Est', 'source' => 'municipal', 'montant' => 300000, 'mois' => 0],
            ['project' => 'Rénovation du réservoir Bellevue', 'source' => 'municipal', 'montant' => 310000, 'mois' => -10],
        ];

        foreach ($rows as $row) {
            $projects[$row['project']]->fundings()->updateOrCreate(
                ['source' => $row['source'], 'montant' => $row['montant']],
                [
                    'donateur_id' => null,
                    'date_versement' => now()->addMonths($row['mois'])->toDateString(),
                ],
            );
        }

        if ($citoyen) {
            $projects['Réhabilitation de la canalisation République']->fundings()->updateOrCreate(
                ['source' => 'don', 'montant' => 250],
                [
                    'donateur_id' => $citoyen->id,
                    'date_versement' => now()->subDays(20)->toDateString(),
                ],
            );
        }
    }

    /**
     * @param  array<string, Project>  $projects
     */
    private function seedProjectDocuments(array $projects): void
    {
        $rows = [
            ['project' => 'Réhabilitation de la canalisation République', 'titre' => 'Rapport de diagnostic', 'fichier' => 'rapport-diagnostic.txt', 'type' => 'rapport', 'public' => true, 'contenu' => "Rapport de diagnostic de démonstration — canalisation République.\nAucune donnée réelle."],
            ['project' => 'Réhabilitation de la canalisation République', 'titre' => 'Plan des travaux', 'fichier' => 'plan-travaux.txt', 'type' => 'plan', 'public' => true, 'contenu' => "Plan des travaux de démonstration — canalisation République.\nAucune donnée réelle."],
            ['project' => 'Réhabilitation de la canalisation République', 'titre' => 'Contrat de travaux', 'fichier' => 'contrat.txt', 'type' => 'contrat', 'public' => false, 'contenu' => "Contrat de démonstration — usage interne."],
            ['project' => 'Modernisation de la station de pompage Nord', 'titre' => 'Fiche projet station Nord', 'fichier' => 'fiche-projet.txt', 'type' => 'rapport', 'public' => true, 'contenu' => "Fiche projet de démonstration — station de pompage Nord.\nAucune donnée réelle."],
            ['project' => 'Extension du réseau Zone Industrielle Est', 'titre' => 'Étude d\'impact', 'fichier' => 'etude-impact.txt', 'type' => 'autre', 'public' => true, 'contenu' => "Étude d'impact de démonstration — extension Zone Est.\nAucune donnée réelle."],
            ['project' => 'Rénovation du réservoir Bellevue', 'titre' => 'Rapport de fin de chantier', 'fichier' => 'fin-chantier.txt', 'type' => 'rapport', 'public' => true, 'contenu' => "Rapport de fin de chantier de démonstration — réservoir Bellevue.\nAucune donnée réelle."],
        ];

        foreach ($rows as $row) {
            $project = $projects[$row['project']];
            $path = "demo/projects/{$project->id}/{$row['fichier']}";

            Storage::disk('local')->put($path, $row['contenu']);

            $project->projectDocuments()->updateOrCreate(
                ['titre' => $row['titre']],
                [
                    'chemin_fichier' => $path,
                    'type' => $row['type'],
                    'est_public' => $row['public'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Zone>  $zones
     * @param  array<string, Infrastructure>  $infrastructures
     */
    private function seedIncidents(
        array $zones,
        array $infrastructures,
        User $gestionnaire,
        ?User $citoyen,
    ): void {
        $rows = [
            [
                'reference' => 'INC-DEMO-0001',
                'type' => 'fuite',
                'title' => 'Fuite importante avenue de la République',
                'description' => 'Écoulement continu sur la chaussée depuis un regard, devant le n°42.',
                'address' => '42 avenue de la République',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
                'severity' => 'haute',
                'status' => 'en_cours',
                'priority' => 'haute',
                'ai_category' => 'fuite',
                'ai_confidence' => 94.5,
                'ai_summary' => 'Fuite probable sur le réseau de distribution, à proximité immédiate de la canalisation République.',
                'reported_at' => now()->subDays(2),
                'assigned_to' => $gestionnaire->id,
                'resolved_at' => null,
                'resolution_note' => null,
            ],
            [
                'reference' => 'INC-DEMO-0002',
                'type' => 'qualite',
                'title' => 'Eau trouble au robinet',
                'description' => 'L\'eau présente une couleur brunâtre depuis deux jours dans le quartier.',
                'address' => '7 quai des Rives',
                'zone' => 'Rives du Nord',
                'infrastructure' => null,
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'priority' => 'normale',
                'ai_category' => 'qualite',
                'ai_confidence' => 81.0,
                'ai_summary' => 'Signalement lié à la qualité de l\'eau ; un prélèvement de contrôle est recommandé.',
                'reported_at' => now()->subDay(),
                'assigned_to' => null,
                'resolved_at' => null,
                'resolution_note' => null,
            ],
            [
                'reference' => 'INC-DEMO-0003',
                'type' => 'coupure',
                'title' => 'Coupure secteur Est',
                'description' => 'Plus d\'eau dans plusieurs rues de la zone industrielle depuis ce matin.',
                'address' => 'Rue des Écoles',
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'severity' => 'haute',
                'status' => 'traite',
                'priority' => 'urgente',
                'ai_category' => 'coupure',
                'ai_confidence' => 88.0,
                'ai_summary' => 'Coupure vraisemblablement liée au tronçon hors service de la rue des Écoles.',
                'reported_at' => now()->subDays(4),
                'assigned_to' => $gestionnaire->id,
                'resolved_at' => now()->subDays(3),
                'resolution_note' => 'Alimentation rétablie après intervention sur le tronçon endommagé.',
            ],
            [
                'reference' => 'INC-DEMO-0004',
                'type' => 'pression',
                'title' => 'Baisse de pression rue des Lilas',
                'description' => 'Pression insuffisante aux heures de pointe, surtout au dernier étage.',
                'address' => '18 rue des Lilas',
                'zone' => 'Centre-Ville',
                'infrastructure' => null,
                'severity' => 'faible',
                'status' => 'en_attente',
                'priority' => 'normale',
                'ai_category' => 'pression',
                'ai_confidence' => 69.0,
                'ai_summary' => 'Baisse de pression possiblement liée à un incident réseau voisin.',
                'reported_at' => now()->subDays(6),
                'assigned_to' => null,
                'resolved_at' => null,
                'resolution_note' => null,
            ],
            [
                'reference' => 'INC-DEMO-0005',
                'type' => 'refoulement',
                'title' => 'Refoulement dans un sous-sol',
                'description' => 'Remontée d\'eaux dans le local technique d\'un immeuble riverain.',
                'address' => '3 rue du Moulin',
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Château d\'eau des Rives',
                'severity' => 'critique',
                'status' => 'en_cours',
                'priority' => 'urgente',
                'ai_category' => 'refoulement',
                'ai_confidence' => 90.0,
                'ai_summary' => 'Refoulement nécessitant une vérification immédiate du réseau d\'assainissement et de l\'alimentation.',
                'reported_at' => now()->subHours(6),
                'assigned_to' => $gestionnaire->id,
                'resolved_at' => null,
                'resolution_note' => null,
            ],
        ];

        $incidents = [];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            $infrastructure = $row['infrastructure'] ? $infrastructures[$row['infrastructure']] : null;

            unset($row['zone'], $row['infrastructure']);

            $incidents[$row['reference']] = Incident::updateOrCreate(
                ['reference' => $row['reference']],
                $row + [
                    'user_id' => $citoyen?->id,
                    'zone_id' => $zone->id,
                    'infrastructure_id' => $infrastructure?->id,
                ],
            );
        }

        IncidentComment::updateOrCreate(
            ['incident_id' => $incidents['INC-DEMO-0001']->id, 'body' => 'Une équipe est envoyée sur place pour localiser la fuite.'],
            ['user_id' => $gestionnaire->id, 'is_internal' => true],
        );

        if ($citoyen) {
            IncidentComment::updateOrCreate(
                ['incident_id' => $incidents['INC-DEMO-0001']->id, 'body' => 'L\'écoulement semble plus fort depuis ce matin.'],
                ['user_id' => $citoyen->id, 'is_internal' => false],
            );
        }

        IncidentComment::updateOrCreate(
            ['incident_id' => $incidents['INC-DEMO-0003']->id, 'body' => 'Intervention terminée, alimentation rétablie.'],
            ['user_id' => $gestionnaire->id, 'is_internal' => true],
        );

        IncidentStatusHistory::updateOrCreate(
            [
                'incident_id' => $incidents['INC-DEMO-0001']->id,
                'ancien_statut' => 'nouveau',
                'nouveau_statut' => 'en_cours',
            ],
            ['user_id' => $gestionnaire->id, 'comment' => 'Prise en charge et affectation à une équipe.'],
        );

        IncidentStatusHistory::updateOrCreate(
            [
                'incident_id' => $incidents['INC-DEMO-0003']->id,
                'ancien_statut' => 'en_cours',
                'nouveau_statut' => 'traite',
            ],
            ['user_id' => $gestionnaire->id, 'comment' => 'Alimentation rétablie.'],
        );
    }

    /**
     * @param  array<string, Zone>  $zones
     * @param  array<string, Infrastructure>  $infrastructures
     */
    private function seedQuality(array $zones, array $infrastructures, User $gestionnaire): void
    {
        $thresholds = [
            ['parametre' => 'ph', 'unite' => 'pH', 'valeur_min' => 6.5, 'valeur_max' => 8.5],
            ['parametre' => 'turbidite', 'unite' => 'NTU', 'valeur_min' => 0, 'valeur_max' => 5],
            ['parametre' => 'chlore', 'unite' => 'mg/L', 'valeur_min' => 0.1, 'valeur_max' => 1.0],
            ['parametre' => 'nitrates', 'unite' => 'mg/L', 'valeur_min' => 0, 'valeur_max' => 50],
        ];

        foreach ($zones as $zone) {
            foreach ($thresholds as $threshold) {
                QualityThreshold::updateOrCreate(
                    ['zone_id' => $zone->id, 'parametre' => $threshold['parametre']],
                    $threshold + ['actif' => true],
                );
            }
        }

        $rows = [
            [
                'reference' => 'AQ-DEMO-0001',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Réservoir Bellevue',
                'jours' => -80,
                'statut' => 'conforme',
                'score' => 100,
                'notes' => 'Contrôle de routine, résultats conformes.',
                'parametres' => [
                    ['ph', 7.2], ['turbidite', 1.2], ['chlore', 0.4],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0002',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Réservoir Bellevue',
                'jours' => -50,
                'statut' => 'conforme',
                'score' => 100,
                'notes' => 'Contrôle de routine, résultats conformes.',
                'parametres' => [
                    ['ph', 7.4], ['turbidite', 0.8], ['chlore', 0.35],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0003',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
                'jours' => -12,
                'statut' => 'non_conforme',
                'score' => 66.67,
                'notes' => 'Turbidité élevée après travaux sur la canalisation.',
                'parametres' => [
                    ['ph', 7.1], ['turbidite', 9.8], ['chlore', 0.3],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0004',
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Château d\'eau des Rives',
                'jours' => -40,
                'statut' => 'conforme',
                'score' => 100,
                'notes' => 'Contrôle de routine, résultats conformes.',
                'parametres' => [
                    ['ph', 7.3], ['turbidite', 1.4], ['chlore', 0.5],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0005',
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Station de pompage Nord',
                'jours' => -8,
                'statut' => 'conforme',
                'score' => 100,
                'notes' => 'Contrôle de routine, résultats conformes.',
                'parametres' => [
                    ['ph', 7.5], ['turbidite', 1.0], ['chlore', 0.45],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0006',
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Captage de la Source Claire',
                'jours' => -60,
                'statut' => 'non_conforme',
                'score' => 75,
                'notes' => 'Teneur en nitrates supérieure au seuil réglementaire.',
                'parametres' => [
                    ['ph', 6.6], ['turbidite', 2.2], ['chlore', 0.4], ['nitrates', 62],
                ],
            ],
            [
                'reference' => 'AQ-DEMO-0007',
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Captage de la Source Claire',
                'jours' => -6,
                'statut' => 'conforme',
                'score' => 100,
                'notes' => 'Contrôle de confirmation, résultats conformes.',
                'parametres' => [
                    ['ph', 6.9], ['turbidite', 1.8], ['chlore', 0.38],
                ],
            ],
        ];

        $thresholdMap = collect($thresholds)->keyBy('parametre');

        $samples = [];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            $infrastructure = $infrastructures[$row['infrastructure']];

            $samples[$row['reference']] = WaterQualitySample::updateOrCreate(
                ['reference' => $row['reference']],
                [
                    'zone_id' => $zone->id,
                    'infrastructure_id' => $infrastructure->id,
                    'user_id' => $gestionnaire->id,
                    'date_prelevement' => now()->addDays($row['jours']),
                    'source' => $infrastructure->nom,
                    'statut' => $row['statut'],
                    'score' => $row['score'],
                    'notes' => $row['notes'],
                ],
            );

            foreach ($row['parametres'] as [$parametre, $valeur]) {
                $threshold = $thresholdMap[$parametre];

                $conforme = $valeur >= $threshold['valeur_min'] && $valeur <= $threshold['valeur_max'];

                WaterQualityParameter::updateOrCreate(
                    ['sample_id' => $samples[$row['reference']]->id, 'parametre' => $parametre],
                    [
                        'valeur' => $valeur,
                        'unite' => $threshold['unite'],
                        'seuil_min' => $threshold['valeur_min'],
                        'seuil_max' => $threshold['valeur_max'],
                        'est_conforme' => $conforme,
                    ],
                );
            }
        }

        WaterQualityAlert::updateOrCreate(
            ['sample_id' => $samples['AQ-DEMO-0003']->id, 'parametre' => 'turbidite'],
            [
                'zone_id' => $zones['Centre-Ville']->id,
                'niveau' => 'critique',
                'message' => 'Turbidité : valeur mesurée 9,8 NTU (plage attendue 0 – 5 NTU) — zone Centre-Ville.',
                'statut' => 'active',
                'resolue_at' => null,
            ],
        );

        WaterQualityAlert::updateOrCreate(
            ['sample_id' => $samples['AQ-DEMO-0006']->id, 'parametre' => 'nitrates'],
            [
                'zone_id' => $zones['Zone Industrielle Est']->id,
                'niveau' => 'avertissement',
                'message' => 'Nitrates : valeur mesurée 62 mg/L (plage attendue 0 – 50 mg/L) — zone Zone Industrielle Est.',
                'statut' => 'resolue',
                'resolue_at' => now()->subDays(50),
            ],
        );
    }

    /**
     * @param  array<string, Zone>  $zones
     */
    private function seedWaterLevels(array $zones): void
    {
        $rows = [
            ['nom_source' => 'Barrage de la Vallée', 'zone' => 'Centre-Ville', 'type_source' => 'barrage', 'niveau' => 42, 'seuil_alerte' => 35, 'seuil_critique' => 20],
            ['nom_source' => 'Nappe des Rives', 'zone' => 'Rives du Nord', 'type_source' => 'nappe', 'niveau' => 26, 'seuil_alerte' => 30, 'seuil_critique' => 15],
            ['nom_source' => 'Réservoir Est', 'zone' => 'Zone Industrielle Est', 'type_source' => 'reservoir', 'niveau' => 9, 'seuil_alerte' => 20, 'seuil_critique' => 10],
            ['nom_source' => 'Rivière Claire', 'zone' => 'Centre-Ville', 'type_source' => 'riviere', 'niveau' => 55, 'seuil_alerte' => 30, 'seuil_critique' => 15],
        ];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            unset($row['zone']);

            WaterLevel::updateOrCreate(
                ['nom_source' => $row['nom_source']],
                $row + [
                    'zone_id' => $zone->id,
                    'unite' => '%',
                    'date_mesure' => now()->subHours(12),
                ],
            );
        }
    }

    /**
     * @param  array<string, Zone>  $zones
     */
    private function seedConsumption(array $zones): void
    {
        $baselines = [
            'Centre-Ville' => ['base' => 12500, 'growth' => 180, 'abonnes' => 4200],
            'Rives du Nord' => ['base' => 9800, 'growth' => 120, 'abonnes' => 3100],
            'Zone Industrielle Est' => ['base' => 7400, 'growth' => 250, 'abonnes' => 1900],
        ];

        foreach ($baselines as $zoneName => $baseline) {
            for ($i = 5; $i >= 0; $i--) {
                $periode = now()->startOfMonth()->subMonths($i)->toDateString();

                ConsumptionReading::updateOrCreate(
                    ['zone_id' => $zones[$zoneName]->id, 'periode' => $periode],
                    [
                        'consommation_m3' => $baseline['base'] + (5 - $i) * $baseline['growth'],
                        'nombre_abonnes' => $baseline['abonnes'],
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, Zone>  $zones
     */
    private function seedRestrictions(array $zones): void
    {
        $rows = [
            [
                'titre' => 'Limitation de l\'arrosage — Zone Est',
                'zone' => 'Zone Industrielle Est',
                'type' => 'alerte',
                'description' => 'Niveau du réservoir Est sous le seuil critique. Les usages non prioritaires sont limités.',
                'usages_interdits' => 'Arrosage des espaces verts, lavage des véhicules, remplissage des piscines.',
                'date_debut' => now()->subDays(7),
                'date_fin' => now()->addDays(14),
                'statut' => 'active',
            ],
            [
                'titre' => 'Vigilance renforcée — Rives du Nord',
                'zone' => 'Rives du Nord',
                'type' => 'vigilance',
                'description' => 'Nappe des Rives sous le seuil d\'alerte ; appel à une consommation raisonnée.',
                'usages_interdits' => 'Arrosage en journée (8 h – 20 h).',
                'date_debut' => now()->subDays(3),
                'date_fin' => now()->addDays(20),
                'statut' => 'active',
            ],
            [
                'titre' => 'Restriction hivernale levée',
                'zone' => 'Centre-Ville',
                'type' => 'vigilance',
                'description' => 'Mesure saisonnière terminée, niveaux revenus à la normale.',
                'usages_interdits' => null,
                'date_debut' => now()->subMonths(5),
                'date_fin' => now()->subMonths(2),
                'statut' => 'levee',
            ],
        ];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            unset($row['zone']);

            Restriction::updateOrCreate(
                ['titre' => $row['titre']],
                $row + ['zone_id' => $zone->id],
            );
        }
    }

    /**
     * @param  array<string, Zone>  $zones
     * @param  array<string, Infrastructure>  $infrastructures
     */
    private function seedScheduledCuts(array $zones, array $infrastructures): void
    {
        $rows = [
            [
                'motif' => 'Intervention sur la canalisation République',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
                'date_debut' => now()->addDays(3)->setTime(9, 0),
                'date_fin' => now()->addDays(3)->setTime(15, 0),
                'statut' => 'planifiee',
            ],
            [
                'motif' => 'Maintenance du réservoir Bellevue',
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Réservoir Bellevue',
                'date_debut' => now()->subDays(2)->setTime(8, 0),
                'date_fin' => now()->subDays(2)->setTime(14, 0),
                'statut' => 'terminee',
            ],
            [
                'motif' => 'Raccordement réseau Zone Est',
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'date_debut' => now()->addDays(7)->setTime(7, 30),
                'date_fin' => now()->addDays(7)->setTime(17, 0),
                'statut' => 'planifiee',
            ],
        ];

        foreach ($rows as $row) {
            $zone = $zones[$row['zone']];
            $infrastructure = $infrastructures[$row['infrastructure']];

            ScheduledCut::updateOrCreate(
                ['motif' => $row['motif']],
                [
                    'zone_id' => $zone->id,
                    'infrastructure_id' => $infrastructure->id,
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'statut' => $row['statut'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Zone>  $zones
     */
    private function seedSubscriptions(?User $citoyen, array $zones): void
    {
        if (! $citoyen) {
            return;
        }

        AlertSubscription::updateOrCreate(
            ['user_id' => $citoyen->id, 'zone_id' => $zones['Centre-Ville']->id],
            ['type_alerte' => 'tous', 'actif' => true],
        );

        AlertSubscription::updateOrCreate(
            ['user_id' => $citoyen->id, 'zone_id' => $zones['Zone Industrielle Est']->id],
            ['type_alerte' => 'qualite', 'actif' => true],
        );
    }
}
