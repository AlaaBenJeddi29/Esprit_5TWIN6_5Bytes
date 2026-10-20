<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use App\Services\QualityAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QualityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
        ]);
        Http::preventStrayRequests();
    }

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    /**
     * @param  list<string>  $anomalies
     */
    private function fakeGeminiSummary(string $resume, array $anomalies = []): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'resume' => $resume,
                                'anomalies' => $anomalies,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ]),
        ]);
    }

    public function test_citizen_cannot_access_quality_back_office(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->get(route('back.quality-samples.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.quality-thresholds.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.quality-alerts.index'))->assertForbidden();
    }

    public function test_gestionnaire_can_view_quality_lists(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->get(route('back.quality-samples.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.quality-thresholds.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.quality-alerts.index'))->assertOk();
    }

    public function test_gestionnaire_can_create_a_threshold(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('back.quality-thresholds.store'), [
                'zone_id' => $zone->id,
                'parametre' => 'turbidite',
                'unite' => 'NTU',
                'valeur_min' => 0,
                'valeur_max' => 5,
                'actif' => 1,
            ])
            ->assertRedirect(route('back.quality-thresholds.index'));

        $this->assertDatabaseHas('quality_thresholds', [
            'zone_id' => $zone->id,
            'parametre' => 'turbidite',
            'actif' => 1,
        ]);
    }

    public function test_threshold_validation_rejects_duplicate_and_incoherent_bounds(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'ph',
            'valeur_min' => 6.5,
            'valeur_max' => 8.5,
        ]);

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-thresholds.create'))
            ->post(route('back.quality-thresholds.store'), [
                'zone_id' => $zone->id,
                'parametre' => 'ph',
                'unite' => 'pH',
                'valeur_min' => 9,
                'valeur_max' => 7,
            ])
            ->assertSessionHasErrors(['parametre', 'valeur_min']);

        $this->assertDatabaseCount('quality_thresholds', 1);
    }

    public function test_sample_analysis_marks_non_conformity_and_creates_alert(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $response = $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'infrastructure_id' => null,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'source' => 'Réseau',
            'notes' => 'Contrôle de routine.',
            'parametres' => [
                'ph' => ['valeur' => 7.2],
                'turbidite' => ['valeur' => 9.5],
            ],
        ]);

        $sample = WaterQualitySample::firstOrFail();

        $response->assertRedirect(route('back.quality-samples.show', $sample));
        $this->assertSame('non_conforme', $sample->statut);
        $this->assertEquals(50, (float) $sample->score);
        $this->assertSame(2, WaterQualityParameter::count());
        $this->assertDatabaseHas('water_quality_parameters', [
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'est_conforme' => 0,
        ]);
        $this->assertDatabaseHas('water_quality_alerts', [
            'zone_id' => $zone->id,
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'statut' => 'active',
        ]);
    }

    public function test_sample_analysis_marks_conformity_without_alert(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'ph' => ['valeur' => 7.4],
                'turbidite' => ['valeur' => 1.2],
                'chlore' => ['valeur' => 0.4],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->assertSame('conforme', $sample->statut);
        $this->assertEquals(100, (float) $sample->score);
        $this->assertSame(0, WaterQualityAlert::count());
    }

    public function test_sample_requires_at_least_one_parameter(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.create'))
            ->post(route('back.quality-samples.store'), [
                'zone_id' => $zone->id,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'parametres' => [],
            ])
            ->assertSessionHasErrors(['parametres']);

        $this->assertDatabaseCount('water_quality_samples', 0);
    }

    public function test_gestionnaire_can_resolve_and_delete_an_alert(): void
    {
        $gestionnaire = $this->gestionnaire();
        $alert = WaterQualityAlert::factory()->create();

        $this->actingAs($gestionnaire)
            ->patch(route('back.quality-alerts.update', $alert), ['statut' => 'resolue'])
            ->assertRedirect();

        $alert->refresh();

        $this->assertSame('resolue', $alert->statut);
        $this->assertNotNull($alert->resolue_at);

        $this->actingAs($gestionnaire)
            ->delete(route('back.quality-alerts.destroy', $alert))
            ->assertRedirect();

        $this->assertDatabaseMissing('water_quality_alerts', ['id' => $alert->id]);
    }

    public function test_deleting_sample_cascades_parameters_but_keeps_alerts(): void
    {
        $gestionnaire = $this->gestionnaire();
        $sample = WaterQualitySample::factory()->nonConforme()->create();
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);
        $alert = WaterQualityAlert::factory()->create(['sample_id' => $sample->id, 'zone_id' => $sample->zone_id]);

        $this->actingAs($gestionnaire)
            ->delete(route('back.quality-samples.destroy', $sample))
            ->assertRedirect(route('back.quality-samples.index'));

        $this->assertDatabaseMissing('water_quality_samples', ['id' => $sample->id]);
        $this->assertDatabaseMissing('water_quality_parameters', ['sample_id' => $sample->id]);
        $this->assertDatabaseHas('water_quality_alerts', ['id' => $alert->id, 'sample_id' => null]);
    }

    public function test_public_quality_pages_are_available(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Zone Publique']);
        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityAlert::factory()->create(['zone_id' => $zone->id, 'sample_id' => $sample->id]);

        $this->get(route('public.quality.index'))->assertOk()->assertSee('Zone Publique');
        $this->get(route('public.quality.zone', $zone))->assertOk()->assertSee('Zone Publique');
    }

    public function test_ai_detects_anomalies_and_writes_a_citizen_summary(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Centre']);

        QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'turbidite',
            'unite' => 'NTU',
            'valeur_min' => 0,
            'valeur_max' => 5,
        ]);

        $this->fakeGeminiSummary(
            'La turbidité mesurée est supérieure au seuil autorisé. Une vérification par les responsables est recommandée.',
            ['Turbidité élevée : 9,5 NTU pour un maximum de 5 NTU.'],
        );

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'ph' => ['valeur' => 7.2],
                'turbidite' => ['valeur' => 9.5],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($request->url(), (string) config('services.gemini.model'))
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && str_contains($prompt, 'Turbidité')
                && str_contains($prompt, '"valeur": 9.5')
                && str_contains($prompt, '"seuil_max": 5')
                && str_contains($prompt, '"origine_seuil": "base_de_donnees"');
        });

        $response = $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample));

        $response->assertOk()
            ->assertSee('Analyse intelligente de la qualité de l\'eau', false)
            ->assertSee('La turbidité mesurée est supérieure au seuil autorisé.')
            ->assertSee('Turbidité élevée : 9,5 NTU pour un maximum de 5 NTU.');
    }

    public function test_ai_summary_states_when_no_anomaly_is_detected(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'ph',
            'unite' => 'pH',
            'valeur_min' => 6.5,
            'valeur_max' => 8.5,
        ]);

        $this->fakeGeminiSummary(
            "Les paramètres mesurés respectent les seuils configurés. Aucune anomalie n'a été détectée lors de ce contrôle.",
        );

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'ph' => ['valeur' => 7.2],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee('Les paramètres mesurés respectent les seuils configurés.')
            ->assertSee('Aucune anomalie détectée par l\'analyse intelligente.', false);
    }

    public function test_analysis_continues_when_gemini_is_unavailable(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Service unavailable', 503),
        ]);

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'turbidite' => ['valeur' => 9.5],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->assertSame('non_conforme', $sample->statut);
        $this->assertDatabaseHas('water_quality_alerts', [
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'statut' => 'active',
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee("Le résumé intelligent n'est pas disponible pour ce prélèvement.", false);
    }

    public function test_analysis_continues_when_gemini_times_out(): void
    {
        Http::fake(fn () => throw new ConnectionException('Timeout simulé.'));

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'turbidite' => ['valeur' => 9.5],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->assertSame('non_conforme', $sample->statut);
        $this->assertSame(1, WaterQualityAlert::where('sample_id', $sample->id)->count());
    }

    public function test_analysis_continues_when_gemini_returns_invalid_json(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'réponse non json']]],
                ]],
            ]),
        ]);

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)->post(route('back.quality-samples.store'), [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'ph' => ['valeur' => 7.2],
            ],
        ])->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee("Le résumé intelligent n'est pas disponible pour ce prélèvement.", false);
    }

    public function test_gemini_summary_is_generated_when_opening_the_detail_page(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);

        $this->fakeGeminiSummary(
            'Résumé généré à l’ouverture de la fiche.',
            ['Turbidité élevée : 9,5 NTU pour un maximum de 5 NTU.'],
        );

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee('Résumé généré à l’ouverture de la fiche.')
            ->assertSee('Turbidité élevée : 9,5 NTU pour un maximum de 5 NTU.');

        Http::assertSentCount(1);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee('Résumé généré à l’ouverture de la fiche.');

        Http::assertSentCount(1);
    }

    public function test_gemini_summary_is_skipped_without_api_key(): void
    {
        config(['services.gemini.api_key' => null]);
        Http::fake();

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee("Le résumé intelligent n'est pas disponible pour ce prélèvement.", false);

        Http::assertNothingSent();
    }

    public function test_gemini_rejections_and_unknown_model_fall_back_gracefully(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);

        foreach ([401, 403, 404, 429] as $status) {
            Cache::flush();

            Http::fake([
                'generativelanguage.googleapis.com/*' => Http::response([
                    'error' => [
                        'status' => $status === 401 ? 'UNAUTHENTICATED' : 'ERROR',
                        'message' => 'Erreur simulée.',
                    ],
                ], $status),
            ]);

            $this->actingAs($gestionnaire)
                ->get(route('back.quality-samples.show', $sample))
                ->assertOk()
                ->assertSee("Le résumé intelligent n'est pas disponible pour ce prélèvement.", false);

            $this->assertSame('non_conforme', $sample->fresh()->statut);
            $this->assertSame(1, $sample->parameters()->count());
        }
    }

    public function test_malformed_gemini_payload_falls_back_gracefully(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => []]]],
            ]),
        ]);

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->nonConforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->nonConforme()->create(['sample_id' => $sample->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.show', $sample))
            ->assertOk()
            ->assertSee("Le résumé intelligent n'est pas disponible pour ce prélèvement.", false);

        $this->assertSame(1, $sample->parameters()->count());
    }

    public function test_repeated_analysis_does_not_duplicate_active_alerts_or_api_calls(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'turbidite',
            'unite' => 'NTU',
            'valeur_min' => 0,
            'valeur_max' => 5,
        ]);

        $this->fakeGeminiSummary('Une vérification est recommandée.', ['Turbidité élevée.']);

        $payload = [
            'zone_id' => $zone->id,
            'date_prelevement' => now()->format('Y-m-d H:i:s'),
            'parametres' => [
                'turbidite' => ['valeur' => 9.5],
            ],
        ];

        $this->actingAs($gestionnaire)
            ->post(route('back.quality-samples.store'), $payload)
            ->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();

        $this->assertSame(1, WaterQualityAlert::where('sample_id', $sample->id)->where('statut', 'active')->count());
        Http::assertSentCount(1);

        $this->actingAs($gestionnaire)
            ->put(route('back.quality-samples.update', $sample), $payload)
            ->assertRedirect();

        $this->assertSame(1, WaterQualityAlert::where('sample_id', $sample->id)->where('statut', 'active')->count());
        $this->assertSame(1, WaterQualityParameter::where('sample_id', $sample->id)->count());
        Http::assertSentCount(1);
    }

    public function test_quality_dashboard_displays_real_statistics_and_parameter_trends(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Test']);

        $conforme = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-01 09:30:00',
            'score' => 100,
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $conforme->id,
            'parametre' => 'ph',
            'valeur' => 7.2,
            'unite' => 'pH',
            'est_conforme' => true,
        ]);

        $nonConforme = WaterQualitySample::factory()->nonConforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-05 14:15:00',
            'score' => 50,
        ]);
        WaterQualityParameter::factory()->nonConforme()->create([
            'sample_id' => $nonConforme->id,
            'parametre' => 'turbidite',
            'valeur' => 9.5,
            'unite' => 'NTU',
            'est_conforme' => false,
        ]);

        WaterQualityAlert::factory()->create([
            'sample_id' => $nonConforme->id,
            'zone_id' => $zone->id,
            'statut' => 'active',
        ]);
        WaterQualityAlert::factory()->resolue()->create([
            'sample_id' => $nonConforme->id,
            'zone_id' => $zone->id,
        ]);

        $dernierConforme = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-08 11:00:00',
            'score' => 100,
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $dernierConforme->id,
            'parametre' => 'ph',
            'valeur' => 7.4,
            'unite' => 'pH',
            'est_conforme' => true,
        ]);

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => $zone->id,
        ]));

        $response->assertOk()
            ->assertSee('Tableau de bord qualité')
            ->assertSee('Évolution par périmètre')
            ->assertSee('Historique chronologique')
            ->assertSee('En amélioration')
            ->assertSee('Turbidité')
            ->assertSee('NTU')
            ->assertSee('01/10/2026')
            ->assertSee('05/10/2026')
            ->assertSee('08/10/2026')
            ->assertSee('Seuils appliqués')
            ->assertSee('<polyline', false);

        $response->assertViewHas('stats', fn (array $stats) => $stats['total'] === 3
            && $stats['conformes'] === 2
            && $stats['non_conformes'] === 1
            && $stats['alertes_actives'] === 1
            && $stats['dernier_prelevement']?->format('Y-m-d H:i') === '2026-10-08 11:00');

        $response->assertViewHas('perimetre', fn (?Zone $perimetre) => $perimetre?->id === $zone->id);
        $response->assertViewHas('trendDisponible', true);

        $response->assertViewHas('perimetreTrends', function (array $trends) {
            $codes = array_column($trends, 'parametre');

            return $codes === ['ph', 'turbidite']
                && count($trends[0]['points']) === 2
                && $trends[0]['points'][0]['valeur'] === 7.2
                && $trends[0]['points'][1]['valeur'] === 7.4
                && $trends[1]['unite'] === 'NTU'
                && $trends[1]['points'][0]['valeur'] === 9.5
                && $trends[1]['points'][0]['conforme'] === false;
        });

        $response->assertViewHas('perimetreHistory', fn ($history) => $history->pluck('reference')->all() === [
            $conforme->reference,
            $nonConforme->reference,
            $dernierConforme->reference,
        ]);
    }

    public function test_quality_dashboard_handles_an_empty_database(): void
    {
        $gestionnaire = $this->gestionnaire();

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index'));

        $response->assertOk()
            ->assertSee('Aucun périmètre sélectionné')
            ->assertSee('Aucun prélèvement')
            ->assertDontSee('<polyline', false);

        $response->assertViewHas('stats', fn (array $stats) => $stats['total'] === 0
            && $stats['conformes'] === 0
            && $stats['non_conformes'] === 0
            && $stats['alertes_actives'] === 0
            && $stats['dernier_prelevement'] === null);

        $response->assertViewHas('perimetre', fn ($perimetre) => $perimetre === null);
        $response->assertViewHas('perimetreTrends', fn (array $trends) => $trends === []);
        $response->assertViewHas('perimetreHistory', fn ($history) => $history->isEmpty());
        $response->assertViewHas('trendDisponible', false);
    }

    public function test_quality_evolution_is_scoped_to_the_selected_perimetre(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zoneA = Zone::factory()->create(['nom' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['nom' => 'Zone Bêta']);

        $sampleA = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zoneA->id,
            'date_prelevement' => '2026-10-02 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $sampleA->id,
            'parametre' => 'ph',
            'valeur' => 7.2,
            'unite' => 'pH',
            'est_conforme' => true,
        ]);

        $sampleB = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zoneB->id,
            'date_prelevement' => '2026-10-03 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $sampleB->id,
            'parametre' => 'plomb',
            'valeur' => 2.0,
            'unite' => 'µg/L',
            'est_conforme' => true,
        ]);

        $responseA = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => $zoneA->id,
        ]));

        $responseA->assertOk()
            ->assertSee('Zone Alpha')
            ->assertDontSee('Plomb')
            ->assertDontSee('µg/L');

        $responseA->assertViewHas('perimetre', fn (?Zone $perimetre) => $perimetre?->id === $zoneA->id);
        $responseA->assertViewHas('perimetreTrends', fn (array $trends) => array_column($trends, 'parametre') === ['ph']);
        $responseA->assertViewHas('perimetreHistory', fn ($history) => $history->pluck('zone_id')->unique()->all() === [$zoneA->id]);

        $responseB = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => $zoneB->id,
        ]));

        $responseB->assertOk();

        $responseB->assertViewHas('perimetre', fn (?Zone $perimetre) => $perimetre?->id === $zoneB->id);
        $responseB->assertViewHas('perimetreTrends', fn (array $trends) => array_column($trends, 'parametre') === ['plomb']);
        $responseB->assertViewHas('perimetreHistory', fn ($history) => $history->pluck('zone_id')->unique()->all() === [$zoneB->id]);
    }

    public function test_quality_evolution_ignores_unknown_perimetre_selection(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->conforme()->create(['zone_id' => $zone->id]);
        WaterQualityParameter::factory()->create(['sample_id' => $sample->id]);

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => 999999,
        ]));

        $response->assertOk()
            ->assertSee('Aucun périmètre sélectionné')
            ->assertDontSee('<polyline', false);

        $response->assertViewHas('perimetre', fn ($perimetre) => $perimetre === null);
        $response->assertViewHas('perimetreTrends', fn (array $trends) => $trends === []);
        $response->assertViewHas('perimetreHistory', fn ($history) => $history->isEmpty());
    }

    public function test_quality_evolution_handles_perimetre_without_any_sample(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Sans Prélèvement']);

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => $zone->id,
        ]));

        $response->assertOk()
            ->assertSee('Aucun prélèvement pour ce périmètre')
            ->assertSee('Tendance non déterminable')
            ->assertDontSee('<polyline', false);

        $response->assertViewHas('perimetreTrends', fn (array $trends) => $trends === []);
        $response->assertViewHas('perimetreHistory', fn ($history) => $history->isEmpty());
        $response->assertViewHas('trendDisponible', false);
    }

    public function test_quality_trend_is_not_reported_without_enough_analysed_samples(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        WaterQualitySample::factory()->nonConforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-01 08:00:00',
            'score' => 40,
        ]);
        WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-02 08:00:00',
            'score' => 100,
        ]);

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'perimetre' => $zone->id,
        ]));

        $response->assertOk()
            ->assertSee('Tendance non déterminable')
            ->assertDontSee('En amélioration')
            ->assertDontSee('En dégradation');

        $response->assertViewHas('trendDisponible', false);
    }

    public function test_quality_back_office_exposes_pdf_report_controls(): void
    {
        $gestionnaire = $this->gestionnaire();
        Zone::factory()->create(['nom' => 'Zone PDF']);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.index'))
            ->assertOk()
            ->assertSee('Rapport PDF par zone')
            ->assertSee('Générer un rapport PDF')
            ->assertSee('Zone PDF')
            ->assertSee('name="pdf" value="1"', false);
    }

    public function test_gestionnaire_can_download_a_zone_pdf_report(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Rapport']);

        $sample = WaterQualitySample::factory()->nonConforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-06 10:30:00',
        ]);
        WaterQualityParameter::factory()->nonConforme()->create([
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'valeur' => 9.5,
            'unite' => 'NTU',
            'seuil_min' => 0,
            'seuil_max' => 5,
        ]);
        WaterQualityAlert::factory()->create([
            'sample_id' => $sample->id,
            'zone_id' => $zone->id,
            'parametre' => 'turbidite',
            'statut' => 'active',
        ]);

        $response = $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'pdf' => 1,
            'perimetre' => $zone->id,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('rapport-qualite-zone-rapport-'.now()->format('Ymd').'.pdf');

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertGreaterThan(1000, strlen((string) $response->getContent()));
    }

    public function test_pdf_report_requires_a_valid_zone(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.index'))
            ->get(route('back.quality-samples.index', ['pdf' => 1]))
            ->assertRedirect(route('back.quality-samples.index'))
            ->assertSessionHasErrors('perimetre');

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.index'))
            ->get(route('back.quality-samples.index', ['pdf' => 1, 'perimetre' => 999999]))
            ->assertRedirect(route('back.quality-samples.index'))
            ->assertSessionHasErrors('perimetre');
    }

    public function test_pdf_report_is_refused_for_a_zone_without_samples(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Vide PDF']);

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.index'))
            ->get(route('back.quality-samples.index', ['pdf' => 1, 'perimetre' => $zone->id]))
            ->assertRedirect(route('back.quality-samples.index', ['perimetre' => $zone->id]))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Aucun prélèvement'));
    }

    public function test_quality_pdf_report_data_is_scoped_to_the_selected_zone(): void
    {
        $zoneA = Zone::factory()->create(['nom' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['nom' => 'Zone Bêta']);

        $olderA = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zoneA->id,
            'date_prelevement' => '2026-10-01 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $olderA->id,
            'parametre' => 'ph',
            'valeur' => 7.2,
            'unite' => 'pH',
            'seuil_min' => 6.5,
            'seuil_max' => 8.5,
            'est_conforme' => true,
        ]);

        $newerA = WaterQualitySample::factory()->nonConforme()->create([
            'zone_id' => $zoneA->id,
            'date_prelevement' => '2026-10-04 09:15:00',
        ]);
        WaterQualityParameter::factory()->nonConforme()->create([
            'sample_id' => $newerA->id,
            'parametre' => 'turbidite',
            'valeur' => 9.5,
            'unite' => 'NTU',
            'seuil_min' => 0,
            'seuil_max' => 5,
        ]);
        WaterQualityAlert::factory()->create([
            'sample_id' => $newerA->id,
            'zone_id' => $zoneA->id,
            'parametre' => 'turbidite',
            'statut' => 'active',
        ]);

        $sampleB = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zoneB->id,
            'date_prelevement' => '2026-10-02 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $sampleB->id,
            'parametre' => 'plomb',
            'valeur' => 2.0,
            'unite' => 'µg/L',
            'est_conforme' => true,
        ]);
        WaterQualityAlert::factory()->create([
            'sample_id' => $sampleB->id,
            'zone_id' => $zoneB->id,
            'parametre' => 'plomb',
        ]);

        $report = app(QualityAnalyzer::class)->zoneReport($zoneA);

        $this->assertSame([$olderA->reference, $newerA->reference], $report['history']->pluck('reference')->all());
        $this->assertSame([$zoneA->id], $report['history']->pluck('zone_id')->unique()->values()->all());
        $this->assertSame([$zoneA->id], $report['alerts']->pluck('zone_id')->unique()->values()->all());
        $this->assertSame($newerA->id, $report['latest']?->id);
        $this->assertSame(2, $report['counts']['total']);
        $this->assertSame(1, $report['counts']['conformes']);
        $this->assertSame(1, $report['counts']['non_conformes']);
        $this->assertFalse($report['trendDisponible']);

        $html = view('back.quality.reports.pdf', $report + [
            'generatedAt' => now(),
            'author' => 'Testeur',
        ])->render();

        $this->assertStringContainsString('Zone Alpha', $html);
        $this->assertStringContainsString($olderA->reference, $html);
        $this->assertStringContainsString($newerA->reference, $html);
        $this->assertStringContainsString('Turbidité', $html);
        $this->assertStringContainsString('6,5 – 8,5 pH', $html);
        $this->assertStringNotContainsString('Zone Bêta', $html);
        $this->assertStringNotContainsString($sampleB->reference, $html);
        $this->assertStringNotContainsString('Plomb', $html);
        $this->assertStringNotContainsString('µg/L', $html);

        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'pdf' => 1,
            'perimetre' => $zoneA->id,
        ]))->assertOk()->assertDownload();

        $this->actingAs($gestionnaire)->get(route('back.quality-samples.index', [
            'pdf' => 1,
            'perimetre' => $zoneB->id,
        ]))->assertOk()->assertDownload();
    }

    public function test_public_zone_page_displays_parameter_evolution_for_that_zone_only(): void
    {
        $zone = Zone::factory()->create();
        $otherZone = Zone::factory()->create();

        $sample = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-09-20 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $sample->id,
            'parametre' => 'chlore',
            'valeur' => 0.4,
            'unite' => 'mg/L',
            'est_conforme' => true,
        ]);

        $otherSample = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $otherZone->id,
            'date_prelevement' => '2026-09-21 08:00:00',
        ]);
        WaterQualityParameter::factory()->create([
            'sample_id' => $otherSample->id,
            'parametre' => 'plomb',
            'valeur' => 2.0,
            'unite' => 'µg/L',
            'est_conforme' => true,
        ]);

        $response = $this->get(route('public.quality.zone', $zone));

        $response->assertOk()
            ->assertSee('Évolution des paramètres')
            ->assertSee('Chlore résiduel')
            ->assertSee('mg/L')
            ->assertSee('20/09/2026')
            ->assertDontSee('Plomb');

        $response->assertViewHas('trends', function (array $trends) {
            return array_column($trends, 'parametre') === ['chlore']
                && $trends[0]['points'][0]['valeur'] === 0.4
                && $trends[0]['unite'] === 'mg/L';
        });
    }
}
