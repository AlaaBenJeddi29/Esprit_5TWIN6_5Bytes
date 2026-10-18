<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QualityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-gemini-key']);
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
            "La turbidité mesurée est supérieure au seuil autorisé. Une vérification par les responsables est recommandée.",
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

            return str_contains($request->url(), 'gemini-2.5-flash')
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
}
