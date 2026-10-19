<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Maintenance;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureModuleTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('back.zones.index'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_module(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->get(route('back.zones.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.infrastructures.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.maintenances.index'))->assertForbidden();
    }

    public function test_gestionnaire_can_view_lists(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->get(route('back.zones.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.infrastructures.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.maintenances.index'))->assertOk();
    }

    public function test_gestionnaire_can_create_zone(): void
    {
        $gestionnaire = $this->gestionnaire();

        $response = $this->actingAs($gestionnaire)->post(route('back.zones.store'), [
            'nom' => 'Zone Centre',
            'commune' => 'Lyon',
            'code_postal' => '69000',
            'population' => 70000,
            'latitude' => 45.7640,
            'longitude' => 4.8357,
        ]);

        $zone = Zone::firstOrFail();
        $response->assertRedirect(route('back.zones.show', $zone));
        $this->assertDatabaseHas('zones', ['nom' => 'Zone Centre', 'commune' => 'Lyon']);
    }

    public function test_zone_validation_fails_with_invalid_data(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.zones.create'))
            ->post(route('back.zones.store'), ['nom' => '', 'commune' => '', 'code_postal' => ''])
            ->assertSessionHasErrors(['nom', 'commune', 'code_postal']);

        $this->assertDatabaseCount('zones', 0);
    }

    public function test_gestionnaire_can_update_and_delete_zone(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Ancienne']);

        $this->actingAs($gestionnaire)->put(route('back.zones.update', $zone), [
            'nom' => 'Nouvelle',
            'commune' => $zone->commune,
            'code_postal' => $zone->code_postal,
            'population' => $zone->population,
        ])->assertRedirect(route('back.zones.show', $zone));

        $this->assertSame('Nouvelle', $zone->fresh()->nom);

        $this->actingAs($gestionnaire)
            ->delete(route('back.zones.destroy', $zone))
            ->assertRedirect(route('back.zones.index'));

        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_deleting_zone_with_infrastructures_is_refused(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($gestionnaire)
            ->delete(route('back.zones.destroy', $zone))
            ->assertRedirect(route('back.zones.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
        $this->assertDatabaseHas('infrastructures', ['id' => $infrastructure->id]);
    }

    public function test_gestionnaire_can_create_infrastructure(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $response = $this->actingAs($gestionnaire)->post(route('back.infrastructures.store'), [
            'nom' => 'Réservoir Nord',
            'type' => 'réservoir',
            'statut' => 'actif',
            'zone_id' => $zone->id,
            'capacite' => 5000,
            'score_risque' => 2.5,
        ]);

        $infrastructure = Infrastructure::firstOrFail();
        $response->assertRedirect(route('back.infrastructures.show', $infrastructure));
        $this->assertSame($zone->id, $infrastructure->zone_id);
    }

    public function test_infrastructure_validation_rejects_unknown_type(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.infrastructures.create'))
            ->post(route('back.infrastructures.store'), [
                'nom' => 'Ouvrage',
                'type' => 'type_inconnu',
                'statut' => 'actif',
                'zone_id' => $zone->id,
            ])
            ->assertSessionHasErrors(['type']);
    }

    public function test_gestionnaire_can_create_maintenance(): void
    {
        $gestionnaire = $this->gestionnaire();
        $infrastructure = Infrastructure::factory()->create();

        $response = $this->actingAs($gestionnaire)->post(route('back.maintenances.store'), [
            'infrastructure_id' => $infrastructure->id,
            'technicien_id' => $gestionnaire->id,
            'type' => 'inspection',
            'date_intervention' => now()->addDays(3)->format('Y-m-d'),
            'cout' => 1200,
            'statut' => 'planifiee',
        ]);

        $maintenance = Maintenance::firstOrFail();
        $response->assertRedirect(route('back.maintenances.show', $maintenance));
        $this->assertSame($infrastructure->id, $maintenance->infrastructure_id);
        $this->assertSame($gestionnaire->id, $maintenance->technicien_id);
    }

    public function test_maintenance_requires_valid_infrastructure_and_date(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.maintenances.create'))
            ->post(route('back.maintenances.store'), [
                'infrastructure_id' => 9999,
                'type' => '',
                'date_intervention' => '',
                'statut' => '',
            ])
            ->assertSessionHasErrors(['infrastructure_id', 'type', 'date_intervention', 'statut']);
    }
}
