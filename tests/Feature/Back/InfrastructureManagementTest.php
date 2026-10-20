<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function infrastructurePayload(Zone $zone, array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Réservoir Est',
            'type' => 'réservoir',
            'materiau' => 'Béton',
            'date_installation' => '2015-06-01',
            'capacite' => 2500,
            'latitude' => 36.8,
            'longitude' => 10.2,
            'statut' => 'actif',
            'score_risque' => 12.5,
            'zone_id' => $zone->id,
        ], $overrides);
    }

    public function test_edit_page_is_displayed_for_a_gestionnaire(): void
    {
        $infrastructure = Infrastructure::factory()->create(['nom' => 'Ouvrage Nord']);
        $zone = $infrastructure->zone;

        $this->actingAs($this->gestionnaire())
            ->get(route('back.infrastructures.edit', $infrastructure))
            ->assertOk()
            ->assertSee('Ouvrage Nord')
            ->assertSee(route('back.infrastructures.update', $infrastructure), false)
            ->assertSee($zone->nom);
    }

    public function test_gestionnaire_can_update_an_infrastructure(): void
    {
        $manager = $this->gestionnaire();
        $infrastructure = Infrastructure::factory()->create(['nom' => 'Ancien nom']);
        $newZone = Zone::factory()->create(['nom' => 'Zone Nouvelle']);

        $this->actingAs($manager)
            ->put(route('back.infrastructures.update', $infrastructure), $this->infrastructurePayload($newZone, ['nom' => 'Nom Actualisé']))
            ->assertRedirect(route('back.infrastructures.show', $infrastructure))
            ->assertSessionHas('success', 'L\'infrastructure « Nom Actualisé » a été mise à jour.');

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'nom' => 'Nom Actualisé',
            'zone_id' => $newZone->id,
            'statut' => 'actif',
        ]);
    }

    public function test_infrastructure_update_rejects_invalid_values(): void
    {
        $manager = $this->gestionnaire();
        $infrastructure = Infrastructure::factory()->create();
        $original = $infrastructure->only(['nom', 'type', 'statut', 'zone_id']);

        $this->actingAs($manager)
            ->from(route('back.infrastructures.edit', $infrastructure))
            ->put(route('back.infrastructures.update', $infrastructure), $this->infrastructurePayload($infrastructure->zone, [
                'nom' => '',
                'type' => 'barrage',
                'statut' => 'inconnu',
                'latitude' => 200,
                'zone_id' => 999999,
            ]))
            ->assertRedirect(route('back.infrastructures.edit', $infrastructure))
            ->assertSessionHasErrors(['nom', 'type', 'statut', 'latitude', 'zone_id']);

        $this->assertDatabaseHas('infrastructures', ['id' => $infrastructure->id] + $original);
    }

    public function test_gestionnaire_can_delete_an_infrastructure(): void
    {
        $manager = $this->gestionnaire();
        $infrastructure = Infrastructure::factory()->create(['nom' => 'À supprimer']);

        $this->actingAs($manager)
            ->delete(route('back.infrastructures.destroy', $infrastructure))
            ->assertRedirect(route('back.infrastructures.index'))
            ->assertSessionHas('success', 'L\'infrastructure « À supprimer » a été supprimée.');

        $this->assertModelMissing($infrastructure);
    }

    public function test_risk_score_is_recomputed_and_persisted(): void
    {
        $infrastructure = Infrastructure::factory()->create([
            'date_installation' => now()->subYears(15),
            'statut' => 'hors_service',
            'score_risque' => 1,
        ]);

        Incident::factory()->count(2)->forInfrastructure($infrastructure)->create(['status' => 'en_cours']);

        $this->actingAs($this->gestionnaire())
            ->post(route('back.infrastructures.risk-score', $infrastructure))
            ->assertRedirect()
            ->assertSessionHas('success', 'Score de risque recalculé : 71.5 / 100 (niveau critique).');

        // 15 ans (22,5) + aucune maintenance (10) + 2 incidents ouverts (14) + hors service (25)
        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'score_risque' => 71.5,
        ]);
    }

    public function test_risk_score_for_a_well_maintained_active_network_is_low(): void
    {
        $infrastructure = Infrastructure::factory()->create([
            'date_installation' => now()->subYears(2),
            'statut' => 'actif',
            'score_risque' => 99,
        ]);

        Maintenance::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'date_intervention' => now()->subMonths(2),
            'statut' => 'terminee',
        ]);

        // 2 ans (3) - 1 maintenance (4) = 0, aucune autre pénalité
        $this->actingAs($this->gestionnaire())
            ->post(route('back.infrastructures.risk-score', $infrastructure))
            ->assertRedirect()
            ->assertSessionHas('success', 'Score de risque recalculé : 0 / 100 (niveau faible).');

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'score_risque' => 0,
        ]);
    }

    public function test_citizen_cannot_update_delete_or_score_infrastructures(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $infrastructure = Infrastructure::factory()->create();

        $this->actingAs($citizen)
            ->put(route('back.infrastructures.update', $infrastructure), $this->infrastructurePayload($infrastructure->zone))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.infrastructures.destroy', $infrastructure))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->post(route('back.infrastructures.risk-score', $infrastructure))
            ->assertForbidden();

        $this->assertModelExists($infrastructure);
    }

    public function test_maintenance_edit_page_is_displayed(): void
    {
        $maintenance = Maintenance::factory()->create(['type' => 'Réparation conduite']);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.maintenances.edit', $maintenance))
            ->assertOk()
            ->assertSee('Réparation conduite')
            ->assertSee(route('back.maintenances.update', $maintenance), false);
    }

    public function test_gestionnaire_can_update_and_delete_a_maintenance(): void
    {
        $manager = $this->gestionnaire();
        $maintenance = Maintenance::factory()->create([
            'type' => 'Inspection',
            'statut' => 'planifiee',
            'cout' => 100,
        ]);
        $technicien = User::factory()->gestionnaire()->create();

        $this->actingAs($manager)
            ->put(route('back.maintenances.update', $maintenance), [
                'infrastructure_id' => $maintenance->infrastructure_id,
                'technicien_id' => $technicien->id,
                'type' => 'Inspection annuelle',
                'description' => 'Contrôle complet de l\'ouvrage.',
                'date_intervention' => now()->toDateString(),
                'cout' => 430.5,
                'statut' => 'terminee',
            ])
            ->assertRedirect(route('back.maintenances.show', $maintenance))
            ->assertSessionHas('success', 'La maintenance a été mise à jour.');

        $this->assertDatabaseHas('maintenances', [
            'id' => $maintenance->id,
            'technicien_id' => $technicien->id,
            'type' => 'Inspection annuelle',
            'statut' => 'terminee',
            'cout' => 430.5,
        ]);

        $this->actingAs($manager)
            ->delete(route('back.maintenances.destroy', $maintenance))
            ->assertRedirect(route('back.maintenances.index'))
            ->assertSessionHas('success', 'La maintenance a été supprimée.');

        $this->assertModelMissing($maintenance);
    }

    public function test_maintenance_rejects_a_technician_without_back_office_role(): void
    {
        $manager = $this->gestionnaire();
        $maintenance = Maintenance::factory()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($manager)
            ->from(route('back.maintenances.edit', $maintenance))
            ->put(route('back.maintenances.update', $maintenance), [
                'infrastructure_id' => $maintenance->infrastructure_id,
                'technicien_id' => $citizen->id,
                'type' => 'Inspection',
                'date_intervention' => now()->toDateString(),
                'statut' => 'planifiee',
            ])
            ->assertRedirect(route('back.maintenances.edit', $maintenance))
            ->assertSessionHasErrors('technicien_id');

        $this->assertDatabaseMissing('maintenances', [
            'id' => $maintenance->id,
            'technicien_id' => $citizen->id,
        ]);
    }
}
