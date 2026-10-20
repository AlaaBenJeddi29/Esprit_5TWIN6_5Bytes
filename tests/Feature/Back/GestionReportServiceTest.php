<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Services\GestionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GestionReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const OLLAMA_URL = 'http://127.0.0.1:11434';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();

        config([
            'services.ollama' => [
                'url' => self::OLLAMA_URL,
                'model' => 'llama3.2',
                'timeout' => 5,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(): array
    {
        return [
            'indicateurs' => [
                'nb_zones' => 2,
                'nb_infrastructures' => 4,
                'population_totale' => 3000,
                'risque_moyen' => 58.8,
                'nb_critiques' => 1,
                'pct_critiques' => 25.0,
                'nb_sans_score' => 0,
                'cout_total_6_mois' => 15000.0,
                'prevision_3_mois' => 15000.0,
            ],
            'zones' => [
                [
                    'nom' => 'Zone A',
                    'commune' => 'Tunis',
                    'population' => 1000,
                    'infrastructures' => 2,
                    'risque_moyen' => 57.5,
                    'critiques' => 1,
                    'score_zone' => 54.5,
                    'niveau' => 'modere',
                    'cout_6_mois' => 15000.0,
                    'prevision_3_mois' => 15000.0,
                ],
                [
                    'nom' => 'Zone B',
                    'commune' => 'Ariana',
                    'population' => 2000,
                    'infrastructures' => 2,
                    'risque_moyen' => 60.0,
                    'critiques' => 0,
                    'score_zone' => 36.0,
                    'niveau' => 'eleve',
                    'cout_6_mois' => 0.0,
                    'prevision_3_mois' => null,
                ],
            ],
            'top_infrastructures' => [
                [
                    'nom' => 'Réservoir A1',
                    'zone' => 'Zone A',
                    'type' => 'Réservoir',
                    'materiau' => 'Béton',
                    'age' => 20,
                    'statut' => 'Actif',
                    'score' => 90.0,
                ],
            ],
            'repartitions' => [
                'niveaux' => [
                    ['label' => 'Faible (0 – 30)', 'nombre' => 1, 'pourcentage' => 25.0],
                    ['label' => 'Modéré (31 – 60)', 'nombre' => 1, 'pourcentage' => 25.0],
                    ['label' => 'Élevé (61 – 80)', 'nombre' => 1, 'pourcentage' => 25.0],
                    ['label' => 'Critique (> 80)', 'nombre' => 1, 'pourcentage' => 25.0],
                    ['label' => 'Sans score', 'nombre' => 0, 'pourcentage' => 0.0],
                ],
                'types' => [],
                'materiaux' => [],
                'statuts' => [],
            ],
            'maintenances' => [
                'preventives' => 6,
                'correctives' => 0,
                'cout_preventif' => 15000.0,
                'cout_correctif' => 0.0,
                'couts_mensuels' => [],
                'couts_par_zone' => [],
            ],
            'anomalies' => [
                [
                    'infrastructure' => 'Réservoir A1',
                    'zone' => 'Zone A',
                    'date' => '2026-10-01',
                    'cout' => 10000.0,
                    'type' => 'Preventive',
                    'seuil' => 9208.2,
                ],
            ],
        ];
    }

    public function test_texte_ia_uses_ollama_and_caches_the_result(): void
    {
        Http::fake([
            self::OLLAMA_URL.'/api/generate' => Http::response(['response' => "Synthèse globale\nTexte généré par l'IA locale."]),
        ]);

        $service = new GestionReportService;

        $premier = $service->texteIA($this->donnees());
        $second = $service->texteIA($this->donnees());

        $this->assertSame('ollama', $premier['source']);
        $this->assertStringContainsString("Texte généré par l'IA locale.", $premier['texte']);
        $this->assertSame($premier, $second);

        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            $corps = $request->data();

            return $request->url() === self::OLLAMA_URL.'/api/generate'
                && $corps['model'] === 'llama3.2'
                && $corps['stream'] === false
                && $corps['options']['temperature'] === 0.3
                && str_contains($corps['prompt'], 'Synthèse globale')
                && str_contains($corps['prompt'], '"nombre_zones":2')
                && str_contains($corps['prompt'], 'Réservoir A1');
        });
    }

    public function test_texte_ia_falls_back_to_local_text_when_ollama_returns_an_error(): void
    {
        Http::fake([
            self::OLLAMA_URL.'/api/generate' => Http::response('Internal error', 500),
        ]);

        $resultat = (new GestionReportService)->texteIA($this->donnees());

        $this->assertSame('secours', $resultat['source']);
        $this->assertStringContainsString('Synthèse globale', $resultat['texte']);
        $this->assertStringContainsString('Analyse des risques par zone prioritaire', $resultat['texte']);
        $this->assertStringContainsString('Analyse des coûts de maintenance', $resultat['texte']);
        $this->assertStringContainsString('Recommandations', $resultat['texte']);
        $this->assertStringContainsString('Zone A', $resultat['texte']);
        $this->assertStringContainsString('15 000,00 DT', $resultat['texte']);
    }

    public function test_texte_ia_falls_back_when_ollama_returns_an_empty_response(): void
    {
        Http::fake([
            self::OLLAMA_URL.'/api/generate' => Http::response(['response' => '   ']),
        ]);

        $resultat = (new GestionReportService)->texteIA($this->donnees());

        $this->assertSame('secours', $resultat['source']);
        $this->assertStringContainsString('Recommandations', $resultat['texte']);
    }

    public function test_texte_ia_falls_back_when_ollama_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Ollama est éteint.'));

        $resultat = (new GestionReportService)->texteIA($this->donnees());

        $this->assertSame('secours', $resultat['source']);
        $this->assertStringContainsString('Le réseau couvre 2 zone(s) et 4 infrastructure(s)', $resultat['texte']);
    }

    public function test_donnees_aggregates_the_network_indicators(): void
    {
        $zoneA = Zone::factory()->create(['nom' => 'Zone A', 'commune' => 'Tunis', 'population' => 1000]);
        $zoneB = Zone::factory()->create(['nom' => 'Zone B', 'commune' => 'Ariana', 'population' => 2000]);

        $infraA1 = Infrastructure::factory()->create([
            'zone_id' => $zoneA->id,
            'nom' => 'Réservoir A1',
            'type' => 'réservoir',
            'materiau' => 'Béton',
            'statut' => 'actif',
            'score_risque' => 90,
            'date_installation' => null,
        ]);
        Infrastructure::factory()->create([
            'zone_id' => $zoneA->id,
            'nom' => 'Canalisation A2',
            'type' => 'canalisation',
            'score_risque' => 25,
            'date_installation' => null,
        ]);
        Infrastructure::factory()->create([
            'zone_id' => $zoneB->id,
            'nom' => 'Station B1',
            'type' => 'station_pompage',
            'score_risque' => 50,
            'date_installation' => null,
        ]);
        Infrastructure::factory()->create([
            'zone_id' => $zoneB->id,
            'nom' => 'Compteur B2',
            'type' => 'compteur',
            'score_risque' => 70,
            'date_installation' => null,
        ]);
        Infrastructure::factory()->create([
            'zone_id' => $zoneB->id,
            'nom' => 'Captage B3',
            'type' => 'captage',
            'score_risque' => 0,
            'date_installation' => null,
        ]);

        foreach ([1000, 1000, 1000, 1000, 1000, 10000] as $cout) {
            Maintenance::factory()->create([
                'infrastructure_id' => $infraA1->id,
                'type' => 'preventive',
                'cout' => $cout,
                'statut' => 'terminee',
                'date_intervention' => now()->startOfMonth()->addDay(),
            ]);
        }

        $donnees = (new GestionReportService)->donnees();
        $indicateurs = $donnees['indicateurs'];

        $this->assertSame(2, $indicateurs['nb_zones']);
        $this->assertSame(5, $indicateurs['nb_infrastructures']);
        $this->assertSame(3000, $indicateurs['population_totale']);
        $this->assertSame(47.0, $indicateurs['risque_moyen']);
        $this->assertSame(1, $indicateurs['nb_critiques']);
        $this->assertSame(20.0, $indicateurs['pct_critiques']);
        $this->assertSame(0, $indicateurs['nb_sans_score']);
        $this->assertSame(15000.0, $indicateurs['cout_total_6_mois']);
        $this->assertSame(15000.0, $indicateurs['prevision_3_mois']);

        $this->assertCount(2, $donnees['zones']);
        $this->assertSame('Zone A', $donnees['zones'][0]['nom']);
        $this->assertSame(54.5, $donnees['zones'][0]['score_zone']);
        $this->assertSame(24.0, $donnees['zones'][1]['score_zone']);

        $this->assertSame('Réservoir A1', $donnees['top_infrastructures'][0]['nom']);
        $this->assertSame(90.0, $donnees['top_infrastructures'][0]['score']);
        $this->assertCount(5, $donnees['top_infrastructures']);

        $niveaux = collect($donnees['repartitions']['niveaux'])->pluck('nombre', 'label')->all();
        $this->assertSame(2, $niveaux['Faible (0 – 30)']);
        $this->assertSame(1, $niveaux['Modéré (31 – 60)']);
        $this->assertSame(1, $niveaux['Élevé (61 – 80)']);
        $this->assertSame(1, $niveaux['Critique (> 80)']);
        $this->assertSame(0, $niveaux['Sans score']);

        $this->assertSame(6, $donnees['maintenances']['preventives']);
        $this->assertSame(0, $donnees['maintenances']['correctives']);
        $this->assertSame(15000.0, $donnees['maintenances']['cout_preventif']);
        $this->assertSame('Zone A', $donnees['maintenances']['couts_par_zone'][0]['zone']);

        $this->assertCount(1, $donnees['anomalies']);
        $this->assertSame('Réservoir A1', $donnees['anomalies'][0]['infrastructure']);
        $this->assertSame(10000.0, $donnees['anomalies'][0]['cout']);
        $this->assertSame('Zone A', $donnees['anomalies'][0]['zone']);
    }

    public function test_donnees_are_empty_safe_on_a_network_without_infrastructure(): void
    {
        Zone::factory()->create(['population' => 500]);

        $donnees = (new GestionReportService)->donnees();

        $this->assertSame(1, $donnees['indicateurs']['nb_zones']);
        $this->assertSame(0, $donnees['indicateurs']['nb_infrastructures']);
        $this->assertNull($donnees['indicateurs']['risque_moyen']);
        $this->assertSame(0.0, $donnees['indicateurs']['pct_critiques']);
        $this->assertSame(0.0, $donnees['indicateurs']['cout_total_6_mois']);
        $this->assertNull($donnees['indicateurs']['prevision_3_mois']);
        $this->assertNull($donnees['zones'][0]['score_zone']);
        $this->assertSame([], $donnees['top_infrastructures']);
        $this->assertSame([], $donnees['anomalies']);
    }
}
