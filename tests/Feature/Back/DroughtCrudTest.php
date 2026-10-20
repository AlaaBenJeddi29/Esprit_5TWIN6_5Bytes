<?php

namespace Tests\Feature\Back;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroughtCrudTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_water_level_edit_page_shows_the_measurement(): void
    {
        $level = WaterLevel::factory()->create([
            'nom_source' => 'Barrage du Nord',
            'niveau' => 42,
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.water-levels.edit', $level))
            ->assertOk()
            ->assertSee('Barrage du Nord')
            ->assertSee(route('back.water-levels.update', $level), false);
    }

    public function test_water_level_can_be_updated_and_deleted(): void
    {
        $manager = $this->gestionnaire();
        $level = WaterLevel::factory()->create(['niveau' => 50, 'unite' => '%']);

        $this->actingAs($manager)
            ->put(route('back.water-levels.update', $level), [
                'nom_source' => 'Nappe Actualisée',
                'zone_id' => $level->zone_id,
                'type_source' => 'nappe',
                'niveau' => 23.5,
                'seuil_alerte' => 40,
                'seuil_critique' => 20,
                'date_mesure' => now()->toDateString(),
            ])
            ->assertRedirect(route('back.water-levels.index'))
            ->assertSessionHas('success', 'La mesure de niveau a été mise à jour.');

        $this->assertDatabaseHas('water_levels', [
            'id' => $level->id,
            'nom_source' => 'Nappe Actualisée',
            'type_source' => 'nappe',
            'niveau' => 23.5,
            'unite' => '%',
        ]);

        $this->actingAs($manager)
            ->delete(route('back.water-levels.destroy', $level))
            ->assertRedirect(route('back.water-levels.index'))
            ->assertSessionHas('success', 'La mesure de niveau a été supprimée.');

        $this->assertModelMissing($level);
    }

    public function test_consumption_reading_edit_page_shows_the_reading(): void
    {
        $reading = ConsumptionReading::factory()->create([
            'periode' => now()->startOfMonth()->format('Y-m-d'),
            'consommation_m3' => 12345,
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.consumption-readings.edit', $reading))
            ->assertOk()
            ->assertSee(route('back.consumption-readings.update', $reading), false)
            ->assertSee('12345');
    }

    public function test_consumption_reading_can_be_updated_without_triggering_its_own_uniqueness_rule(): void
    {
        $manager = $this->gestionnaire();
        $reading = ConsumptionReading::factory()->create([
            'periode' => now()->startOfMonth()->format('Y-m-d'),
            'consommation_m3' => 10000,
            'nombre_abonnes' => 100,
        ]);

        $this->actingAs($manager)
            ->put(route('back.consumption-readings.update', $reading), [
                'zone_id' => $reading->zone_id,
                'periode' => $reading->periode->format('Y-m-d'),
                'consommation_m3' => 12500,
                'nombre_abonnes' => 120,
            ])
            ->assertRedirect(route('back.consumption-readings.index'))
            ->assertSessionHas('success', 'Le relevé de consommation a été mis à jour.');

        $this->assertDatabaseHas('consumption_readings', [
            'id' => $reading->id,
            'consommation_m3' => 12500,
            'nombre_abonnes' => 120,
        ]);
    }

    public function test_consumption_reading_update_rejects_a_period_already_used_by_another_reading(): void
    {
        $manager = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $reading = ConsumptionReading::factory()->create([
            'zone_id' => $zone->id,
            'periode' => now()->startOfMonth()->subMonth()->format('Y-m-d'),
        ]);
        ConsumptionReading::factory()->create([
            'zone_id' => $zone->id,
            'periode' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $this->actingAs($manager)
            ->from(route('back.consumption-readings.edit', $reading))
            ->put(route('back.consumption-readings.update', $reading), [
                'zone_id' => $zone->id,
                'periode' => now()->startOfMonth()->format('Y-m-d'),
                'consommation_m3' => 9000,
            ])
            ->assertRedirect(route('back.consumption-readings.edit', $reading))
            ->assertSessionHasErrors('periode');

        $this->assertDatabaseHas('consumption_readings', [
            'id' => $reading->id,
            'consommation_m3' => $reading->consommation_m3,
        ]);
    }

    public function test_consumption_reading_can_be_deleted(): void
    {
        $reading = ConsumptionReading::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.consumption-readings.destroy', $reading))
            ->assertRedirect(route('back.consumption-readings.index'))
            ->assertSessionHas('success', 'Le relevé de consommation a été supprimé.');

        $this->assertModelMissing($reading);
    }

    public function test_scheduled_cut_can_be_updated_and_deleted(): void
    {
        $manager = $this->gestionnaire();
        $cut = ScheduledCut::factory()->create(['motif' => 'Ancien motif']);

        $this->actingAs($manager)
            ->get(route('back.scheduled-cuts.edit', $cut))
            ->assertOk()
            ->assertSee('Ancien motif')
            ->assertSee(route('back.scheduled-cuts.update', $cut), false);

        $this->actingAs($manager)
            ->put(route('back.scheduled-cuts.update', $cut), [
                'zone_id' => $cut->zone_id,
                'infrastructure_id' => null,
                'motif' => 'Nouveau motif',
                'date_debut' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'date_fin' => now()->addDays(2)->addHours(4)->format('Y-m-d H:i:s'),
                'statut' => 'en_cours',
            ])
            ->assertRedirect(route('back.scheduled-cuts.index'))
            ->assertSessionHas('success', 'La coupure programmée a été mise à jour.');

        $this->assertDatabaseHas('scheduled_cuts', [
            'id' => $cut->id,
            'motif' => 'Nouveau motif',
            'statut' => 'en_cours',
        ]);

        $this->actingAs($manager)
            ->delete(route('back.scheduled-cuts.destroy', $cut))
            ->assertRedirect(route('back.scheduled-cuts.index'))
            ->assertSessionHas('success', 'La coupure programmée a été supprimée.');

        $this->assertModelMissing($cut);
    }

    public function test_restriction_can_be_deleted(): void
    {
        $restriction = Restriction::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->get(route('back.restrictions.edit', $restriction))
            ->assertOk();

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.restrictions.destroy', $restriction))
            ->assertRedirect(route('back.restrictions.index'))
            ->assertSessionHas('success', 'La restriction a été supprimée.');

        $this->assertModelMissing($restriction);
    }

    public function test_citizen_cannot_mutate_drought_records(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $level = WaterLevel::factory()->create();
        $reading = ConsumptionReading::factory()->create();
        $cut = ScheduledCut::factory()->create();
        $restriction = Restriction::factory()->create();

        $this->actingAs($citizen)
            ->delete(route('back.water-levels.destroy', $level))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.consumption-readings.destroy', $reading))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.scheduled-cuts.destroy', $cut))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.restrictions.destroy', $restriction))
            ->assertForbidden();

        $this->assertModelExists($level);
        $this->assertModelExists($reading);
        $this->assertModelExists($cut);
        $this->assertModelExists($restriction);
    }
}
