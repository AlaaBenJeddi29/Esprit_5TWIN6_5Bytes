<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\GroqAIService;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityScorer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Jeu de démonstration déterministe pour la détection d'incidents similaires.
 *
 * Date de référence : 2026-10-15. Les références « INC-SIM-* » identifient
 * les seuls incidents gérés par ce seeder ; aucune autre ligne n'est modifiée
 * ni supprimée. Le seeder est idempotent (updateOrCreate par référence).
 *
 * Utilisation :
 *   php artisan db:seed --class=IncidentSimilarityDemoSeeder
 *
 * Pour un résultat strictement local et déterministe, désactiver l'IA :
 *   INCIDENT_SIMILARITY_AI_ENABLED=false php artisan db:seed --class=IncidentSimilarityDemoSeeder
 */
class IncidentSimilarityDemoSeeder extends Seeder
{
    public const DEMO_DATE = '2026-10-15';

    /**
     * @var array<string, string>
     */
    public const REFERENCES = [
        'a1' => 'INC-SIM-A-001',
        'a2' => 'INC-SIM-A-002',
        'a3' => 'INC-SIM-A-003',
        'b1' => 'INC-SIM-B-001',
        'b2' => 'INC-SIM-B-002',
        'b3' => 'INC-SIM-B-003',
        'c1' => 'INC-SIM-C-001',
        'c2' => 'INC-SIM-C-002',
        'd1' => 'INC-SIM-D-001',
        'd2' => 'INC-SIM-D-002',
        'e1' => 'INC-SIM-E-001',
        'e2' => 'INC-SIM-E-002',
        'e3' => 'INC-SIM-E-003',
        'e4' => 'INC-SIM-E-004',
        'e5' => 'INC-SIM-E-005',
        'e6' => 'INC-SIM-E-006',
    ];

    /**
     * @var list<string>
     */
    private const ZONE_NAMES = [
        'Centre-Ville',
        'Rives du Nord',
        'Zone Industrielle Est',
        'Quartier Sud',
    ];

    /**
     * @var list<string>
     */
    private const INFRASTRUCTURE_NAMES = [
        'Canalisation République',
        'Station de pompage Nord',
        'Canalisation secondaire Rue des Écoles',
        "Château d'eau des Rives",
        'Canalisation Principale Sud',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('IncidentSimilarityDemoSeeder ignoré : données de démonstration interdites en production.');

            return;
        }

        $this->call([ZoneSeeder::class, InfrastructureSeeder::class]);

        $zones = Zone::query()->whereIn('nom', self::ZONE_NAMES)->get()->keyBy('nom');
        $infrastructures = Infrastructure::query()
            ->whereIn('nom', self::INFRASTRUCTURE_NAMES)
            ->get()
            ->keyBy('nom');

        foreach (self::ZONE_NAMES as $zoneName) {
            if (! $zones->has($zoneName)) {
                throw new RuntimeException("Zone de démonstration introuvable : {$zoneName}.");
            }
        }

        foreach (self::INFRASTRUCTURE_NAMES as $infrastructureName) {
            if (! $infrastructures->has($infrastructureName)) {
                throw new RuntimeException("Infrastructure de démonstration introuvable : {$infrastructureName}.");
            }
        }

        $citizenId = User::query()
            ->where('role', UserRole::Citoyen)
            ->orderBy('id')
            ->value('id');

        $incidents = [];

        foreach ($this->demoRows() as $row) {
            $zone = $zones->get($row['zone']);
            $infrastructure = $row['infrastructure'] !== null
                ? $infrastructures->get($row['infrastructure'])
                : null;

            $incidents[$row['key']] = Incident::updateOrCreate(
                ['reference' => $row['reference']],
                [
                    'user_id' => $citizenId,
                    'zone_id' => $zone->id,
                    'infrastructure_id' => $infrastructure?->id,
                    'type' => $row['type'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'address' => $row['address'],
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'severity' => $row['severity'],
                    'status' => $row['status'],
                    'priority' => $this->priorityFor($row['severity']),
                    'ai_category' => $row['type'],
                    'ai_confidence' => $row['ai_confidence'],
                    'ai_summary' => 'Signalement de démonstration pour la détection de similarité.',
                    'is_duplicate' => false,
                    'duplicate_of_id' => null,
                    'assigned_to' => null,
                    'reported_at' => $row['reported_at'],
                    'resolved_at' => null,
                    'resolution_note' => null,
                    'rejection_reason' => $row['rejection_reason'] ?? null,
                ],
            );
        }

        $service = app(IncidentSimilarityService::class);

        foreach (['a1', 'b1', 'c1', 'd1', 'e1'] as $sourceKey) {
            $source = $incidents[$sourceKey];

            if ($source->status !== 'rejete') {
                $service->detect($source);
            }
        }

        $this->reportScores($incidents, $service, app(SimilarityScorer::class));

        $this->command?->info(sprintf(
            'IncidentSimilarityDemoSeeder : %d incidents de démonstration créés/actualisés (date de référence %s).',
            count($incidents),
            self::DEMO_DATE,
        ));
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse(self::DEMO_DATE.' '.$time);
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
     * @return list<array<string, mixed>>
     */
    private function demoRows(): array
    {
        return [
            [
                'key' => 'a1',
                'reference' => self::REFERENCES['a1'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => "Fuite d'eau importante rue des Écoles",
                'description' => "Une fuite d'eau importante est observée près de l'école. L'eau s'écoule continuellement sur la chaussée depuis ce matin.",
                'address' => '12 rue des Écoles, Zone Industrielle Est',
                'latitude' => 48.8450000,
                'longitude' => 2.4090000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('08:00'),
                'ai_confidence' => 92.0,
            ],
            [
                'key' => 'a2',
                'reference' => self::REFERENCES['a2'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => "Fuite d'eau rue des Écoles",
                'description' => "Une fuite d'eau est signalée près de l'école. L'eau coule sur la chaussée depuis ce matin.",
                'address' => '18 rue des Écoles, Zone Industrielle Est',
                'latitude' => 48.8455000,
                'longitude' => 2.4098000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('10:00'),
                'ai_confidence' => 91.0,
            ],
            [
                'key' => 'a3',
                'reference' => self::REFERENCES['a3'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => "Écoulement d'eau près de l'école",
                'description' => "Un écoulement d'eau continu est constaté sur la route à proximité de l'école depuis ce matin. Une fuite semble provenir de la rue des Écoles et l'eau s'écoule sur la chaussée.",
                'address' => 'Rue des Écoles, près de l\'école, Zone Industrielle Est',
                'latitude' => 48.8446000,
                'longitude' => 2.4084000,
                'severity' => 'haute',
                'status' => 'en_cours',
                'reported_at' => $this->at('12:00'),
                'ai_confidence' => 88.0,
            ],
            [
                'key' => 'b1',
                'reference' => self::REFERENCES['b1'],
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Station de pompage Nord',
                'type' => 'coupure',
                'title' => "Coupure d'eau dans le quartier",
                'description' => "Une coupure d'eau touche plusieurs foyers du quartier depuis ce matin. Les robinets sont à sec et la distribution est interrompue.",
                'address' => 'Quartier des Rives, près de la station de pompage',
                'latitude' => 48.8745000,
                'longitude' => 2.3640000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('07:30'),
                'ai_confidence' => 90.0,
            ],
            [
                'key' => 'b2',
                'reference' => self::REFERENCES['b2'],
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Station de pompage Nord',
                'type' => 'coupure',
                'title' => "Absence d'eau potable depuis ce matin",
                'description' => "Les habitants du quartier signalent une absence d'eau potable depuis ce matin. La distribution d'eau est interrompue et les robinets sont à sec.",
                'address' => '9 rue des Rives, Rives du Nord',
                'latitude' => 48.8748000,
                'longitude' => 2.3645000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('09:15'),
                'ai_confidence' => 89.0,
            ],
            [
                'key' => 'b3',
                'reference' => self::REFERENCES['b3'],
                'zone' => 'Rives du Nord',
                'infrastructure' => 'Station de pompage Nord',
                'type' => 'coupure',
                'title' => "Nouvelle interruption de la distribution d'eau",
                'description' => "Une nouvelle interruption de la distribution d'eau affecte le quartier. Les foyers sont privés d'eau potable depuis ce matin et les robinets sont à sec.",
                'address' => '21 avenue des Rives, Rives du Nord',
                'latitude' => 48.8742000,
                'longitude' => 2.3636000,
                'severity' => 'haute',
                'status' => 'en_cours',
                'reported_at' => $this->at('11:00'),
                'ai_confidence' => 87.0,
            ],
            [
                'key' => 'c1',
                'reference' => self::REFERENCES['c1'],
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
                'type' => 'fuite',
                'title' => "Fuite d'eau signalée sur la voie publique",
                'description' => "Une fuite d'eau est visible sur la voie publique devant le numéro 12. L'écoulement forme une flaque importante sur le trottoir.",
                'address' => '12 rue de la République, Centre-Ville',
                'latitude' => 48.8570000,
                'longitude' => 2.3540000,
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'reported_at' => $this->at('14:00'),
                'ai_confidence' => 86.0,
            ],
            [
                'key' => 'c2',
                'reference' => self::REFERENCES['c2'],
                'zone' => 'Rives du Nord',
                'infrastructure' => "Château d'eau des Rives",
                'type' => 'fuite',
                'title' => "Fuite d'eau signalée sur la voie publique",
                'description' => "Une fuite d'eau est visible sur la voie publique devant le numéro 12. L'écoulement forme une flaque importante sur le trottoir.",
                'address' => '12 rue de la République, Rives du Nord',
                'latitude' => 48.8740000,
                'longitude' => 2.3640000,
                'severity' => 'moyenne',
                'status' => 'en_attente',
                'reported_at' => $this->at('14:30'),
                'ai_confidence' => 85.0,
            ],
            [
                'key' => 'd1',
                'reference' => self::REFERENCES['d1'],
                'zone' => 'Quartier Sud',
                'infrastructure' => 'Canalisation Principale Sud',
                'type' => 'fuite',
                'title' => "Fuite d'eau avenue du Parc",
                'description' => "Une fuite d'eau importante s'écoule sur la chaussée devant le parc depuis ce matin.",
                'address' => '5 avenue du Parc, Quartier Sud',
                'latitude' => 48.8300000,
                'longitude' => 2.3410000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('15:00'),
                'ai_confidence' => 91.0,
            ],
            [
                'key' => 'd2',
                'reference' => self::REFERENCES['d2'],
                'zone' => 'Quartier Sud',
                'infrastructure' => 'Canalisation Principale Sud',
                'type' => 'qualite',
                'title' => 'Eau trouble et odeur de chlore',
                'description' => 'Les habitants signalent une eau trouble avec une forte odeur de chlore au robinet. Un prélèvement est nécessaire.',
                'address' => '8 avenue du Parc, Quartier Sud',
                'latitude' => 48.8303000,
                'longitude' => 2.3414000,
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'reported_at' => $this->at('15:30'),
                'ai_confidence' => 84.0,
            ],
            [
                'key' => 'e1',
                'reference' => self::REFERENCES['e1'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'pression',
                'title' => 'Baisse de pression rue des Lilas',
                'description' => "La pression de l'eau est insuffisante aux heures de pointe dans les étages élevés. Aucune fuite visible n'est signalée.",
                'address' => '22 rue des Lilas, Zone Industrielle Est',
                'latitude' => 48.8700000,
                'longitude' => 2.4300000,
                'severity' => 'faible',
                'status' => 'nouveau',
                'reported_at' => $this->at('16:00'),
                'ai_confidence' => 72.0,
            ],
            [
                'key' => 'e2',
                'reference' => self::REFERENCES['e2'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => "Fuite d'eau rue des Écoles",
                'description' => "Une fuite d'eau est signalée près de l'école. L'eau coule sur la chaussée depuis ce matin.",
                'address' => '18 rue des Écoles, Zone Industrielle Est',
                'latitude' => 48.8455000,
                'longitude' => 2.4098000,
                'severity' => 'haute',
                'status' => 'rejete',
                'reported_at' => $this->at('10:30'),
                'ai_confidence' => 80.0,
                'rejection_reason' => 'Signalement de démonstration marqué comme non recevable.',
            ],
            [
                'key' => 'e3',
                'reference' => self::REFERENCES['e3'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => "Fuite d'eau rue des Écoles",
                'description' => "Une fuite d'eau est signalée près de l'école. L'eau coule sur la chaussée depuis ce matin.",
                'address' => '18 rue des Écoles, Zone Industrielle Est',
                'latitude' => 48.8455000,
                'longitude' => 2.4098000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('10:00')->subDays(45),
                'ai_confidence' => 82.0,
            ],
            [
                'key' => 'e4',
                'reference' => self::REFERENCES['e4'],
                'zone' => 'Centre-Ville',
                'infrastructure' => 'Canalisation République',
                'type' => 'fuite',
                'title' => "Fuite d'eau rue des Écoles",
                'description' => "Une fuite d'eau est signalée près de l'école. L'eau coule sur la chaussée depuis ce matin.",
                'address' => '18 rue des Écoles, Centre-Ville',
                'latitude' => 48.8570000,
                'longitude' => 2.3540000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('10:30'),
                'ai_confidence' => 83.0,
            ],
            [
                'key' => 'e5',
                'reference' => self::REFERENCES['e5'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'autre',
                'title' => "Débordement d'un regard d'égout",
                'description' => "Un regard d'égout déborde après les fortes pluies, sans lien avec le réseau d'eau potable.",
                'address' => 'Rue des Écoles, Zone Industrielle Est',
                'latitude' => null,
                'longitude' => null,
                'severity' => 'moyenne',
                'status' => 'nouveau',
                'reported_at' => $this->at('16:30'),
                'ai_confidence' => 68.0,
            ],
            [
                'key' => 'e6',
                'reference' => self::REFERENCES['e6'],
                'zone' => 'Zone Industrielle Est',
                'infrastructure' => 'Canalisation secondaire Rue des Écoles',
                'type' => 'fuite',
                'title' => 'Fuite après compteur dans une cave',
                'description' => "Le propriétaire signale une petite fuite après le compteur, dans la cave de l'immeuble. Le réseau public n'est pas concerné.",
                'address' => '3 rue des Écoles, Zone Industrielle Est',
                'latitude' => 48.8452000,
                'longitude' => 2.4092000,
                'severity' => 'haute',
                'status' => 'nouveau',
                'reported_at' => $this->at('17:00'),
                'ai_confidence' => 79.0,
            ],
        ];
    }

    /**
     * @param  array<string, Incident>  $incidents
     */
    private function reportScores(array $incidents, IncidentSimilarityService $service, SimilarityScorer $scorer): void
    {
        $pairs = [
            ['A', 'a1', 'a2'],
            ['A', 'a1', 'a3'],
            ['B', 'b1', 'b2'],
            ['B', 'b1', 'b3'],
            ['C', 'c1', 'c2'],
            ['D', 'd1', 'd2'],
            ['E', 'a1', 'e1'],
            ['E', 'a1', 'e2'],
            ['E', 'a1', 'e3'],
            ['E', 'a1', 'e4'],
            ['E', 'a1', 'e5'],
            ['E', 'a1', 'e6'],
        ];

        $candidateIds = [];

        foreach (array_unique(array_column($pairs, 1)) as $sourceKey) {
            $candidateIds[$sourceKey] = $service->candidates($incidents[$sourceKey])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $rows = [];
        $aiNotes = [];

        foreach ($pairs as [$scenario, $sourceKey, $candidateKey]) {
            $source = $incidents[$sourceKey];
            $candidate = $incidents[$candidateKey];
            $detail = $scorer->explain($source, $candidate);

            $sqlIncluded = in_array((int) $candidate->id, $candidateIds[$sourceKey], true);

            $persisted = IncidentSimilarity::query()
                ->where('incident_id', $source->id)
                ->where('similar_incident_id', $candidate->id)
                ->first();

            $rows[] = [
                $scenario,
                $source->reference,
                $candidate->reference,
                'Z'.$source->zone_id.'/I'.($source->infrastructure_id ?? '—'),
                'Z'.$candidate->zone_id.'/I'.($candidate->infrastructure_id ?? '—'),
                $sqlIncluded ? 'oui' : 'non',
                number_format($detail['text_similarity'], 3, ',', ' '),
                $detail['text'],
                $detail['zone'],
                $detail['type'],
                $detail['severity'],
                $detail['geography'],
                $detail['time'],
                $detail['total'],
                $persisted ? 'oui' : 'non',
                $this->aiLabel($persisted),
            ];

            if ($persisted?->ai_justification) {
                $aiNotes[] = sprintf(
                    '%s → %s : %s',
                    $source->reference,
                    $candidate->reference,
                    $persisted->ai_justification,
                );
            }
        }

        $this->command?->info('Détail des scores locaux (date de référence '.self::DEMO_DATE.') :');
        $this->command?->table([
            'Scénario',
            'Source',
            'Candidat',
            'Zone/Infra source',
            'Zone/Infra candidat',
            'SQL',
            'Jaccard',
            'Texte',
            'Zone',
            'Type',
            'Gravité',
            'Géo',
            'Temps',
            'Score',
            'Persistée',
            'IA',
        ], $rows);

        $aiEnabled = app(GroqAIService::class)->enabled();
        $this->command?->line($aiEnabled
            ? 'IA activée : les scores et statuts restent locaux, l’avis Grok est purement indicatif.'
            : 'IA désactivée ou sans clé : aucune requête externe effectuée.');

        foreach ($aiNotes as $note) {
            $this->command?->line('  '.$note);
        }
    }

    private function aiLabel(?IncidentSimilarity $similarity): string
    {
        if ($similarity === null) {
            return '—';
        }

        return match ($similarity->method) {
            IncidentSimilarity::METHOD_LOCAL_AI => 'positive',
            IncidentSimilarity::METHOD_LOCAL_AI_NEGATIVE => 'négative',
            IncidentSimilarity::METHOD_LOCAL_AI_UNAVAILABLE => 'indisponible',
            default => 'non appelée',
        };
    }
}
