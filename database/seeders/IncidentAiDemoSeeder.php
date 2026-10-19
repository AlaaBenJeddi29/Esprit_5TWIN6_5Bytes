<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentStatusHistory;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Jeu de démonstration pour la détection d'incidents similaires et l'IA.
 *
 * Chaque incident possède une référence stable « DEMO-AI-* » : le seeder est
 * idempotent et ne touche jamais aux autres signalements. Aucune suggestion
 * de similarité ni analyse IA n'est pré-créée : elles doivent être produites
 * par l'utilisateur depuis le Back Office ou les commandes Artisan.
 *
 * Utilisation :
 *   php artisan db:seed --class=IncidentAiDemoSeeder
 */
class IncidentAiDemoSeeder extends Seeder
{
    public const REFERENCE_PREFIX = 'DEMO-AI-';

    private const TITLE_PREFIX = '[DEMO] ';

    private const MINIMUM_ZONES = 4;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('IncidentAiDemoSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        $zones = $this->ensureGeography();

        if ($zones === null) {
            return;
        }

        [$zoneA, $zoneB, $zoneC, $zoneD] = $zones;

        $citizen = $this->findCitizen();
        $manager = $this->findManager();

        if ($citizen === null) {
            $this->command?->warn('Aucun citoyen trouvé : les signalements seront créés sans déclarant.');
        }

        if ($manager === null) {
            $this->command?->warn('Aucun gestionnaire trouvé : aucun incident ne sera affecté.');
        }

        $incidents = [];

        foreach ($this->seedGroupA($zoneA, $citizen, $manager) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupB($zoneB, $citizen) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupC($zoneA, $citizen) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupD($zoneB, $citizen, $manager) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupE($zoneA, $citizen, $manager, $incidents['A-01']) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupF($zoneC, $citizen, $manager) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        foreach ($this->seedGroupG($zoneD, $citizen, $manager) as $key => $incident) {
            $incidents[$key] = $incident;
        }

        $this->printSummary($incidents);
    }

    /**
     * @return list<Zone>|null
     */
    private function ensureGeography(): ?array
    {
        if (! Zone::query()->exists()) {
            $this->call(ZoneSeeder::class);
        }

        if (! Infrastructure::query()->exists()) {
            $this->call(InfrastructureSeeder::class);
        }

        $zones = Zone::query()->orderBy('id')->get();

        while ($zones->count() < self::MINIMUM_ZONES) {
            $index = $zones->count() + 1;

            $zone = Zone::query()->firstOrCreate(
                ['nom' => 'Secteur Démo IA '.$index],
                [
                    'commune' => 'Commune Démo IA',
                    'code_postal' => '7500'.$index,
                    'population' => 8000,
                    'latitude' => 48.8566 + ($index * 0.01),
                    'longitude' => 2.3522 + ($index * 0.01),
                ],
            );

            if (! $zone->exists) {
                $this->command?->error('Impossible de préparer les zones de démonstration.');

                return null;
            }

            $zones->push($zone);
        }

        return $zones->take(self::MINIMUM_ZONES)->values()->all();
    }

    private function findCitizen(): ?User
    {
        $email = config('aquasecure.demo.users.citoyen.email');
        $user = $email ? User::query()->where('email', $email)->first() : null;

        return $user ?? User::query()->where('role', UserRole::Citoyen)->orderBy('id')->first();
    }

    private function findManager(): ?User
    {
        $email = config('aquasecure.demo.users.gestionnaire.email');
        $user = $email ? User::query()->where('email', $email)->first() : null;

        return $user
            ?? User::query()->where('role', UserRole::Gestionnaire)->orderBy('id')->first()
            ?? User::query()->where('role', UserRole::Admin)->orderBy('id')->first();
    }

    /**
     * Groupe A — quasi-doublons dans la même zone.
     *
     * @return array<string, Incident>
     */
    private function seedGroupA(Zone $zone, ?User $citizen, ?User $manager): array
    {
        [$latitude, $longitude] = $this->coordinates($zone, 0.0008, 0.0005);

        $title = self::TITLE_PREFIX."Eau marron et odeur près de l'école";
        $description = "Eau marron et mauvaise odeur devant l'école primaire depuis hier matin. "
            .'Les habitants signalent un liquide trouble au robinet.';

        $a1 = $this->upsertIncident('A-01', now()->subDay()->setTime(8, 15), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => $title,
            'description' => $description,
            'address' => "12 rue de l'École",
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
        ]);

        $a2 = $this->upsertIncident('A-02', now()->subDay()->setTime(14, 40), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => self::TITLE_PREFIX.'Eau trouble marron avec mauvaise odeur devant l\'école',
            'description' => "L'eau du robinet est trouble et marron avec une mauvaise odeur devant l'école primaire depuis hier. "
                .'Un liquide brun sort aussi du regard.',
            'address' => "12 rue de l'École",
            'latitude' => round($latitude + 0.0002, 7),
            'longitude' => round($longitude + 0.0002, 7),
            'severity' => 'haute',
            'status' => 'en_cours',
            'priority' => 'haute',
            'assigned_to' => $manager?->id,
        ]);

        $a3 = $this->upsertIncident('A-03', now()->subDays(2)->setTime(9, 5), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => $title,
            'description' => $description,
            'address' => "12 rue de l'École",
            'latitude' => round($latitude - 0.0002, 7),
            'longitude' => round($longitude - 0.0001, 7),
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
        ]);

        $this->recordHistory(
            $a2,
            'nouveau',
            'en_cours',
            $a2->reported_at->copy()->addHours(2),
            'Prise en charge et analyse sur place.',
            actor: $manager,
        );

        return ['A-01' => $a1, 'A-02' => $a2, 'A-03' => $a3];
    }

    /**
     * Groupe B — même texte que le groupe A, mais dans une autre zone.
     * La présélection SQL limite les candidats à la zone : ce signalement
     * ne doit donc jamais être proposé pour A-01.
     *
     * @return array<string, Incident>
     */
    private function seedGroupB(Zone $zone, ?User $citizen): array
    {
        [$latitude, $longitude] = $this->coordinates($zone, 0.0006, 0.0005);

        $b1 = $this->upsertIncident('B-01', now()->subDay()->setTime(10, 20), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => self::TITLE_PREFIX."Eau marron et odeur près de l'école",
            'description' => "Eau marron et mauvaise odeur devant l'école primaire depuis hier matin. "
                .'Les habitants signalent un liquide trouble au robinet.',
            'address' => "3 place de l'École",
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
        ]);

        return ['B-01' => $b1];
    }

    /**
     * Groupe C — même zone que A, mais problèmes sans rapport.
     *
     * @return array<string, Incident>
     */
    private function seedGroupC(Zone $zone, ?User $citizen): array
    {
        [$latitude, $longitude] = $this->coordinates($zone, -0.0012, -0.0009);

        $c1 = $this->upsertIncident('C-01', now()->subDays(3)->setTime(11, 0), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'pression',
            'title' => self::TITLE_PREFIX.'Baisse de pression rue des Lilas',
            'description' => 'Pression insuffisante aux heures de pointe, surtout au dernier étage. '
                .'Les voisins constatent un débit faible depuis trois jours.',
            'address' => '18 rue des Lilas',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'moyenne',
            'status' => 'nouveau',
            'priority' => 'normale',
        ]);

        $c2 = $this->upsertIncident('C-02', now()->subDays(4)->setTime(16, 30), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => self::TITLE_PREFIX.'Fuite légère sur une canalisation',
            'description' => "Un mince filet d'eau s'écoule près du regard de la rue Voltaire. "
                ."L'écoulement reste faible et intermittent.",
            'address' => '9 rue Voltaire',
            'latitude' => round($latitude + 0.0015, 7),
            'longitude' => round($longitude - 0.0012, 7),
            'severity' => 'faible',
            'status' => 'nouveau',
            'priority' => 'basse',
        ]);

        $c3 = $this->upsertIncident('C-03', now()->subDays(5)->setTime(7, 45), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'autre',
            'title' => self::TITLE_PREFIX.'Bruit métallique dans le réseau',
            'description' => 'Un bruit de claquement se fait entendre dans les canalisations, '
                .'sans fuite visible ni baisse de pression.',
            'address' => '27 rue des Tilleuls',
            'latitude' => round($latitude - 0.0007, 7),
            'longitude' => round($longitude + 0.0014, 7),
            'severity' => 'faible',
            'status' => 'nouveau',
            'priority' => 'basse',
        ]);

        return ['C-01' => $c1, 'C-02' => $c2, 'C-03' => $c3];
    }

    /**
     * Groupe D — problème récurrent : quatre signalements de pression
     * similaires échelonnés sur dix jours dans la même zone.
     *
     * @return array<string, Incident>
     */
    private function seedGroupD(Zone $zone, ?User $citizen, ?User $manager): array
    {
        $rows = [
            'D-01' => [
                'reported_at' => now()->subDay()->setTime(9, 10),
                'title' => self::TITLE_PREFIX.'Baisse de pression persistante quartier des Tilleuls',
                'description' => "Depuis plusieurs jours, la pression de l'eau est très faible dans le quartier des Tilleuls. "
                    .'Les robinets du deuxième étage manquent de débit aux heures de pointe.',
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => [0.0005, 0.0004],
            ],
            'D-02' => [
                'reported_at' => now()->subDays(3)->setTime(18, 25),
                'title' => self::TITLE_PREFIX.'Pression très faible quartier des Tilleuls',
                'description' => "La pression de l'eau reste très faible dans le quartier des Tilleuls depuis plusieurs jours. "
                    .'Le débit des robinets est insuffisant aux heures de pointe.',
                'status' => 'en_cours',
                'assigned_to' => $manager?->id,
                'offset' => [0.0008, 0.0007],
            ],
            'D-03' => [
                'reported_at' => now()->subDays(6)->setTime(12, 40),
                'title' => self::TITLE_PREFIX.'Manque de pression récurrent quartier des Tilleuls',
                'description' => 'Les habitants du quartier des Tilleuls signalent un manque de pression récurrent depuis plusieurs jours. '
                    .'Le débit est faible aux heures de pointe, surtout en étage.',
                'status' => 'en_attente',
                'assigned_to' => $manager?->id,
                'offset' => [0.0011, 0.0003],
            ],
            'D-04' => [
                'reported_at' => now()->subDays(9)->setTime(8, 55),
                'title' => self::TITLE_PREFIX.'Débit faible et pression basse quartier des Tilleuls',
                'description' => "Le débit de l'eau est faible et la pression basse dans le quartier des Tilleuls depuis plusieurs jours. "
                    ."Les robinets manquent d'eau aux heures de pointe.",
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => [0.0003, 0.0009],
            ],
        ];

        $incidents = [];

        foreach ($rows as $key => $row) {
            [$latitude, $longitude] = $this->coordinates($zone, ...$row['offset']);

            $incidents[$key] = $this->upsertIncident($key, $row['reported_at'], [
                'user_id' => $citizen?->id,
                'zone_id' => $zone->id,
                'type' => 'pression',
                'title' => $row['title'],
                'description' => $row['description'],
                'address' => 'Quartier des Tilleuls',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'severity' => 'moyenne',
                'status' => $row['status'],
                'priority' => 'normale',
                'assigned_to' => $row['assigned_to'],
            ]);
        }

        $this->recordHistory(
            $incidents['D-02'],
            'nouveau',
            'en_cours',
            $incidents['D-02']->reported_at->copy()->addHours(3),
            'Équipe envoyée pour mesurer la pression.',
            actor: $manager,
        );

        $this->recordHistory(
            $incidents['D-03'],
            'nouveau',
            'en_cours',
            $incidents['D-03']->reported_at->copy()->addDay(),
            'Première visite technique effectuée.',
            actor: $manager,
        );

        $this->recordHistory(
            $incidents['D-03'],
            'en_cours',
            'en_attente',
            $incidents['D-03']->reported_at->copy()->addDays(2),
            'Attente du retour de la société de maintenance.',
            actor: $manager,
        );

        return $incidents;
    }

    /**
     * Groupe E — cas limites de la détection.
     *
     * @return array<string, Incident>
     */
    private function seedGroupE(Zone $zone, ?User $citizen, ?User $manager, Incident $reference): array
    {
        [$latitude, $longitude] = $this->coordinates($zone, 0.0008, 0.0005);

        $sameTitle = self::TITLE_PREFIX."Eau marron et odeur près de l'école";
        $sameDescription = "Eau marron et mauvaise odeur devant l'école primaire depuis hier matin. "
            .'Les habitants signalent un liquide trouble au robinet.';

        $e1 = $this->upsertIncident('E-01', now()->subDay()->setTime(7, 30), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => $sameTitle,
            'description' => $sameDescription,
            'address' => "12 rue de l'École",
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'haute',
            'status' => 'rejete',
            'priority' => 'haute',
            'rejection_reason' => 'Signalement en double avec un incident déjà pris en charge par une équipe.',
        ]);

        $e2 = $this->upsertIncident('E-02', now()->subDays(65)->setTime(10, 0), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => $sameTitle,
            'description' => $sameDescription,
            'address' => "12 rue de l'École",
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
        ]);

        $e3 = $this->upsertIncident('E-03', now()->subDay()->setTime(11, 50), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => $sameTitle,
            'description' => $sameDescription,
            'address' => "12 rue de l'École",
            'latitude' => round($latitude + 0.0001, 7),
            'longitude' => round($longitude, 7),
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
            'is_duplicate' => true,
            'duplicate_of_id' => $reference->id,
        ]);

        $e4 = $this->upsertIncident('E-04', now()->subDays(2)->setTime(15, 10), [
            'user_id' => $citizen?->id,
            'zone_id' => null,
            'infrastructure_id' => null,
            'type' => 'autre',
            'title' => self::TITLE_PREFIX.'Signalement sans localisation',
            'description' => "Je signale un problème d'eau mais je ne connais pas l'adresse exacte. "
                .'Le problème semble concerner le réseau du quartier.',
            'address' => null,
            'latitude' => null,
            'longitude' => null,
            'severity' => 'moyenne',
            'status' => 'nouveau',
            'priority' => 'normale',
        ]);

        $e5 = $this->upsertIncident('E-05', now()->subDays(2)->setTime(17, 5), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => self::TITLE_PREFIX.'Fuite',
            'description' => 'Ça coule.',
            'address' => 'Rue du Lavoir',
            'latitude' => round($latitude - 0.0009, 7),
            'longitude' => round($longitude + 0.0010, 7),
            'severity' => 'faible',
            'status' => 'nouveau',
            'priority' => 'basse',
        ]);

        $e6 = $this->upsertIncident('E-06', now()->subDays(3)->setTime(9, 25), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'autre',
            'title' => self::TITLE_PREFIX."Problème d'eau",
            'description' => 'Pas de détail.',
            'address' => null,
            'latitude' => round($latitude + 0.0013, 7),
            'longitude' => round($longitude - 0.0008, 7),
            'severity' => 'moyenne',
            'status' => 'nouveau',
            'priority' => 'normale',
        ]);

        $longDescription = "Après les travaux de voirie de la semaine dernière, plusieurs riverains de l'allée des Acacias "
            ."mentionnent un goût de plastique et une couleur jaune au robinet. L'odeur persiste quelques minutes après "
            ."avoir laissé couler l'eau. Le phénomène concerne surtout les logements situés au rez-de-chaussée et n'est pas "
            .'accompagné de baisse de pression. Personne n\'a constaté de fuite visible dans la rue. Les habitants demandent '
            ."un prélèvement de contrôle et une explication sur l'origine de cette odeur. Un premier relevé effectué hier "
            ."par le gardien de l'immeuble indique une eau claire après plusieurs minutes d'écoulement, mais la couleur "
            .'jaune réapparaît le lendemain matin. Le gardien a conservé un échantillon dans un flacon propre et propose '
            .'de le remettre à une équipe technique.';

        $e7 = $this->upsertIncident('E-07', now()->subDays(3)->setTime(13, 35), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'title' => self::TITLE_PREFIX.'Odeur persistante et eau colorée après travaux',
            'description' => $longDescription,
            'address' => '5 allée des Acacias',
            'latitude' => round($latitude + 0.0016, 7),
            'longitude' => round($longitude + 0.0006, 7),
            'severity' => 'moyenne',
            'status' => 'nouveau',
            'priority' => 'normale',
        ]);

        $this->recordHistory(
            $e1,
            'nouveau',
            'rejete',
            $e1->reported_at->copy()->addHour(),
            'Doublon identifié lors du tri.',
            rejectionReason: 'Signalement en double avec un incident déjà pris en charge par une équipe.',
            actor: $manager,
        );

        return [
            'E-01' => $e1,
            'E-02' => $e2,
            'E-03' => $e3,
            'E-04' => $e4,
            'E-05' => $e5,
            'E-06' => $e6,
            'E-07' => $e7,
        ];
    }

    /**
     * Groupe F — matière variée pour l'analyse (gravités, catégories,
     * informations manquantes). Aucune analyse n'est pré-calculée.
     *
     * @return array<string, Incident>
     */
    private function seedGroupF(Zone $zone, ?User $citizen, ?User $manager): array
    {
        $rows = [
            'F-01' => [
                'reported_at' => now()->subDays(2)->setTime(10, 30),
                'type' => 'qualite',
                'title' => self::TITLE_PREFIX.'Eau irisée et odeur de chlore au robinet',
                'description' => "L'eau présente des reflets irisés et une odeur de chlore prononcée au robinet de la cuisine. "
                    ."Le phénomène apparaît le matin et disparaît après quelques minutes d'écoulement.",
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => [0.0006, 0.0004],
            ],
            'F-02' => [
                'reported_at' => now()->subDays(4)->setTime(14, 10),
                'type' => 'fuite',
                'title' => self::TITLE_PREFIX."Fuite au pied d'une borne incendie",
                'description' => 'Une fuite continue s\'écoule au pied de la borne incendie située à l\'angle de la rue des Ateliers. '
                    .'La chaussée commence à se déformer.',
                'severity' => 'haute',
                'status' => 'en_cours',
                'assigned_to' => $manager?->id,
                'offset' => [-0.0008, 0.0009],
            ],
            'F-03' => [
                'reported_at' => now()->subDay()->setTime(8, 5),
                'type' => 'qualite',
                'title' => self::TITLE_PREFIX.'Déversement suspect près de la rivière',
                'description' => 'Un liquide huileux et une odeur suspecte ont été observés près de la rivière, '
                    .'en aval du rejet pluvial. Plusieurs poissons morts sont visibles sur la berge.',
                'severity' => 'critique',
                'status' => 'en_cours',
                'assigned_to' => $manager?->id,
                'offset' => [0.0014, -0.0011],
            ],
            'F-04' => [
                'reported_at' => now()->subDays(6)->setTime(19, 20),
                'type' => 'pression',
                'title' => self::TITLE_PREFIX.'Pression instable aux heures de pointe',
                'description' => 'La pression varie fortement entre 18 h et 20 h. '
                    .'Les douches perdent leur débit pendant plusieurs minutes, puis tout revient à la normale.',
                'severity' => 'faible',
                'status' => 'en_attente',
                'assigned_to' => $manager?->id,
                'offset' => [-0.0013, -0.0006],
            ],
            'F-05' => [
                'reported_at' => now()->subDays(3)->setTime(12, 0),
                'type' => 'qualite',
                'title' => self::TITLE_PREFIX.'Goût métallique signalé par plusieurs habitants',
                'description' => "Plusieurs habitants du secteur décrivent un goût métallique dans l'eau du robinet "
                    ."depuis le début de la semaine. Aucun changement de couleur n'est visible.",
                'severity' => 'haute',
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => [0.0009, 0.0012],
            ],
            'F-06' => [
                'reported_at' => now()->subDays(5)->setTime(16, 45),
                'type' => 'fuite',
                'title' => self::TITLE_PREFIX.'Fuite signalée sans adresse précise',
                'description' => 'Une fuite a été aperçue dans le secteur, mais je ne peux pas préciser '
                    ."l'emplacement exact. L'eau semble venir d'un regard sur le trottoir.",
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => null,
            ],
            'F-07' => [
                'reported_at' => now()->subDays(7)->setTime(9, 15),
                'type' => 'autre',
                'title' => self::TITLE_PREFIX."Aspect étrange de l'eau sans horaire précis",
                'description' => "L'eau a un aspect étrange depuis un moment. "
                    .'Je ne sais pas quand cela a commencé ni d\'où cela vient.',
                'severity' => 'faible',
                'status' => 'nouveau',
                'assigned_to' => null,
                'offset' => null,
            ],
        ];

        $incidents = [];

        foreach ($rows as $key => $row) {
            $coordinates = $row['offset'] === null
                ? [null, null]
                : $this->coordinates($zone, ...$row['offset']);

            $incidents[$key] = $this->upsertIncident($key, $row['reported_at'], [
                'user_id' => $citizen?->id,
                'zone_id' => $zone->id,
                'type' => $row['type'],
                'title' => $row['title'],
                'description' => $row['description'],
                'address' => $row['offset'] === null ? null : 'Secteur Est',
                'latitude' => $coordinates[0],
                'longitude' => $coordinates[1],
                'severity' => $row['severity'],
                'status' => $row['status'],
                'priority' => $this->priorityFor($row['severity']),
                'assigned_to' => $row['assigned_to'],
            ]);
        }

        $this->recordHistory(
            $incidents['F-02'],
            'nouveau',
            'en_cours',
            $incidents['F-02']->reported_at->copy()->addHours(2),
            'Équipe envoyée pour sécuriser la zone.',
            actor: $manager,
        );

        $this->recordHistory(
            $incidents['F-03'],
            'nouveau',
            'en_cours',
            $incidents['F-03']->reported_at->copy()->addHour(),
            'Signalement transmis au service environnement.',
            actor: $manager,
        );

        $this->recordHistory(
            $incidents['F-04'],
            'nouveau',
            'en_cours',
            $incidents['F-04']->reported_at->copy()->addHours(4),
            'Premier relevé de pression effectué.',
            actor: $manager,
        );

        $this->recordHistory(
            $incidents['F-04'],
            'en_cours',
            'en_attente',
            $incidents['F-04']->reported_at->copy()->addDay(),
            'Analyse du réducteur de pression en cours.',
            actor: $manager,
        );

        return $incidents;
    }

    /**
     * Groupe G — statuts mixtes avec historique cohérent.
     *
     * @return array<string, Incident>
     */
    private function seedGroupG(Zone $zone, ?User $citizen, ?User $manager): array
    {
        [$latitude, $longitude] = $this->coordinates($zone, 0.0005, 0.0004);

        $g1 = $this->upsertIncident('G-01', now()->subDays(2)->setTime(11, 30), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'coupure',
            'title' => self::TITLE_PREFIX."Coupure d'eau rue des Acacias",
            'description' => "Plus d'eau au robinet depuis ce matin dans plusieurs logements de la rue des Acacias. "
                .'Le voisinage est également concerné.',
            'address' => '4 rue des Acacias',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
        ]);

        $g2 = $this->upsertIncident('G-02', now()->subDays(5)->setTime(9, 40), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => self::TITLE_PREFIX.'Fuite sous chaussée avenue du Parc',
            'description' => 'Une fuite sous la chaussée provoque un affaissement léger devant le numéro 8. '
                .'La circulation est ralentie et une équipe est sur place.',
            'address' => '8 avenue du Parc',
            'latitude' => round($latitude - 0.0007, 7),
            'longitude' => round($longitude + 0.0008, 7),
            'severity' => 'haute',
            'status' => 'en_cours',
            'priority' => 'haute',
            'assigned_to' => $manager?->id,
        ]);

        $g3 = $this->upsertIncident('G-03', now()->subDays(8)->setTime(14, 15), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => self::TITLE_PREFIX.'Réparation en attente de pièce',
            'description' => "La réparation d'une canalisation est suspendue en attendant une pièce de raccord. "
                .'Un périmètre de sécurité est maintenu autour de la tranchée.',
            'address' => '21 rue des Peupliers',
            'latitude' => round($latitude + 0.0010, 7),
            'longitude' => round($longitude - 0.0005, 7),
            'severity' => 'moyenne',
            'status' => 'en_attente',
            'priority' => 'normale',
            'assigned_to' => $manager?->id,
        ]);

        $g4ReportedAt = now()->subDays(12)->setTime(8, 50);

        $g4 = $this->upsertIncident('G-04', $g4ReportedAt, [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'fuite',
            'title' => self::TITLE_PREFIX.'Canalisation réparée rue des Peupliers',
            'description' => 'Une canalisation percée a été remplacée rue des Peupliers. '
                .'Les habitants ont été informés d\'une coupure temporaire pendant les travaux.',
            'address' => '21 rue des Peupliers',
            'latitude' => round($latitude - 0.0011, 7),
            'longitude' => round($longitude - 0.0009, 7),
            'severity' => 'haute',
            'status' => 'traite',
            'priority' => 'haute',
            'assigned_to' => $manager?->id,
            'resolved_at' => $g4ReportedAt->copy()->addDay(),
            'resolution_note' => 'Tronçon remplacé et chaussée remise en état. Alimentation contrôlée.',
        ]);

        $g5 = $this->upsertIncident('G-05', now()->subDays(15)->setTime(16, 5), [
            'user_id' => $citizen?->id,
            'zone_id' => $zone->id,
            'type' => 'autre',
            'title' => self::TITLE_PREFIX.'Signalement non recevable',
            'description' => 'Un riverain signale un écoulement supposé, sans photo ni localisation précise. '
                ."Le contrôle sur place n'a révélé aucune anomalie.",
            'address' => null,
            'latitude' => round($latitude + 0.0003, 7),
            'longitude' => round($longitude - 0.0013, 7),
            'severity' => 'faible',
            'status' => 'rejete',
            'priority' => 'basse',
            'rejection_reason' => 'Signalement non recevable : aucun problème constaté lors du contrôle terrain.',
        ]);

        $this->recordHistory(
            $g2,
            'nouveau',
            'en_cours',
            $g2->reported_at->copy()->addHours(2),
            'Équipe de voirie envoyée sur place.',
            actor: $manager,
        );

        $this->recordHistory(
            $g3,
            'nouveau',
            'en_cours',
            $g3->reported_at->copy()->addHours(2),
            'Fuite localisée sous la chaussée.',
            actor: $manager,
        );

        $this->recordHistory(
            $g3,
            'en_cours',
            'en_attente',
            $g3->reported_at->copy()->addDay(),
            'Pièce de raccord commandée.',
            actor: $manager,
        );

        $this->recordHistory(
            $g4,
            'nouveau',
            'en_cours',
            $g4->reported_at->copy()->addHours(2),
            'Intervention programmée.',
            actor: $manager,
        );

        $this->recordHistory(
            $g4,
            'en_cours',
            'traite',
            $g4->reported_at->copy()->addDay(),
            'Travaux terminés.',
            resolutionNote: 'Tronçon remplacé et chaussée remise en état. Alimentation contrôlée.',
            actor: $manager,
        );

        $this->recordHistory(
            $g5,
            'nouveau',
            'rejete',
            $g5->reported_at->copy()->addHours(4),
            'Contrôle terrain sans anomalie.',
            rejectionReason: 'Signalement non recevable : aucun problème constaté lors du contrôle terrain.',
            actor: $manager,
        );

        return ['G-01' => $g1, 'G-02' => $g2, 'G-03' => $g3, 'G-04' => $g4, 'G-05' => $g5];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertIncident(string $key, Carbon $reportedAt, array $attributes): Incident
    {
        $incident = Incident::query()->firstOrNew([
            'reference' => self::REFERENCE_PREFIX.$key,
        ]);

        $incident->fill(array_merge([
            'user_id' => null,
            'zone_id' => null,
            'infrastructure_id' => null,
            'type' => 'autre',
            'title' => self::TITLE_PREFIX.$key,
            'description' => '',
            'address' => null,
            'latitude' => null,
            'longitude' => null,
            'severity' => 'moyenne',
            'status' => 'nouveau',
            'priority' => 'normale',
            'ai_category' => null,
            'ai_confidence' => null,
            'ai_summary' => null,
            'is_duplicate' => false,
            'duplicate_of_id' => null,
            'assigned_to' => null,
            'reported_at' => $reportedAt,
            'resolved_at' => null,
            'resolution_note' => null,
            'rejection_reason' => null,
        ], $attributes));

        $incident->reported_at = $reportedAt;
        $incident->created_at = $reportedAt->copy();
        $incident->updated_at = $reportedAt->copy();
        $incident->save();

        return $incident;
    }

    private function recordHistory(
        Incident $incident,
        string $from,
        string $to,
        Carbon $at,
        ?string $comment = null,
        ?string $resolutionNote = null,
        ?string $rejectionReason = null,
        ?User $actor = null,
    ): void {
        $history = IncidentStatusHistory::query()->firstOrNew([
            'incident_id' => $incident->id,
            'ancien_statut' => $from,
            'nouveau_statut' => $to,
        ]);

        $history->fill([
            'user_id' => $actor?->id,
            'comment' => $comment,
            'resolution_note' => $resolutionNote,
            'rejection_reason' => $rejectionReason,
        ]);

        $history->created_at = $at->copy();
        $history->updated_at = $at->copy();
        $history->save();
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function coordinates(Zone $zone, float $latitudeOffset, float $longitudeOffset): array
    {
        $latitude = is_numeric($zone->latitude) ? (float) $zone->latitude : 48.8566;
        $longitude = is_numeric($zone->longitude) ? (float) $zone->longitude : 2.3522;

        return [
            round($latitude + $latitudeOffset, 7),
            round($longitude + $longitudeOffset, 7),
        ];
    }

    private function priorityFor(string $severity): string
    {
        return match ($severity) {
            'critique' => 'urgente',
            'haute' => 'haute',
            'faible' => 'basse',
            default => 'normale',
        };
    }

    /**
     * @param  array<string, Incident>  $incidents
     */
    private function printSummary(array $incidents): void
    {
        $rows = [
            ['A', 'Quasi-doublons même zone : score local >= seuil', $this->describe($incidents, ['A-01', 'A-02', 'A-03'])],
            ['B', 'Même texte, autre zone : exclu des candidats de A', $this->describe($incidents, ['B-01'])],
            ['C', 'Même zone, problèmes différents : score < seuil', $this->describe($incidents, ['C-01', 'C-02', 'C-03'])],
            ['D', 'Problème récurrent : 4 incidents, 3 suggestions attendues', $this->describe($incidents, ['D-01', 'D-02', 'D-03', 'D-04'])],
            ['E', 'Cas limites : rejeté, hors fenêtre, doublon, sans zone, descriptions', $this->describe($incidents, ['E-01', 'E-02', 'E-03', 'E-04', 'E-05', 'E-06', 'E-07'])],
            ['F', 'Matière variée pour analyse, aucun résultat IA pré-créé', $this->describe($incidents, ['F-01', 'F-02', 'F-03', 'F-04', 'F-05', 'F-06', 'F-07'])],
            ['G', 'Statuts mixtes avec historique cohérent', $this->describe($incidents, ['G-01', 'G-02', 'G-03', 'G-04', 'G-05'])],
        ];

        $this->command?->info('IncidentAiDemoSeeder : '.count($incidents).' signalements de démonstration créés/actualisés.');
        $this->command?->table(['Groupe', 'Comportement attendu', 'Incidents (ID / référence)'], $rows);
        $this->command?->line('Aucune suggestion de similarité ni analyse IA n\'a été pré-créée.');
        $this->command?->line('Commandes utiles :');
        $this->command?->line('  php artisan incidents:detect-similar --days=20 --dry-run');
        $this->command?->line('  php artisan incidents:detect-similar --days=20');
        $this->command?->line('  php artisan ai:check');
    }

    /**
     * @param  array<string, Incident>  $incidents
     * @param  list<string>  $keys
     */
    private function describe(array $incidents, array $keys): string
    {
        return implode(', ', array_map(
            fn (string $key): string => $incidents[$key]->id.' ('.$incidents[$key]->reference.')',
            $keys,
        ));
    }
}
