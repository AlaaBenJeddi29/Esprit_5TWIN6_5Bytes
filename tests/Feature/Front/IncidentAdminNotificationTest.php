<?php

namespace Tests\Feature\Front;

use App\Mail\NewIncidentAdminMail;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentAdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class IncidentAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function incidentPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'fuite',
            'title' => 'Fuite importante devant l\'école',
            'description' => 'Un écoulement continu inonde la chaussée depuis ce matin.',
            'address' => '12 rue des Écoles',
            'severity' => 'haute',
        ], $overrides);
    }

    public function test_valid_citizen_submission_emails_the_active_administrators(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create([
            'name' => 'Amina Ben Salah',
            'email' => 'amina@example.test',
        ]);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);
        $secondAdmin = User::factory()->admin()->create(['email' => 'admin-2@example.test']);
        $inactiveAdmin = User::factory()->admin()->create([
            'email' => 'inactive-admin@example.test',
            'is_active' => false,
        ]);
        $manager = User::factory()->gestionnaire()->create(['email' => 'manager@example.test']);
        $zone = Zone::factory()->create(['nom' => 'Zone Centre']);
        $infrastructure = Infrastructure::factory()->create([
            'zone_id' => $zone->id,
            'nom' => 'Réservoir Nord',
        ]);

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), $this->incidentPayload([
            'zone_id' => $zone->id,
            'infrastructure_id' => $infrastructure->id,
        ]));

        $incident = Incident::firstOrFail();

        $response
            ->assertRedirect(route('citizen.incidents.show', $incident))
            ->assertSessionHas('success', "Votre signalement {$incident->reference} a bien été enregistré.");

        $this->assertSame($citizen->id, $incident->user_id);
        $this->assertSame('nouveau', $incident->status);

        Mail::assertSentCount(2);

        Mail::assertSent(NewIncidentAdminMail::class, fn (NewIncidentAdminMail $mail): bool => $mail->hasTo($admin->email));
        Mail::assertSent(NewIncidentAdminMail::class, fn (NewIncidentAdminMail $mail): bool => $mail->hasTo($secondAdmin->email));

        Mail::assertNotSent(NewIncidentAdminMail::class, fn (NewIncidentAdminMail $mail): bool => $mail->hasTo($inactiveAdmin->email)
            || $mail->hasTo($manager->email)
            || $mail->hasTo($citizen->email));
    }

    public function test_email_contains_citizen_identity_saved_incident_details_and_secure_back_office_link(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create([
            'name' => 'Amina Ben Salah',
            'email' => 'amina@example.test',
        ]);
        User::factory()->admin()->create(['email' => 'admin@example.test']);
        $zone = Zone::factory()->create(['nom' => 'Zone Centre']);
        $infrastructure = Infrastructure::factory()->create([
            'zone_id' => $zone->id,
            'nom' => 'Réservoir Nord',
        ]);

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), $this->incidentPayload([
            'zone_id' => $zone->id,
            'infrastructure_id' => $infrastructure->id,
        ]))->assertRedirect();

        $incident = Incident::firstOrFail();
        $submittedAt = $incident->reported_at->copy()->locale('fr')->translatedFormat('d F Y à H:i');
        $detailUrl = route('back.incidents.show', $incident);

        $mail = Mail::sent(NewIncidentAdminMail::class)->first();

        $this->assertInstanceOf(NewIncidentAdminMail::class, $mail);
        $this->assertSame($incident->id, $mail->incident->id);
        $this->assertStringContainsString($incident->reference, $mail->envelope()->subject);

        $html = $mail->render();

        $this->assertStringContainsString('Nouveau signalement d\'incident', $html);
        $this->assertStringContainsString($citizen->name, $html);
        $this->assertStringContainsString($citizen->email, $html);
        $this->assertStringContainsString($incident->reference, $html);
        $this->assertStringContainsString(e($incident->title), $html);
        $this->assertStringContainsString(e($incident->description), $html);
        $this->assertStringContainsString(e($incident->typeLabel()), $html);
        $this->assertStringContainsString($incident->severityLabel(), $html);
        $this->assertStringContainsString($incident->priorityLabel(), $html);
        $this->assertStringContainsString($incident->statusLabel(), $html);
        $this->assertStringContainsString(e($zone->nom), $html);
        $this->assertStringContainsString(e($infrastructure->nom), $html);
        $this->assertStringContainsString(e($incident->address), $html);
        $this->assertStringContainsString($submittedAt, $html);
        $this->assertStringContainsString($detailUrl, $html);
        $this->assertStringContainsString('Consulter le signalement', $html);
    }

    public function test_invalid_submission_does_not_create_an_incident_or_send_an_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create();

        $this->actingAs($citizen)
            ->from(route('citizen.incidents.create'))
            ->post(route('citizen.incidents.store'), [
                'type' => 'inconnu',
                'title' => '',
                'description' => 'court',
            ])
            ->assertSessionHasErrors(['type', 'title', 'description']);

        $this->assertDatabaseCount('incidents', 0);
        Mail::assertNothingSent();
    }

    public function test_failed_incident_creation_does_not_send_an_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create();

        Incident::creating(function (): void {
            throw new RuntimeException('Database write failed.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($citizen)->post(route('citizen.incidents.store'), $this->incidentPayload());
            $this->fail('La création de l\'incident aurait dû échouer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database write failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('incidents', 0);
        Mail::assertNothingSent();
    }

    public function test_optional_incident_fields_are_rendered_gracefully(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create();

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'type' => 'pression',
            'title' => 'Pression trop faible',
            'description' => 'Le débit est très faible depuis hier soir.',
            'address' => null,
            'zone_id' => null,
            'infrastructure_id' => null,
            'severity' => null,
        ])->assertRedirect();

        $incident = Incident::firstOrFail();

        $this->assertNull($incident->address);
        $this->assertNull($incident->zone_id);
        $this->assertNull($incident->infrastructure_id);

        Mail::assertSent(NewIncidentAdminMail::class, function (NewIncidentAdminMail $mail) use ($incident): bool {
            $html = $mail->render();

            return str_contains($html, $incident->reference)
                && str_contains($html, 'Pression trop faible')
                && str_contains($html, 'Non renseignée')
                && str_contains($html, 'Non renseignées');
        });
    }

    public function test_repeated_processing_of_the_same_incident_does_not_duplicate_the_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create();
        $incident = Incident::factory()->create([
            'user_id' => $citizen->id,
            'title' => 'Fuite persistante',
        ]);

        $notifier = app(IncidentAdminNotifier::class);
        $notifier->notifyNewIncident($incident);
        $notifier->notifyNewIncident($incident);

        Mail::assertSentCount(1);
    }

    public function test_email_failure_does_not_prevent_the_saved_incident_or_the_success_response(): void
    {
        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create(['email' => 'admin@example.test']);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP transport unavailable.'));

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), $this->incidentPayload());

        $incident = Incident::firstOrFail();

        $response
            ->assertRedirect(route('citizen.incidents.show', $incident))
            ->assertSessionHas('success', "Votre signalement {$incident->reference} a bien été enregistré.");

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'user_id' => $citizen->id,
            'status' => 'nouveau',
        ]);
    }

    public function test_user_generated_content_is_escaped_in_the_email(): void
    {
        Mail::fake();

        $citizen = User::factory()->citoyen()->create();
        User::factory()->admin()->create();

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'type' => 'autre',
            'title' => '<script>alert("titre")</script>',
            'description' => "Première ligne\n<script>alert('description')</script>\nDernière ligne.",
            'address' => '<img src=x onerror=alert(1)>',
        ])->assertRedirect();

        Mail::assertSent(NewIncidentAdminMail::class, function (NewIncidentAdminMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, '&lt;script&gt;alert(&quot;titre&quot;)&lt;/script&gt;')
                && str_contains($html, '&lt;script&gt;alert(&#039;description&#039;)&lt;/script&gt;')
                && str_contains($html, '&lt;img src=x onerror=alert(1)&gt;')
                && ! str_contains($html, '<script>alert("titre")</script>')
                && ! str_contains($html, '<img src=x onerror=alert(1)>');
        });
    }

    public function test_incident_detail_link_is_protected_by_the_back_office_authorization(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create(['user_id' => $citizen->id]);
        $url = route('back.incidents.show', $incident);

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($citizen)->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertOk();
    }
}
