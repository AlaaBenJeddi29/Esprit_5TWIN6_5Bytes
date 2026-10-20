<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AlertSubscription;
use App\Models\Incident\Incident;
use App\Models\Infrastructure\Zone;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function reportPayload(): array
    {
        return [
            'type' => 'fuite',
            'title' => 'Fuite signalée en back office',
            'description' => "Écoulement continu d'eau au niveau du regard.",
            'address' => '10 rue de la Fontaine',
            'reported_at' => now()->format('Y-m-d\TH:i'),
        ];
    }

    public function test_guest_is_redirected_to_login_on_protected_reads_and_writes(): void
    {
        $report = Report::factory()->create();
        $incident = Incident::factory()->create();
        $otherCitizen = User::factory()->citoyen()->create();
        $subscription = AlertSubscription::factory()->create(['user_id' => $otherCitizen->id]);

        $this->get(route('back.reports.index'))->assertRedirect(route('login'));

        $reportCount = Report::count();
        $this->post(route('back.reports.store'), $this->reportPayload())->assertRedirect(route('login'));
        $this->put(route('back.reports.update', $report), $this->reportPayload() + ['status' => 'nouveau'])->assertRedirect(route('login'));
        $this->patch(route('back.incidents.status', $incident), ['status' => 'traite'])->assertRedirect(route('login'));

        $this->get(route('citizen.dashboard'))->assertRedirect(route('login'));
        $this->post(route('citizen.incidents.comments.store', $incident), ['body' => 'Intrusion'])->assertRedirect(route('login'));
        $this->delete(route('citizen.subscriptions.destroy', $subscription))->assertRedirect(route('login'));

        $this->assertSame($reportCount, Report::count());
        $this->assertDatabaseHas('alert_subscriptions', ['id' => $subscription->id]);
    }

    public function test_citizen_can_access_permitted_front_office_pages_and_writes(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($citizen)->get(route('citizen.dashboard'))->assertOk();
        $this->actingAs($citizen)->get(route('citizen.incidents.index'))->assertOk();
        $this->actingAs($citizen)->get(route('citizen.incidents.create'))->assertOk();
        $this->actingAs($citizen)->get(route('citizen.subscriptions.index'))->assertOk();
        $this->actingAs($citizen)->get(route('citizen.feedbacks.index'))->assertOk();

        $this->actingAs($citizen)
            ->post(route('citizen.subscriptions.store'), [
                'zone_id' => $zone->id,
                'type_alerte' => 'qualite',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('alert_subscriptions', [
            'user_id' => $citizen->id,
            'zone_id' => $zone->id,
        ]);
    }

    public function test_citizen_cannot_reach_back_office_read_routes_directly(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $uris = [
            route('back.dashboard'),
            route('back.reports.index'),
            route('back.zones.index'),
            route('back.incidents.index'),
            route('back.projects.index'),
            route('back.quality-samples.index'),
            route('back.water-levels.index'),
            route('back.audit-events.index'),
            route('back.gestionnaires.index'),
        ];

        foreach ($uris as $uri) {
            $this->actingAs($citizen)->get($uri)->assertForbidden();
        }
    }

    public function test_citizen_cannot_perform_back_office_writes_even_with_admin_role_in_payload(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();
        $report = Report::factory()->create();
        $incident = Incident::factory()->create(['status' => 'nouveau']);

        $counts = [
            'reports' => Report::count(),
            'zones' => Zone::count(),
            'users' => User::count(),
        ];

        $this->actingAs($citizen)
            ->post(route('back.reports.store'), $this->reportPayload() + ['role' => UserRole::Admin->value])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->put(route('back.reports.update', $report), $this->reportPayload() + [
                'status' => 'en_cours',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->post(route('back.zones.store'), [
                'nom' => 'Zone Pirate',
                'commune' => 'Commune Pirate',
                'code_postal' => '75000',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->patch(route('back.incidents.status', $incident), [
                'status' => 'traite',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->post(route('back.gestionnaires.store'), [
                'name' => 'Compte Pirate',
                'email' => 'pirate@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->post(route('back.quality-planifications.store'), [
                'zone_id' => $zone->id,
                'date_prevue' => now()->addWeek()->format('Y-m-d'),
                'frequence' => 'hebdomadaire',
                'role' => UserRole::Admin->value,
            ])
            ->assertForbidden();

        $this->actingAs($citizen)
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $citizen->id,
                'zone_ids' => [$zone->id],
            ])
            ->assertForbidden();

        $this->assertSame($counts['reports'], Report::count());
        $this->assertSame($counts['zones'], Zone::count());
        $this->assertSame($counts['users'], User::count());
        $this->assertDatabaseMissing('users', ['email' => 'pirate@example.com']);
        $this->assertDatabaseCount('quality_planifications', 0);
        $this->assertDatabaseCount('quality_zone_assignments', 0);
        $this->assertSame('nouveau', $incident->fresh()->status);
    }

    public function test_gestionnaire_cannot_use_citizen_only_features(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)->get(route('citizen.dashboard'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('citizen.incidents.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('citizen.subscriptions.index'))->assertForbidden();

        $this->actingAs($gestionnaire)
            ->post(route('citizen.subscriptions.store'), [
                'zone_id' => $zone->id,
                'type_alerte' => 'qualite',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('alert_subscriptions', 0);
    }

    public function test_gestionnaire_cannot_manage_administrator_only_resources(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();
        $target = User::factory()->gestionnaire()->create();
        $zone = Zone::factory()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($gestionnaire)->get(route('back.gestionnaires.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.gestionnaires.create'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.gestionnaires.edit', $target))->assertForbidden();
        $this->actingAs($gestionnaire)->patch(route('back.gestionnaires.toggle', $target))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.quality-assignments.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.quality-planifications.create'))->assertForbidden();

        $this->actingAs($gestionnaire)
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $citizen->id,
                'zone_ids' => [$zone->id],
            ])
            ->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('quality_zone_assignments', 0);
    }

    public function test_profile_update_cannot_escalate_role_or_deactivate_account(): void
    {
        $citizen = User::factory()->citoyen()->create(['is_active' => true]);

        $this->actingAs($citizen)
            ->patch(route('profile.update'), [
                'name' => 'Nom Modifié',
                'email' => $citizen->email,
                'role' => UserRole::Admin->value,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $citizen->refresh();

        $this->assertSame('Nom Modifié', $citizen->name);
        $this->assertSame(UserRole::Citoyen, $citizen->role);
        $this->assertTrue($citizen->is_active);
    }

    public function test_citizen_cannot_view_or_comment_another_citizens_incident(): void
    {
        $owner = User::factory()->citoyen()->create();
        $intruder = User::factory()->citoyen()->create();
        $incident = Incident::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get(route('citizen.incidents.show', $incident))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('citizen.incidents.comments.store', $incident), ['body' => 'Commentaire intrus'])
            ->assertForbidden();

        $this->assertDatabaseCount('incident_comments', 0);
    }
}
