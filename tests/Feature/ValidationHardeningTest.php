<?php

namespace Tests\Feature;

use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_array_query_parameters_return_a_client_error_instead_of_a_server_error(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->get(route('back.water-levels.index').'?search[]=x')
            ->assertStatus(400);

        $this->actingAs($gestionnaire)
            ->getJson(route('back.water-levels.index').'?search[]=x')
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');

        $zone = Zone::factory()->create();

        $this->getJson(route('api.v1.drought.forecast', $zone).'?search[]=x')
            ->assertStatus(422)
            ->assertJsonValidationErrors('search');
    }

    public function test_forms_disable_native_browser_validation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<form novalidate', false);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.zones.create'))
            ->assertOk()
            ->assertSee('<form novalidate', false);
    }

    public function test_export_format_is_restricted_to_supported_values(): void
    {
        $this->actingAs($this->gestionnaire())
            ->get(route('back.water-levels.export', ['format' => 'html']))
            ->assertNotFound();
    }

    public function test_quality_assignment_rejects_non_array_zone_ids_without_erroring(): void
    {
        $admin = User::factory()->admin()->create();
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($admin)
            ->from(route('back.quality-assignments.index'))
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $gestionnaire->id,
                'zone_ids' => 'not-an-array',
            ])
            ->assertSessionHasErrors('zone_ids');

        $this->assertDatabaseCount('quality_zone_assignments', 0);
    }

    public function test_quality_assignment_rejects_unknown_users_and_zones(): void
    {
        $admin = User::factory()->admin()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($admin)
            ->from(route('back.quality-assignments.index'))
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $citizen->id,
                'zone_ids' => [999999],
            ])
            ->assertSessionHasErrors(['gestionnaire_id', 'zone_ids.0']);

        $this->assertDatabaseCount('quality_zone_assignments', 0);
    }

    public function test_maintenance_technician_must_be_a_back_office_user_and_cost_has_two_decimals(): void
    {
        $gestionnaire = $this->gestionnaire();
        $citizen = User::factory()->citoyen()->create();
        $infrastructure = Infrastructure::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.maintenances.create'))
            ->post(route('back.maintenances.store'), [
                'infrastructure_id' => $infrastructure->id,
                'technicien_id' => $citizen->id,
                'type' => 'inspection',
                'description' => 'Contrôle annuel.',
                'date_intervention' => now()->addDay()->format('Y-m-d'),
                'cout' => '12.345',
                'statut' => 'planifiee',
            ])
            ->assertSessionHasErrors(['technicien_id', 'cout']);

        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_incident_photo_upload_rejects_non_raster_mime_types(): void
    {
        Storage::fake('local');

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.photos.store', $incident), [
                'photo' => UploadedFile::fake()->create('document.jpg', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('incident_photos', 0);
    }

    public function test_quality_sample_rejects_unknown_parameter_keys(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->from(route('back.quality-samples.create'))
            ->post(route('back.quality-samples.store'), [
                'zone_id' => $zone->id,
                'date_prelevement' => now()->format('Y-m-d H:i:s'),
                'parametres' => [
                    'ph' => ['valeur' => 7.2],
                    'inconnu' => ['valeur' => 1],
                ],
            ])
            ->assertSessionHasErrors('parametres');

        $this->assertDatabaseCount('water_quality_samples', 0);
    }

    public function test_donation_return_requires_a_scalar_session_id(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $project = Project::factory()->enCours()->create();

        $this->actingAs($citizen)
            ->get(route('citizen.projects.donation.success', $project).'?session_id=unknown')
            ->assertNotFound();

        $this->actingAs($citizen)
            ->get(route('citizen.projects.donation.cancel', $project).'?session_id[]=unknown')
            ->assertStatus(400);
    }
}
