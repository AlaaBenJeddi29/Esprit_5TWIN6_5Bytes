<?php

namespace Tests\Feature\Back;

use App\Exceptions\InvalidIncidentTransitionException;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentComment;
use App\Models\User;
use App\Services\IncidentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function incident(string $status = 'nouveau', array $overrides = []): Incident
    {
        return Incident::factory()->create(array_merge(['status' => $status], $overrides));
    }

    private function transition(User $user, Incident $incident, array $payload)
    {
        return $this->actingAs($user)
            ->patch(route('back.incidents.status', $incident), $payload);
    }

    public function test_nouveau_can_transition_to_en_cours(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');

        $this->transition($gestionnaire, $incident, ['status' => 'en_cours'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('en_cours', $incident->fresh()->status);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'user_id' => $gestionnaire->id,
            'ancien_statut' => 'nouveau',
            'nouveau_statut' => 'en_cours',
        ]);
    }

    public function test_en_cours_can_transition_to_en_attente(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->transition($gestionnaire, $incident, ['status' => 'en_attente'])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_attente', $incident->fresh()->status);
    }

    public function test_en_attente_can_return_to_en_cours(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_attente');

        $this->transition($gestionnaire, $incident, ['status' => 'en_cours'])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_cours', $incident->fresh()->status);
    }

    public function test_incident_can_be_closed_with_a_valid_resolution_note(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');
        $note = 'Fuite réparée et pression contrôlée sur le réseau.';

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'comment' => 'Intervention terminée.',
            'resolution_note' => $note,
        ])->assertSessionHasNoErrors();

        $incident->refresh();

        $this->assertSame('traite', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame($note, $incident->resolution_note);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'en_cours',
            'nouveau_statut' => 'traite',
            'comment' => 'Intervention terminée.',
            'resolution_note' => $note,
        ]);
    }

    public function test_closing_without_resolution_note_is_rejected(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->transition($gestionnaire, $incident, ['status' => 'traite'])
            ->assertSessionHasErrors('resolution_note');

        $incident->refresh();

        $this->assertSame('en_cours', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_whitespace_only_resolution_note_is_rejected(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'resolution_note' => '                    ',
        ])->assertSessionHasErrors('resolution_note');

        $this->assertSame('en_cours', $incident->fresh()->status);
    }

    public function test_resolution_note_must_respect_length_bounds(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'resolution_note' => str_repeat('a', 9),
        ])->assertSessionHasErrors('resolution_note');

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'resolution_note' => str_repeat('a', 2001),
        ])->assertSessionHasErrors('resolution_note');

        $this->assertSame('en_cours', $incident->fresh()->status);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_nouveau_cannot_jump_directly_to_traite(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'resolution_note' => 'Compte rendu suffisamment détaillé.',
        ])->assertSessionHasErrors('status');

        $incident->refresh();

        $this->assertSame('nouveau', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_rejection_requires_a_valid_reason(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');

        $this->transition($gestionnaire, $incident, ['status' => 'rejete'])
            ->assertSessionHasErrors('rejection_reason');

        $this->transition($gestionnaire, $incident, [
            'status' => 'rejete',
            'rejection_reason' => '   ',
        ])->assertSessionHasErrors('rejection_reason');

        $this->transition($gestionnaire, $incident, [
            'status' => 'rejete',
            'rejection_reason' => 'court',
        ])->assertSessionHasErrors('rejection_reason');

        $reason = 'Signalement non recevable après vérification terrain.';

        $this->transition($gestionnaire, $incident, [
            'status' => 'rejete',
            'rejection_reason' => $reason,
        ])->assertSessionHasNoErrors();

        $incident->refresh();

        $this->assertSame('rejete', $incident->status);
        $this->assertSame($reason, $incident->rejection_reason);
        $this->assertNull($incident->resolved_at);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'nouveau',
            'nouveau_statut' => 'rejete',
            'rejection_reason' => $reason,
        ]);
    }

    public function test_reopening_from_traite_requires_justification_and_clears_resolution(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = Incident::factory()->traite()->create();
        $oldNote = $incident->resolution_note;

        $this->transition($gestionnaire, $incident, [
            'status' => 'en_cours',
            'comment' => 'court',
        ])->assertSessionHasErrors('comment');

        $this->assertSame('traite', $incident->fresh()->status);

        $justification = 'Réouverture : la fuite est de nouveau visible sur place.';

        $this->transition($gestionnaire, $incident, [
            'status' => 'en_cours',
            'comment' => $justification,
        ])->assertSessionHasNoErrors();

        $incident->refresh();

        $this->assertSame('en_cours', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertNull($incident->resolution_note);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'traite',
            'nouveau_statut' => 'en_cours',
            'comment' => $justification,
            'resolution_note' => $oldNote,
        ]);
    }

    public function test_reopening_from_rejete_requires_justification_and_clears_rejection_reason(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = Incident::factory()->rejete()->create();
        $oldReason = $incident->rejection_reason;

        $this->transition($gestionnaire, $incident, ['status' => 'nouveau'])
            ->assertSessionHasErrors('comment');

        $justification = 'Réouverture : de nouveaux éléments ont été fournis par le citoyen.';

        $this->transition($gestionnaire, $incident, [
            'status' => 'nouveau',
            'comment' => $justification,
        ])->assertSessionHasNoErrors();

        $incident->refresh();

        $this->assertSame('nouveau', $incident->status);
        $this->assertNull($incident->rejection_reason);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'rejete',
            'nouveau_statut' => 'nouveau',
            'comment' => $justification,
            'rejection_reason' => $oldReason,
        ]);
    }

    public function test_history_records_all_transition_information_in_chronological_order(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');
        $note = 'Compte rendu de clôture détaillé et vérifié.';

        $this->transition($gestionnaire, $incident, [
            'status' => 'en_cours',
            'comment' => 'Prise en charge.',
        ])->assertSessionHasNoErrors();

        $this->transition($gestionnaire, $incident, [
            'status' => 'traite',
            'comment' => 'Travaux terminés.',
            'resolution_note' => $note,
        ])->assertSessionHasNoErrors();

        $histories = $incident->statusHistories()->get();

        $this->assertCount(2, $histories);
        $this->assertSame('nouveau', $histories[0]->ancien_statut);
        $this->assertSame('en_cours', $histories[0]->nouveau_statut);
        $this->assertSame('en_cours', $histories[1]->ancien_statut);
        $this->assertSame('traite', $histories[1]->nouveau_statut);
        $this->assertSame($gestionnaire->id, $histories[1]->user_id);
        $this->assertSame('Travaux terminés.', $histories[1]->comment);
        $this->assertSame($note, $histories[1]->resolution_note);
        $this->assertNotNull($histories[1]->created_at);
    }

    public function test_refused_transition_does_not_modify_incident_or_history(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->transition($gestionnaire, $incident, [
            'status' => 'nouveau',
            'comment' => 'Tentative de retour arrière non autorisée.',
        ])->assertSessionHasErrors('status');

        $incident->refresh();

        $this->assertSame('en_cours', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_citizen_cannot_change_incident_status(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = $this->incident('nouveau');

        $this->actingAs($citizen)
            ->patch(route('back.incidents.status', $incident), ['status' => 'en_cours'])
            ->assertForbidden();

        $this->assertSame('nouveau', $incident->fresh()->status);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_general_update_cannot_bypass_workflow(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');

        $this->actingAs($gestionnaire)
            ->put(route('back.incidents.update', $incident), [
                'type' => 'fuite',
                'title' => 'Fuite sur la chaussée',
                'description' => 'Une fuite importante est visible devant le numéro 12.',
                'address' => '12 rue des Lilas',
                'zone_id' => null,
                'infrastructure_id' => null,
                'latitude' => null,
                'longitude' => null,
                'severity' => 'haute',
                'priority' => 'haute',
                'assigned_to' => null,
                'status' => 'traite',
                'resolution_note' => 'Tentative de contournement du workflow.',
            ])
            ->assertSessionHasErrors(['status', 'resolution_note']);

        $incident->refresh();

        $this->assertSame('nouveau', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertNull($incident->resolution_note);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_back_office_creation_forces_nouveau_status(): void
    {
        $gestionnaire = $this->gestionnaire();

        $payload = [
            'type' => 'fuite',
            'title' => 'Fuite nouvellement signalée',
            'description' => 'Écoulement continu observé depuis ce matin.',
            'address' => '8 avenue du Lac',
            'zone_id' => null,
            'infrastructure_id' => null,
            'latitude' => null,
            'longitude' => null,
            'severity' => 'moyenne',
            'priority' => 'normale',
            'assigned_to' => null,
        ];

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.store'), $payload + ['status' => 'en_cours'])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Incident::count());

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.store'), $payload)
            ->assertSessionHasNoErrors();

        $incident = Incident::firstOrFail();

        $this->assertSame('nouveau', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertNull($incident->resolution_note);
    }

    public function test_duplicate_identical_transitions_do_not_create_inconsistent_state(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');

        $this->transition($gestionnaire, $incident, ['status' => 'en_cours'])
            ->assertSessionHasNoErrors();

        $this->transition($gestionnaire, $incident, ['status' => 'en_cours'])
            ->assertSessionHasErrors('status');

        $this->assertSame('en_cours', $incident->fresh()->status);
        $this->assertSame(1, $incident->statusHistories()->count());
    }

    public function test_stale_model_instance_cannot_apply_a_transition_based_on_outdated_status(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('nouveau');
        $stale = Incident::findOrFail($incident->id);

        app(IncidentWorkflowService::class)->transition($incident, 'en_cours', $gestionnaire);

        $this->expectException(InvalidIncidentTransitionException::class);

        app(IncidentWorkflowService::class)->transition($stale, 'en_cours', $gestionnaire);
    }

    public function test_back_show_lists_only_allowed_transitions(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = $this->incident('en_cours');

        $this->actingAs($gestionnaire)
            ->get(route('back.incidents.show', $incident))
            ->assertOk()
            ->assertSee('value="en_attente"', false)
            ->assertSee('value="traite"', false)
            ->assertSee('value="rejete"', false)
            ->assertDontSee('value="nouveau"', false);
    }

    public function test_back_show_offers_reopening_with_justification_for_traite_incident(): void
    {
        $gestionnaire = $this->gestionnaire();
        $incident = Incident::factory()->traite()->create();

        $this->actingAs($gestionnaire)
            ->get(route('back.incidents.show', $incident))
            ->assertOk()
            ->assertSee('value="en_cours"', false)
            ->assertSee('réouverture')
            ->assertDontSee('value="traite"', false);
    }

    public function test_front_show_displays_resolution_note_after_closure(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee('Compte rendu de résolution')
            ->assertSee($incident->resolution_note);
    }

    public function test_front_show_displays_rejection_reason_and_hides_internal_comments(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->rejete()->create(['user_id' => $citizen->id]);

        IncidentComment::factory()->internal()->create([
            'incident_id' => $incident->id,
            'body' => 'NOTE INTERNE CONFIDENTIELLE',
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee('Motif de rejet')
            ->assertSee($incident->rejection_reason)
            ->assertDontSee('NOTE INTERNE CONFIDENTIELLE');
    }
}
