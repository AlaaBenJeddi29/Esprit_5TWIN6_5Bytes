<?php

namespace Tests\Feature\Back;

use App\Mail\IncidentResolvedCitizenMail;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentCitizenNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class IncidentResolutionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function transition(User $actor, Incident $incident, array $payload)
    {
        return $this->actingAs($actor)
            ->patch(route('back.incidents.status', $incident), $payload);
    }

    /**
     * @return array<string, string>
     */
    private function resolutionPayload(string $note = 'Fuite réparée et pression contrôlée sur le réseau.'): array
    {
        return [
            'status' => 'traite',
            'comment' => 'Intervention terminée.',
            'resolution_note' => $note,
        ];
    }

    public function test_treating_a_pending_incident_emails_the_original_citizen(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create(['email' => 'citizen@example.test']);
        $manager = $this->gestionnaire();
        $otherCitizen = User::factory()->citoyen()->create(['email' => 'other@example.test']);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_attente',
        ]);

        $this->transition($manager, $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertSentCount(1);
        Mail::assertSent(
            IncidentResolvedCitizenMail::class,
            fn (IncidentResolvedCitizenMail $mail): bool => $mail->hasTo($citizen->email),
        );
        Mail::assertNotSent(
            IncidentResolvedCitizenMail::class,
            fn (IncidentResolvedCitizenMail $mail): bool => $mail->hasTo($manager->email)
                || $mail->hasTo($otherCitizen->email)
                || $mail->hasTo($admin->email),
        );

        $this->assertSame('traite', $incident->fresh()->status);
    }

    public function test_treating_an_in_progress_incident_emails_the_original_citizen(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create(['email' => 'citizen@example.test']);
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        $this->transition($this->gestionnaire(), $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors();

        Mail::assertSent(
            IncidentResolvedCitizenMail::class,
            fn (IncidentResolvedCitizenMail $mail): bool => $mail->hasTo($citizen->email),
        );
    }

    public function test_transition_to_another_status_does_not_send_a_resolution_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        $this->transition($this->gestionnaire(), $incident, ['status' => 'en_attente'])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_attente', $incident->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_repeated_processing_of_the_same_treatment_event_does_not_duplicate_the_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);
        $history = $incident->statusHistories()->create([
            'user_id' => $this->gestionnaire()->id,
            'ancien_statut' => 'en_cours',
            'nouveau_statut' => 'traite',
        ]);

        $notifier = app(IncidentCitizenNotifier::class);
        $notifier->notifyResolved($incident, $history->id);
        $notifier->notifyResolved($incident, $history->id);

        Mail::assertSentCount(1);
    }

    public function test_unrelated_edit_of_an_already_treated_incident_does_not_send_an_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $citizen->id]);

        $this->actingAs($this->gestionnaire())
            ->put(route('back.incidents.update', $incident), [
                'type' => $incident->type,
                'title' => 'Titre corrigé après clôture',
                'description' => $incident->description,
                'address' => $incident->address,
                'zone_id' => $incident->zone_id,
                'infrastructure_id' => null,
                'latitude' => null,
                'longitude' => null,
                'severity' => $incident->severity,
                'priority' => $incident->priority,
                'assigned_to' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Titre corrigé après clôture', $incident->fresh()->title);
        $this->assertSame('traite', $incident->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_incident_created_already_treated_does_not_send_an_email(): void
    {
        Mail::fake();

        Incident::factory()->traite()->create();

        Mail::assertNothingSent();
    }

    public function test_invalid_citizen_email_is_handled_safely(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create(['email' => 'not-an-email']);
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        $this->transition($this->gestionnaire(), $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('traite', $incident->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_missing_citizen_or_owner_is_handled_safely(): void
    {
        Mail::fake();

        $citizenWithoutEmail = User::factory()->citoyen()->create(['email' => '']);
        $incidentWithoutEmail = Incident::factory()->create([
            'user_id' => $citizenWithoutEmail->id,
            'status' => 'en_cours',
        ]);
        $incidentWithoutOwner = Incident::factory()->create([
            'user_id' => null,
            'status' => 'en_cours',
        ]);

        $manager = $this->gestionnaire();

        $this->transition($manager, $incidentWithoutEmail, $this->resolutionPayload())
            ->assertSessionHasNoErrors();
        $this->transition($manager, $incidentWithoutOwner, $this->resolutionPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('traite', $incidentWithoutEmail->fresh()->status);
        $this->assertSame('traite', $incidentWithoutOwner->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_incident_owned_by_staff_does_not_email_the_staff_member(): void
    {
        Mail::fake();

        $managerOwner = $this->gestionnaire();
        $adminOwner = User::factory()->admin()->create();
        $incident = Incident::factory()->create([
            'user_id' => $managerOwner->id,
            'status' => 'en_cours',
        ]);
        $secondIncident = Incident::factory()->create([
            'user_id' => $adminOwner->id,
            'status' => 'en_cours',
        ]);

        $actor = $this->gestionnaire();

        $this->transition($actor, $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors();
        $this->transition($actor, $secondIncident, $this->resolutionPayload())
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_email_content_escapes_dynamic_values_and_links_only_to_the_citizen_detail_page(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create([
            'name' => 'Élodie Martin',
            'email' => 'elodie@example.test',
        ]);
        $zone = Zone::factory()->create(['nom' => 'Zone Littorale']);
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'zone_id' => $zone->id,
            'type' => 'qualite',
            'status' => 'en_cours',
            'title' => '<script>alert("titre")</script> Fuite avenue',
            'address' => '<img src=x onerror=alert(1)>',
        ]);
        $internalNote = 'NOTE INTERNE RÉSERVÉE AUX GESTIONNAIRES.';
        $internalComment = 'Commentaire de suivi interne.';

        $this->transition($this->gestionnaire(), $incident, [
            'status' => 'traite',
            'comment' => $internalComment,
            'resolution_note' => $internalNote,
        ])->assertSessionHasNoErrors();

        $incident->refresh();
        $mail = Mail::sent(IncidentResolvedCitizenMail::class)->first();

        $this->assertInstanceOf(IncidentResolvedCitizenMail::class, $mail);
        $this->assertSame(
            "Votre signalement {$incident->reference} a été traité — AquaSecure",
            $mail->envelope()->subject,
        );

        $html = $mail->render();
        $resolvedAt = $incident->resolved_at->copy()->locale('fr')->translatedFormat('d F Y à H:i');
        $citizenUrl = route('citizen.incidents.show', $incident);

        $this->assertStringContainsString(e($citizen->name), $html);
        $this->assertStringContainsString($incident->reference, $html);
        $this->assertStringContainsString(e($incident->title), $html);
        $this->assertStringContainsString(e($incident->typeLabel()), $html);
        $this->assertStringContainsString(e($incident->address), $html);
        $this->assertStringContainsString(e($zone->nom), $html);
        $this->assertStringContainsString('Traité', $html);
        $this->assertStringContainsString($resolvedAt, $html);
        $this->assertStringContainsString($citizenUrl, $html);
        $this->assertStringContainsString('Consulter mon signalement', $html);
        $this->assertStringNotContainsString(route('back.incidents.show', $incident), $html);
        $this->assertStringNotContainsString($internalNote, $html);
        $this->assertStringNotContainsString($internalComment, $html);
        $this->assertStringNotContainsString('<script>alert("titre")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function test_mail_failure_does_not_roll_back_the_status_update(): void
    {
        $citizen = User::factory()->citoyen()->create(['email' => 'citizen@example.test']);
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP transport unavailable.'));

        $this->transition($this->gestionnaire(), $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $incident->refresh();

        $this->assertSame('traite', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertDatabaseHas('incident_status_histories', [
            'incident_id' => $incident->id,
            'ancien_statut' => 'en_cours',
            'nouveau_statut' => 'traite',
        ]);
    }

    public function test_reopening_and_treating_again_is_a_new_treatment_event(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);
        $manager = $this->gestionnaire();

        $this->transition($manager, $incident, $this->resolutionPayload())
            ->assertSessionHasNoErrors();

        $this->transition($manager, $incident, [
            'status' => 'en_cours',
            'comment' => 'Réouverture après un nouveau constat sur le terrain.',
        ])->assertSessionHasNoErrors();

        $this->transition($manager, $incident, $this->resolutionPayload('Nouvelle intervention réalisée.'))
            ->assertSessionHasNoErrors();

        Mail::assertSentCount(2);
    }

    public function test_citizen_cannot_change_the_status_or_trigger_the_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'status' => 'en_cours',
        ]);

        $this->actingAs($citizen)
            ->patch(route('back.incidents.status', $incident), $this->resolutionPayload())
            ->assertForbidden();

        $this->assertSame('en_cours', $incident->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_citizen_detail_link_is_authorized_only_for_the_incident_owner(): void
    {
        $owner = User::factory()->citoyen()->create();
        $otherCitizen = User::factory()->citoyen()->create();
        $incident = Incident::factory()->traite()->create(['user_id' => $owner->id]);
        $url = route('citizen.incidents.show', $incident);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($otherCitizen)->get($url)->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk();
    }
}
