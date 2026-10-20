<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentPhoto;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'fuite',
            'title' => 'Fuite signalée',
            'description' => 'Une fuite importante a été constatée sur la conduite principale.',
            'address' => '12 rue des Eaux',
            'zone_id' => null,
            'infrastructure_id' => null,
            'latitude' => 36.8,
            'longitude' => 10.2,
            'severity' => 'haute',
            'priority' => 'haute',
            'assigned_to' => null,
        ], $overrides);
    }

    public function test_edit_page_is_displayed_with_current_values(): void
    {
        $incident = Incident::factory()->create(['title' => 'Titre existant']);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.edit', $incident))
            ->assertOk()
            ->assertSee('Titre existant')
            ->assertSee(route('back.incidents.update', $incident), false);
    }

    public function test_gestionnaire_can_update_an_incident_without_touching_its_status(): void
    {
        $manager = $this->gestionnaire();
        $incident = Incident::factory()->enCours()->create(['status' => 'en_cours']);
        $zone = Zone::factory()->create();

        $this->actingAs($manager)
            ->put(route('back.incidents.update', $incident), $this->updatePayload([
                'title' => 'Titre corrigé',
                'severity' => 'critique',
                'priority' => 'urgente',
                'zone_id' => $zone->id,
                'assigned_to' => $manager->id,
            ]))
            ->assertRedirect(route('back.incidents.show', $incident))
            ->assertSessionHas('success', 'L\'incident a été mis à jour.');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => 'Titre corrigé',
            'severity' => 'critique',
            'priority' => 'urgente',
            'zone_id' => $zone->id,
            'assigned_to' => $manager->id,
            'status' => 'en_cours',
        ]);
    }

    public function test_status_and_resolution_fields_cannot_be_changed_through_the_edit_form(): void
    {
        $manager = $this->gestionnaire();
        $incident = Incident::factory()->create([
            'status' => 'nouveau',
            'resolution_note' => null,
            'rejection_reason' => null,
        ]);

        $this->actingAs($manager)
            ->from(route('back.incidents.edit', $incident))
            ->put(route('back.incidents.update', $incident), $this->updatePayload([
                'title' => 'Tentative de contournement',
                'status' => 'traite',
                'resolution_note' => 'Clôture sauvage',
                'rejection_reason' => 'Motif inventé',
            ]))
            ->assertRedirect(route('back.incidents.edit', $incident))
            ->assertSessionHasErrors(['status', 'resolution_note', 'rejection_reason']);

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => 'nouveau',
            'resolution_note' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_incident_update_validates_the_payload(): void
    {
        $incident = Incident::factory()->create();

        $this->actingAs($this->gestionnaire())
            ->from(route('back.incidents.edit', $incident))
            ->put(route('back.incidents.update', $incident), $this->updatePayload([
                'type' => 'inconnu',
                'title' => '',
                'description' => 'court',
                'severity' => 'extrême',
                'priority' => 'immédiate',
                'latitude' => 120,
            ]))
            ->assertRedirect(route('back.incidents.edit', $incident))
            ->assertSessionHasErrors(['type', 'title', 'description', 'severity', 'priority', 'latitude']);
    }

    public function test_citizen_cannot_edit_update_or_delete_incidents(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);

        $this->actingAs($citizen)
            ->get(route('back.incidents.edit', $incident))
            ->assertForbidden();
        $this->actingAs($citizen)
            ->put(route('back.incidents.update', $incident), $this->updatePayload())
            ->assertForbidden();
        $this->actingAs($citizen)
            ->delete(route('back.incidents.destroy', $incident))
            ->assertForbidden();

        $this->assertModelExists($incident);
    }

    public function test_deleting_an_incident_removes_its_photos_from_storage(): void
    {
        Storage::fake('local');

        $incident = Incident::factory()->create();
        $path = UploadedFile::fake()->create('fuite.jpg', 100, 'image/jpeg')->store("incidents/{$incident->id}", 'local');
        $photo = $incident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => 'fuite.jpg',
            'mime_type' => 'image/jpeg',
            'taille' => 100,
        ]);

        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->gestionnaire())
            ->delete(route('back.incidents.destroy', $incident))
            ->assertRedirect(route('back.incidents.index'));

        $this->assertModelMissing($incident);
        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_gestionnaire_can_download_a_photo_with_its_original_name(): void
    {
        Storage::fake('local');

        $incident = Incident::factory()->create();
        $path = UploadedFile::fake()->create('constat.jpg', 100, 'image/jpeg')->store("incidents/{$incident->id}", 'local');
        $photo = $incident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => 'constat.jpg',
            'mime_type' => 'image/jpeg',
            'taille' => 100,
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.photos.download', [$incident, $photo->id]))
            ->assertOk()
            ->assertDownload('constat.jpg');
    }

    public function test_photo_belongs_to_the_incident_in_the_url(): void
    {
        Storage::fake('local');

        $incident = Incident::factory()->create();
        $otherIncident = Incident::factory()->create();
        $path = UploadedFile::fake()->create('preuve.jpg', 100, 'image/jpeg')->store("incidents/{$otherIncident->id}", 'local');
        $photo = $otherIncident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => 'preuve.jpg',
            'mime_type' => 'image/jpeg',
            'taille' => 100,
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.photos.download', [$incident, $photo->id]))
            ->assertNotFound();
    }

    public function test_photo_download_is_not_found_when_the_file_is_missing(): void
    {
        Storage::fake('local');

        $incident = Incident::factory()->create();
        $photo = IncidentPhoto::factory()->create([
            'incident_id' => $incident->id,
            'chemin_fichier' => "incidents/{$incident->id}/absent.jpg",
        ]);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.photos.download', [$incident, $photo->id]))
            ->assertNotFound();
    }

    public function test_citizen_can_download_a_photo_of_their_own_incident_only(): void
    {
        Storage::fake('local');

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);
        $path = UploadedFile::fake()->create('signalement.jpg', 100, 'image/jpeg')->store("incidents/{$incident->id}", 'local');
        $photo = $incident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => 'signalement.jpg',
            'mime_type' => 'image/jpeg',
            'taille' => 100,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.photos.download', [$incident, $photo->id]))
            ->assertOk()
            ->assertDownload('signalement.jpg');

        $otherCitizen = User::factory()->citoyen()->create();

        $this->actingAs($otherCitizen)
            ->get(route('citizen.incidents.photos.download', [$incident, $photo->id]))
            ->assertForbidden();

        $this->actingAs($this->gestionnaire())
            ->get(route('citizen.incidents.photos.download', [$incident, $photo->id]))
            ->assertForbidden();
    }

    public function test_citizen_photo_download_is_scoped_to_the_incident_in_the_url(): void
    {
        Storage::fake('local');

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);
        $otherIncident = Incident::factory()->create(['user_id' => $citizen->id]);
        $path = UploadedFile::fake()->create('autre.jpg', 100, 'image/jpeg')->store("incidents/{$otherIncident->id}", 'local');
        $photo = $otherIncident->photos()->create([
            'chemin_fichier' => $path,
            'nom_original' => 'autre.jpg',
            'mime_type' => 'image/jpeg',
            'taille' => 100,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.photos.download', [$incident, $photo->id]))
            ->assertNotFound();
    }
}
