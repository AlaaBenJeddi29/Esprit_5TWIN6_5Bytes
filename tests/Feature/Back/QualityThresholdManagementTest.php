<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityThreshold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityThresholdManagementTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_edit_page_is_displayed_with_the_threshold_values(): void
    {
        $threshold = QualityThreshold::factory()->create([
            'parametre' => 'turbidite',
            'unite' => 'NTU',
            'valeur_max' => 5,
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.quality-thresholds.edit', $threshold))
            ->assertOk()
            ->assertSee(route('back.quality-thresholds.update', $threshold), false)
            ->assertSee('5');
    }

    public function test_gestionnaire_can_update_a_threshold_and_deactivate_it(): void
    {
        $manager = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $threshold = QualityThreshold::factory()->create([
            'zone_id' => null,
            'parametre' => 'ph',
            'unite' => 'pH',
            'valeur_min' => 6.5,
            'valeur_max' => 8.5,
            'actif' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('back.quality-thresholds.update', $threshold), [
                'zone_id' => $zone->id,
                'parametre' => 'ph',
                'unite' => 'pH',
                'valeur_min' => 6.8,
                'valeur_max' => 8.2,
                'actif' => '0',
            ])
            ->assertRedirect(route('back.quality-thresholds.index'))
            ->assertSessionHas('success', 'Le seuil de qualité a été mis à jour.');

        $threshold->refresh();

        $this->assertSame($zone->id, $threshold->zone_id);
        $this->assertSame('6.8000', (string) $threshold->valeur_min);
        $this->assertSame('8.2000', (string) $threshold->valeur_max);
        $this->assertFalse($threshold->actif);
    }

    public function test_threshold_update_rejects_incoherent_bounds_and_duplicates(): void
    {
        $manager = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $threshold = QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'chlore',
            'valeur_min' => 0.2,
            'valeur_max' => 1,
        ]);
        QualityThreshold::factory()->create([
            'zone_id' => $zone->id,
            'parametre' => 'nitrates',
            'valeur_min' => 0,
            'valeur_max' => 50,
        ]);

        $this->actingAs($manager)
            ->from(route('back.quality-thresholds.edit', $threshold))
            ->put(route('back.quality-thresholds.update', $threshold), [
                'zone_id' => $zone->id,
                'parametre' => 'nitrates',
                'unite' => 'mg/L',
                'valeur_min' => 60,
                'valeur_max' => 10,
            ])
            ->assertRedirect(route('back.quality-thresholds.edit', $threshold))
            ->assertSessionHasErrors(['valeur_min', 'parametre']);

        $this->assertDatabaseHas('quality_thresholds', [
            'id' => $threshold->id,
            'parametre' => 'chlore',
        ]);
    }

    public function test_threshold_update_requires_at_least_one_bound(): void
    {
        $threshold = QualityThreshold::factory()->create([
            'parametre' => 'plomb',
            'valeur_min' => null,
            'valeur_max' => 10,
        ]);

        $this->actingAs($this->gestionnaire())
            ->from(route('back.quality-thresholds.edit', $threshold))
            ->put(route('back.quality-thresholds.update', $threshold), [
                'parametre' => 'plomb',
                'unite' => 'µg/L',
                'valeur_min' => null,
                'valeur_max' => null,
            ])
            ->assertRedirect(route('back.quality-thresholds.edit', $threshold))
            ->assertSessionHasErrors('valeur_min');
    }

    public function test_gestionnaire_can_delete_a_threshold(): void
    {
        $threshold = QualityThreshold::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.quality-thresholds.destroy', $threshold))
            ->assertRedirect(route('back.quality-thresholds.index'))
            ->assertSessionHas('success', 'Le seuil de qualité a été supprimé.');

        $this->assertModelMissing($threshold);
    }

    public function test_citizen_cannot_edit_update_or_delete_thresholds(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $threshold = QualityThreshold::factory()->create();

        $this->actingAs($citizen)
            ->get(route('back.quality-thresholds.edit', $threshold))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->put(route('back.quality-thresholds.update', $threshold), [
                'parametre' => $threshold->parametre,
                'unite' => $threshold->unite,
                'valeur_max' => 1,
            ])
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.quality-thresholds.destroy', $threshold))
            ->assertForbidden();

        $this->assertModelExists($threshold);
    }
}
