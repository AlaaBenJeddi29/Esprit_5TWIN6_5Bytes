<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityPlanification;
use App\Models\Quality\WaterQualityAlert;
use App\Models\Quality\WaterQualityParameter;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QualitySampleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // L'analyse reste locale : aucun appel Gemini réel ne doit partir du test.
        config(['services.gemini.api_key' => '']);
        Http::preventStrayRequests();
    }

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_edit_page_is_displayed_with_the_sample_data(): void
    {
        $sample = WaterQualitySample::factory()->create(['source' => 'Réservoir Nord']);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.quality-samples.edit', $sample))
            ->assertOk()
            ->assertSee($sample->reference)
            ->assertSee(route('back.quality-samples.update', $sample), false)
            ->assertSee('Réservoir Nord');
    }

    public function test_updating_a_sample_replaces_parameters_and_reanalyses_it(): void
    {
        $manager = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->create([
            'zone_id' => $zone->id,
            'source' => 'Réseau',
            'notes' => null,
        ]);

        $this->actingAs($manager)
            ->put(route('back.quality-samples.update', $sample), [
                'zone_id' => $zone->id,
                'infrastructure_id' => null,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'source' => 'Réservoir',
                'notes' => 'Deuxième analyse après intervention.',
                'parametres' => [
                    'ph' => ['valeur' => 7.2],
                    'turbidite' => ['valeur' => 9.5],
                ],
            ])
            ->assertRedirect(route('back.quality-samples.show', $sample))
            ->assertSessionHas('success', 'Le prélèvement a été mis à jour et réanalysé.');

        $sample->refresh();

        $this->assertSame('Réservoir', $sample->source);
        $this->assertSame('Deuxième analyse après intervention.', $sample->notes);
        $this->assertSame('non_conforme', $sample->statut);
        $this->assertEquals(50, (float) $sample->score);
        $this->assertSame(2, WaterQualityParameter::where('sample_id', $sample->id)->count());
        $this->assertDatabaseHas('water_quality_parameters', [
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'valeur' => 9.5,
            'est_conforme' => 0,
        ]);
        $this->assertDatabaseHas('water_quality_alerts', [
            'sample_id' => $sample->id,
            'parametre' => 'turbidite',
            'statut' => 'active',
        ]);

        // Une nouvelle modification ne doit pas dupliquer l'alerte active.
        $this->actingAs($manager)
            ->put(route('back.quality-samples.update', $sample), [
                'zone_id' => $zone->id,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'parametres' => [
                    'turbidite' => ['valeur' => 9.5],
                ],
            ])
            ->assertRedirect(route('back.quality-samples.show', $sample));

        $this->assertSame(1, WaterQualityAlert::where('sample_id', $sample->id)->count());
        $this->assertSame(1, WaterQualityParameter::where('sample_id', $sample->id)->count());
    }

    public function test_zone_cannot_be_changed_when_the_sample_is_linked_to_a_planification(): void
    {
        $manager = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $otherZone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->create(['zone_id' => $zone->id]);

        QualityPlanification::create([
            'zone_id' => $zone->id,
            'user_id' => $manager->id,
            'water_quality_sample_id' => $sample->id,
            'date_prevue' => now()->toDateString(),
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($manager)
            ->from(route('back.quality-samples.edit', $sample))
            ->put(route('back.quality-samples.update', $sample), [
                'zone_id' => $otherZone->id,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'parametres' => [
                    'ph' => ['valeur' => 7.1],
                ],
            ])
            ->assertRedirect(route('back.quality-samples.edit', $sample))
            ->assertSessionHasErrors('zone_id');

        $sample->refresh();

        $this->assertSame($zone->id, $sample->zone_id);
        $this->assertSame('en_attente', $sample->statut);
        $this->assertSame(0, WaterQualityParameter::where('sample_id', $sample->id)->count());
    }

    public function test_sample_linked_to_a_planification_cannot_be_deleted(): void
    {
        $manager = $this->gestionnaire();
        $sample = WaterQualitySample::factory()->create();

        QualityPlanification::create([
            'zone_id' => $sample->zone_id,
            'user_id' => $manager->id,
            'water_quality_sample_id' => $sample->id,
            'date_prevue' => now()->toDateString(),
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($manager)
            ->delete(route('back.quality-samples.destroy', $sample))
            ->assertRedirect(route('back.quality-samples.show', $sample))
            ->assertSessionHas('error', 'Ce prélèvement est lié à un contrôle planifié : son historique doit être conservé.');

        $this->assertModelExists($sample);
    }

    public function test_citizen_cannot_edit_update_or_delete_samples(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $sample = WaterQualitySample::factory()->create();

        $this->actingAs($citizen)
            ->get(route('back.quality-samples.edit', $sample))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->put(route('back.quality-samples.update', $sample), [
                'zone_id' => $sample->zone_id,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'parametres' => ['ph' => ['valeur' => 7]],
            ])
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.quality-samples.destroy', $sample))
            ->assertForbidden();

        $this->assertModelExists($sample);
    }
}
