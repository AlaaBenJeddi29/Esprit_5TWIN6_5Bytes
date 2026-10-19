<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Notifications\Incident\ComplaintDecisionNotification;
use App\Notifications\Incident\ComplaintUnderReviewNotification;
use App\Notifications\Incident\ManagerResponseNotification;
use App\Notifications\Incident\ReopeningDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ComplaintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function complaint(
        string $state = 'en_attente',
        bool $reopenRequested = false,
        ?User $manager = null,
    ): IncidentFeedback {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);

        $factory = IncidentFeedback::factory()->complaint($reopenRequested);

        $factory = match ($state) {
            'en_examen' => $factory->enExamen($manager),
            'acceptee' => $factory->acceptee($manager),
            'rejetee' => $factory->rejetee($manager),
            'cloturee' => $factory->cloturee($manager),
            default => $factory,
        };

        return $factory->create([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
        ]);
    }

    public function test_manager_can_start_the_review_and_the_citizen_is_notified(): void
    {
        Notification::fake();

        $manager = $this->manager();
        $feedback = $this->complaint();

        $this->actingAs($manager)
            ->patch(route('back.complaints.review', $feedback))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $feedback->refresh();

        $this->assertSame('en_examen', $feedback->complaint_status);
        $this->assertSame($manager->id, $feedback->reviewed_by);
        $this->assertNotNull($feedback->reviewed_at);
        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => $feedback->id,
            'user_id' => $manager->id,
            'action' => 'examen',
            'ancien_statut' => 'en_attente',
            'nouveau_statut' => 'en_examen',
        ]);

        Notification::assertSentTo($feedback->user, ComplaintUnderReviewNotification::class);
    }

    public function test_decision_without_justification_is_refused(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('en_examen', false, $manager);

        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $feedback), [
                'decision' => 'acceptee',
                'justification' => '',
            ])
            ->assertSessionHasErrors('justification');

        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $feedback), [
                'decision' => 'acceptee',
                'justification' => 'court',
            ])
            ->assertSessionHasErrors('justification');

        $feedback->refresh();

        $this->assertSame('en_examen', $feedback->complaint_status);
        $this->assertNull($feedback->complaint_decision);
        $this->assertNull($feedback->decision_reason);
    }

    public function test_valid_decision_is_historized_and_notifies_the_citizen(): void
    {
        Notification::fake();

        $manager = $this->manager();
        $feedback = $this->complaint('en_examen', false, $manager);
        $reason = 'La réclamation est fondée après contrôle de la pression sur le réseau.';

        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $feedback), [
                'decision' => 'acceptee',
                'justification' => $reason,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $feedback->refresh();

        $this->assertSame('acceptee', $feedback->complaint_status);
        $this->assertSame('acceptee', $feedback->complaint_decision);
        $this->assertSame($reason, $feedback->decision_reason);
        $this->assertSame($manager->id, $feedback->reviewed_by);
        $this->assertNotNull($feedback->reviewed_at);
        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => $feedback->id,
            'user_id' => $manager->id,
            'action' => 'acceptation',
            'ancien_statut' => 'en_examen',
            'nouveau_statut' => 'acceptee',
            'comment' => $reason,
        ]);

        Notification::assertSentTo($feedback->user, ComplaintDecisionNotification::class);
    }

    public function test_accepted_reopen_request_reopens_the_incident_through_the_workflow(): void
    {
        Notification::fake();

        $manager = $this->manager();
        $feedback = $this->complaint('acceptee', true, $manager);
        $incident = $feedback->incident;
        $oldResolutionNote = $incident->resolution_note;
        $justification = 'Réouverture : la fuite est de nouveau visible devant le numéro 12.';

        $this->actingAs($manager)
            ->patch(route('back.complaints.reopen', $feedback), [
                'justification' => $justification,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $feedback->refresh();
        $incident->refresh();

        $this->assertSame('en_cours', $incident->status);
        $this->assertNull($incident->resolved_at);
        $this->assertNull($incident->resolution_note);
        $this->assertNotNull($feedback->reopened_at);
        $this->assertSame($justification, $feedback->reopen_decision_reason);

        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'user_id' => $manager->id,
            'ancien_statut' => 'traite',
            'nouveau_statut' => 'en_cours',
            'comment' => $justification,
            'resolution_note' => $oldResolutionNote,
        ]);

        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => $feedback->id,
            'user_id' => $manager->id,
            'action' => 'reouverture',
            'ancien_statut' => 'traite',
            'nouveau_statut' => 'en_cours',
            'comment' => $justification,
        ]);

        Notification::assertSentTo($feedback->user, ReopeningDecisionNotification::class);
    }

    public function test_refused_reopen_request_keeps_the_incident_treated(): void
    {
        Notification::fake();

        $manager = $this->manager();
        $feedback = $this->complaint('en_examen', true, $manager);
        $incident = $feedback->incident;
        $reason = 'Les vérifications terrain ne confirment pas la persistance du problème.';

        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $feedback), [
                'decision' => 'rejetee',
                'justification' => $reason,
            ])
            ->assertSessionHasNoErrors();

        $feedback->refresh();
        $incident->refresh();

        $this->assertSame('rejetee', $feedback->complaint_status);
        $this->assertSame('rejetee', $feedback->complaint_decision);
        $this->assertNull($feedback->reopened_at);
        $this->assertSame('traite', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(0, $incident->statusHistories()->count());

        Notification::assertSentTo($feedback->user, ComplaintDecisionNotification::class);
        Notification::assertSentTo(
            $feedback->user,
            ReopeningDecisionNotification::class,
            fn (ReopeningDecisionNotification $notification) => $notification->toDatabase($feedback->user)['accepted'] === false,
        );
    }

    public function test_reopening_requires_a_justification(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('acceptee', true, $manager);

        $this->actingAs($manager)
            ->patch(route('back.complaints.reopen', $feedback), [
                'justification' => 'court',
            ])
            ->assertSessionHasErrors('justification');

        $feedback->refresh();

        $this->assertNull($feedback->reopened_at);
        $this->assertSame('traite', $feedback->incident->status);
    }

    public function test_incident_that_is_no_longer_treated_cannot_be_reopened(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('acceptee', true, $manager);
        $feedback->incident->update(['status' => 'en_cours']);

        $this->actingAs($manager)
            ->patch(route('back.complaints.reopen', $feedback), [
                'justification' => 'Réouverture demandée après acceptation de la réclamation.',
            ])
            ->assertSessionHasErrors('reopen');

        $feedback->refresh();

        $this->assertNull($feedback->reopened_at);
        $this->assertSame('en_cours', $feedback->incident->status);
        $this->assertSame(0, $feedback->incident->statusHistories()->count());
    }

    public function test_an_incident_cannot_be_reopened_twice(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('acceptee', true, $manager);
        $justification = 'Réouverture : le problème est de nouveau constaté sur place.';

        $this->actingAs($manager)
            ->patch(route('back.complaints.reopen', $feedback), [
                'justification' => $justification,
            ])
            ->assertSessionHasNoErrors();

        $reopenedAt = $feedback->refresh()->reopened_at;

        $this->actingAs($manager)
            ->patch(route('back.complaints.reopen', $feedback), [
                'justification' => $justification,
            ])
            ->assertForbidden();

        $feedback->refresh();

        $this->assertSame(1, $feedback->histories()->where('action', 'reouverture')->count());
        $this->assertEquals($reopenedAt, $feedback->reopened_at);
        $this->assertSame('en_cours', $feedback->incident->status);
        $this->assertSame(1, $feedback->incident->statusHistories()->count());
    }

    public function test_manager_can_respond_to_the_citizen(): void
    {
        Notification::fake();

        $manager = $this->manager();
        $feedback = $this->complaint();
        $response = 'Nos équipes sont intervenues ce matin, merci de votre patience.';

        $this->actingAs($manager)
            ->post(route('back.complaints.response', $feedback), [
                'response' => $response,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $feedback->refresh();

        $this->assertSame($response, $feedback->manager_response);
        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => $feedback->id,
            'user_id' => $manager->id,
            'action' => 'reponse',
            'comment' => $response,
        ]);

        Notification::assertSentTo($feedback->user, ManagerResponseNotification::class);
    }

    public function test_manager_can_close_a_decided_complaint_but_not_an_pending_one(): void
    {
        $manager = $this->manager();

        $pending = $this->complaint();
        $this->actingAs($manager)
            ->patch(route('back.complaints.close', $pending))
            ->assertForbidden();

        $decided = $this->complaint('acceptee', false, $manager);
        $this->actingAs($manager)
            ->patch(route('back.complaints.close', $decided), [
                'justification' => 'Le citoyen a confirmé la résolution après intervention.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $decided->refresh();

        $this->assertSame('cloturee', $decided->complaint_status);
        $this->assertSame('acceptee', $decided->complaint_decision);
        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => $decided->id,
            'action' => 'cloture',
            'ancien_statut' => 'acceptee',
            'nouveau_statut' => 'cloturee',
        ]);
    }

    public function test_invalid_complaint_transitions_are_refused(): void
    {
        $manager = $this->manager();
        $decision = $this->complaint('acceptee', false, $manager);

        $this->actingAs($manager)
            ->patch(route('back.complaints.review', $decision))
            ->assertForbidden();

        $pending = $this->complaint();
        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $pending), [
                'decision' => 'acceptee',
                'justification' => 'Justification suffisamment longue.',
            ])
            ->assertForbidden();

        $this->assertSame('en_attente', $pending->fresh()->complaint_status);
    }

    public function test_manager_actions_do_not_modify_the_citizens_evaluation(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('en_examen', true, $manager);
        $originalRating = $feedback->rating;
        $originalComment = $feedback->comment;
        $originalResolution = $feedback->resolution_confirmed;

        $this->actingAs($manager)
            ->post(route('back.complaints.response', $feedback), [
                'response' => 'Nous prenons en charge votre réclamation.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->patch(route('back.complaints.decision', $feedback), [
                'decision' => 'acceptee',
                'justification' => 'La réclamation est justifiée après vérification.',
            ])
            ->assertSessionHasNoErrors();

        $feedback->refresh();

        $this->assertSame($originalRating, $feedback->rating);
        $this->assertSame($originalComment, $feedback->comment);
        $this->assertSame($originalResolution, $feedback->resolution_confirmed);
    }

    public function test_citizen_cannot_access_the_complaints_back_office(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $feedback = $this->complaint();

        $this->actingAs($citizen)
            ->get(route('back.complaints.index'))
            ->assertForbidden();

        $this->actingAs($citizen)
            ->get(route('back.complaints.stats'))
            ->assertForbidden();

        $this->actingAs($citizen)
            ->patch(route('back.complaints.review', $feedback))
            ->assertForbidden();

        $this->assertSame('en_attente', $feedback->fresh()->complaint_status);
    }

    public function test_complaint_list_can_be_filtered_by_status_rating_and_zone(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create(['nom' => 'Zone Filtre']);

        $matching = $this->complaint();
        $matching->incident->update(['zone_id' => $zone->id]);

        $other = $this->complaint('acceptee', false, $manager);
        $other->incident->update(['zone_id' => $zone->id]);

        $this->actingAs($manager)
            ->get(route('back.complaints.index', [
                'status' => 'en_attente',
                'rating' => $matching->rating,
                'zone_id' => $zone->id,
            ]))
            ->assertOk()
            ->assertSee($matching->incident->reference)
            ->assertSee('Le problème persiste malgré')
            ->assertDontSee($other->incident->reference);
    }

    public function test_show_refuses_an_evaluation_without_complaint(): void
    {
        $manager = $this->manager();
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);
        $feedback = IncidentFeedback::factory()->create([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
        ]);

        $this->actingAs($manager)
            ->get(route('back.complaints.show', $feedback))
            ->assertNotFound();
    }

    public function test_show_displays_the_complaint_details_and_history(): void
    {
        $manager = $this->manager();
        $feedback = $this->complaint('en_examen', true, $manager);
        $feedback->histories()->create([
            'user_id' => $manager->id,
            'action' => 'reponse',
            'comment' => 'Réponse intermédiaire au citoyen.',
        ]);

        $this->actingAs($manager)
            ->get(route('back.complaints.show', $feedback))
            ->assertOk()
            ->assertSee($feedback->incident->reference)
            ->assertSee($feedback->complaint_description)
            ->assertSee('Motif de la réclamation')
            ->assertSee($feedback->user->name);
    }
}
