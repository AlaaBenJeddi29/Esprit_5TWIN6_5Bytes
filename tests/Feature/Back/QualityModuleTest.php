<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityModuleTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
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
}
