<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentComment;
use App\Models\Incident\IncidentStatusHistory;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentModuleTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function incidentPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'fuite',
            'title' => 'Fuite sur la chaussée',
            'description' => 'Une fuite importante est visible devant le numéro 12.',
            'address' => '12 rue des Lilas',
            'zone_id' => Zone::factory()->create()->id,
            'infrastructure_id' => null,
            'severity' => 'haute',
            'priority' => 'haute',
            'status' => 'nouveau',
            'assigned_to' => null,
        ], $overrides);
    }

    public function test_citizen_cannot_access_back_office_incidents(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->get(route('back.incidents.index'))->assertForbidden();
        $this->actingAs($citizen)->post(route('back.incidents.store'), $this->incidentPayload())->assertForbidden();
    }

    public function test_gestionnaire_can_view_and_create_incidents(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->get(route('back.incidents.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.incidents.create'))->assertOk();

        $response = $this->actingAs($gestionnaire)->post(route('back.incidents.store'), $this->incidentPayload());

        $incident = Incident::firstOrFail();
        $response->assertRedirect(route('back.incidents.show', $incident));

        $this->assertStringStartsWith('INC-', $incident->reference);
        $this->assertSame($gestionnaire->id, $incident->user_id);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'nouveau_statut' => 'nouveau',
        ]);
    }

    public function test_incident_validation_rejects_invalid_payload(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.incidents.create'))
            ->post(route('back.incidents.store'), [
                'type' => 'inconnu',
                'title' => '',
                'description' => 'court',
                'severity' => 'extreme',
                'priority' => 'immédiate',
                'status' => 'inconnu',
                'zone_id' => 9999,
            ])
            ->assertSessionHasErrors(['type', 'title', 'description', 'severity', 'priority', 'status', 'zone_id']);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_status_change_is_journalised_and_sets_resolution(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = Incident::factory()->create(['status' => 'nouveau']);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.status', $incident), [
                'status' => 'en_cours',
                'comment' => 'Prise en charge par l\'équipe technique.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.status', $incident), [
                'status' => 'traite',
                'comment' => 'Intervention terminée.',
                'resolution_note' => 'Fuite colmatée sur la conduite principale.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $incident->refresh();

        $this->assertSame('traite', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame('Fuite colmatée sur la conduite principale.', $incident->resolution_note);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'nouveau',
            'nouveau_statut' => 'en_cours',
        ]);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'en_cours',
            'nouveau_statut' => 'traite',
            'resolution_note' => 'Fuite colmatée sur la conduite principale.',
        ]);
    }

    public function test_gestionnaire_can_add_internal_comment(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = Incident::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.comments.store', $incident), [
                'body' => 'Équipe affectée.',
                'is_internal' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('incident_comments', [
            'incident_id' => $incident->id,
            'is_internal' => 1,
        ]);
    }

    public function test_internal_comment_is_hidden_from_citizen_view(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);

        IncidentComment::factory()->internal()->create([
            'incident_id' => $incident->id,
            'body' => 'NOTE INTERNE CONFIDENTIELLE',
        ]);

        IncidentComment::factory()->create([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
            'body' => 'Commentaire citoyen visible',
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee('Commentaire citoyen visible')
            ->assertDontSee('NOTE INTERNE CONFIDENTIELLE');
    }

    public function test_citizen_can_create_incident_with_heuristic_classification(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'type' => 'autre',
            'title' => 'Grosse fuite devant l\'école',
            'description' => 'Un écoulement continu inonde la chaussée devant l\'école.',
            'address' => '2 rue de l\'École',
            'zone_id' => $zone->id,
            'severity' => 'faible',
        ]);

        $incident = Incident::firstOrFail();

        $response->assertRedirect(route('citizen.incidents.show', $incident));
        $this->assertSame($citizen->id, $incident->user_id);
        $this->assertSame('fuite', $incident->type);
        $this->assertSame('critique', $incident->severity);
        $this->assertSame('urgente', $incident->priority);
        $this->assertNotNull($incident->ai_confidence);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'nouveau_statut' => 'nouveau',
        ]);
    }

    public function test_citizen_sees_only_own_incidents(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $other = User::factory()->citoyen()->create();

        $own = Incident::factory()->create(['user_id' => $citizen->id]);
        $foreign = Incident::factory()->create(['user_id' => $other->id]);

        $this->actingAs($citizen)->get(route('citizen.incidents.index'))->assertOk()->assertSee($own->reference);
        $this->actingAs($citizen)->get(route('citizen.incidents.show', $own))->assertOk();
        $this->actingAs($citizen)->get(route('citizen.incidents.show', $foreign))->assertForbidden();
    }

    public function test_citizen_can_comment_and_upload_photo_on_own_incident(): void
    {
        Storage::fake('local');

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.comments.store', $incident), ['body' => 'Toujours en cours.'])
            ->assertRedirect();

        $this->assertDatabaseHas('incident_comments', [
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
            'is_internal' => 0,
        ]);

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.photos.store', $incident), [
                'photo' => UploadedFile::fake()->create('fuite.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect();

        $photo = $incident->photos()->firstOrFail();

        Storage::disk('local')->assertExists($photo->chemin_fichier);
    }

    public function test_citizen_cannot_comment_on_another_incident(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.comments.store', $incident), ['body' => 'Intrusion'])
            ->assertForbidden();

        $this->assertSame(0, IncidentComment::count());
    }

    public function test_public_tracking_returns_incident_by_reference(): void
    {
        $incident = Incident::factory()->create([
            'reference' => 'INC-DEMO-TRACK1',
            'title' => 'Fuite suivie publiquement',
        ]);
        IncidentStatusHistory::factory()->create(['incident_id' => $incident->id]);

        $this->get(route('front.incidents.track.form'))->assertOk();

        $this->post(route('front.incidents.track'), ['reference' => 'inc-demo-track1'])
            ->assertOk()
            ->assertSee('INC-DEMO-TRACK1')
            ->assertSee('Fuite suivie publiquement');

        $this->post(route('front.incidents.track'), ['reference' => 'INC-INCONNU'])
            ->assertOk()
            ->assertSee('Aucun signalement');
    }
}
