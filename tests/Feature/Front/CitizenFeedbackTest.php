<?php

namespace Tests\Feature\Front;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentFeedback;
use App\Models\User;
use App\Notifications\Incident\ComplaintSubmittedNotification;
use App\Notifications\Incident\NewComplaintNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CitizenFeedbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Incident}
     */
    private function treatedIncident(): array
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);

        return [$citizen, $incident];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'rating' => 5,
            'comment' => 'Intervention rapide et efficace.',
            'resolution_confirmed' => 1,
        ], $overrides);
    }

    public function test_owner_can_evaluate_a_treated_incident(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload())
            ->assertRedirect(route('citizen.incidents.show', $incident))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incident_feedbacks', [
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
            'rating' => 5,
            'resolution_confirmed' => true,
            'complaint_status' => null,
        ]);

        $this->assertDatabaseHas('incident_feedback_histories', [
            'incident_feedback_id' => IncidentFeedback::firstOrFail()->id,
            'user_id' => $citizen->id,
            'action' => 'depot',
        ]);
    }

    public function test_citizen_cannot_evaluate_another_citizens_incident(): void
    {
        [, $incident] = $this->treatedIncident();
        $intruder = User::factory()->citoyen()->create();

        $this->actingAs($intruder)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, IncidentFeedback::count());
    }

    public function test_evaluation_is_only_available_once_the_incident_is_treated(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload())
            ->assertForbidden();

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee("L'évaluation sera disponible dès que ce signalement sera traité", false);

        $this->assertSame(0, IncidentFeedback::count());
    }

    public function test_rating_must_be_an_integer_between_one_and_five(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        foreach ([0, 6, null, 'excellent'] as $rating) {
            $this->actingAs($citizen)
                ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                    'rating' => $rating,
                ]))
                ->assertSessionHasErrors('rating');
        }

        $this->assertSame(0, IncidentFeedback::count());
    }

    public function test_complaint_description_is_validated_and_must_match_the_resolution_answer(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        // Description trop courte.
        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'resolution_confirmed' => 0,
                'complaint_description' => 'court',
            ]))
            ->assertSessionHasErrors('complaint_description');

        // Réclamation alors que la résolution est confirmée.
        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'resolution_confirmed' => 1,
                'complaint_description' => 'Le problème persiste malgré tout sur la chaussée.',
            ]))
            ->assertSessionHasErrors('complaint_description');

        // Problème persistant sans description.
        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'resolution_confirmed' => 0,
                'complaint_description' => '',
            ]))
            ->assertSessionHasErrors('complaint_description');

        // Réouverture demandée alors que la résolution est confirmée.
        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'resolution_confirmed' => 1,
                'reopen_requested' => 1,
            ]))
            ->assertSessionHasErrors('reopen_requested');

        $this->assertSame(0, IncidentFeedback::count());
    }

    public function test_duplicate_evaluation_is_rejected_with_an_explicit_message(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload())
            ->assertSessionHasNoErrors();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload(['rating' => 2]))
            ->assertSessionHasErrors('feedback');

        $this->assertSame(1, IncidentFeedback::where('incident_id', $incident->id)->count());
        $this->assertSame(5, IncidentFeedback::firstOrFail()->rating);
    }

    public function test_reopen_request_never_changes_the_incident_status_immediately(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'rating' => 2,
                'resolution_confirmed' => 0,
                'complaint_description' => 'La fuite est toujours visible devant le numéro 12.',
                'reopen_requested' => 1,
            ]))
            ->assertSessionHasNoErrors();

        $incident->refresh();
        $feedback = IncidentFeedback::firstOrFail();

        $this->assertSame('traite', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame('en_attente', $feedback->complaint_status);
        $this->assertTrue($feedback->reopen_requested);
        $this->assertNull($feedback->reopened_at);
        $this->assertSame(0, $incident->statusHistories()->count());
    }

    public function test_citizen_and_managers_are_notified_when_a_complaint_is_submitted(): void
    {
        Notification::fake();

        [$citizen, $incident] = $this->treatedIncident();
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload([
                'rating' => 2,
                'resolution_confirmed' => 0,
                'complaint_description' => 'Le problème persiste malgré le passage des équipes.',
            ]))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($citizen, ComplaintSubmittedNotification::class);
        Notification::assertSentTo($manager, NewComplaintNotification::class);
    }

    public function test_simple_evaluation_does_not_notify_managers(): void
    {
        Notification::fake();

        [$citizen, $incident] = $this->treatedIncident();
        $manager = User::factory()->gestionnaire()->create();

        $this->actingAs($citizen)
            ->post(route('citizen.incidents.feedback.store', $incident), $this->payload())
            ->assertSessionHasNoErrors();

        Notification::assertNothingSentTo($manager);
    }

    public function test_citizen_sees_the_sent_evaluation_and_complaint_on_the_incident_page(): void
    {
        [$citizen, $incident] = $this->treatedIncident();

        $feedback = IncidentFeedback::factory()->complaint(true)->create([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
            'rating' => 2,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee('Votre avis sur le traitement')
            ->assertSee('Problème persistant')
            ->assertSee('Votre réclamation')
            ->assertSee($feedback->complaint_description)
            ->assertDontSee('Envoyer mon évaluation');
    }

    public function test_citizen_sees_only_their_own_evaluations_in_their_space(): void
    {
        [$citizen, $incident] = $this->treatedIncident();
        IncidentFeedback::factory()->create([
            'incident_id' => $incident->id,
            'user_id' => $citizen->id,
        ]);

        $otherCitizen = User::factory()->citoyen()->create();
        $otherIncident = Incident::factory()->traite()->create(['user_id' => $otherCitizen->id]);
        IncidentFeedback::factory()->create([
            'incident_id' => $otherIncident->id,
            'user_id' => $otherCitizen->id,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.feedbacks.index'))
            ->assertOk()
            ->assertSee($incident->reference)
            ->assertDontSee($otherIncident->reference);
    }
}
