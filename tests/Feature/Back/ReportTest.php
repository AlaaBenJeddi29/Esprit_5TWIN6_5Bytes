<?php

namespace Tests\Feature\Back;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'fuite',
            'title' => 'Fuite signalée sur la voie publique',
            'description' => "Écoulement continu d'eau au niveau du regard.",
            'address' => '10 rue de la Fontaine',
            'reported_at' => now()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_guests_are_redirected_to_login_from_back_office(): void
    {
        $this->get(route('back.dashboard'))->assertRedirect(route('login'));
        $this->get(route('back.reports.index'))->assertRedirect(route('login'));
        $this->get(route('back.reports.create'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_reports_index(): void
    {
        $user = User::factory()->gestionnaire()->create();

        $this->actingAs($user)
            ->get(route('back.reports.index'))
            ->assertOk()
            ->assertSee('Signalements');
    }

    public function test_index_shows_empty_state_when_no_reports(): void
    {
        $user = User::factory()->gestionnaire()->create();

        $this->actingAs($user)
            ->get(route('back.reports.index'))
            ->assertOk()
            ->assertSee('Aucun signalement');
    }

    public function test_authenticated_user_can_create_a_report(): void
    {
        $user = User::factory()->gestionnaire()->create();

        $response = $this->actingAs($user)
            ->post(route('back.reports.store'), $this->validPayload());

        $report = Report::first();

        $this->assertNotNull($report);
        $response->assertRedirect(route('back.reports.show', $report));
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('nouveau', $report->status);
        $this->assertDatabaseHas('reports', ['title' => 'Fuite signalée sur la voie publique']);
    }

    public function test_store_validation_fails_with_invalid_data(): void
    {
        $user = User::factory()->gestionnaire()->create();

        $this->actingAs($user)
            ->from(route('back.reports.create'))
            ->post(route('back.reports.store'), [
                'type' => 'invalide',
                'title' => '',
                'description' => '',
            ])
            ->assertSessionHasErrors(['type', 'title', 'description']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_authenticated_user_can_view_and_update_a_report(): void
    {
        $user = User::factory()->gestionnaire()->create();
        $report = Report::factory()->create(['status' => 'nouveau']);

        $this->actingAs($user)
            ->get(route('back.reports.show', $report))
            ->assertOk()
            ->assertSee($report->title);

        $this->actingAs($user)
            ->get(route('back.reports.edit', $report))
            ->assertOk();

        $response = $this->actingAs($user)->put(route('back.reports.update', $report), $this->validPayload([
            'status' => 'traite',
            'title' => 'Titre mis à jour',
        ]));

        $response->assertRedirect(route('back.reports.show', $report));
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'traite',
            'title' => 'Titre mis à jour',
        ]);
    }

    public function test_update_validation_fails_with_invalid_status(): void
    {
        $user = User::factory()->gestionnaire()->create();
        $report = Report::factory()->create();

        $this->actingAs($user)
            ->from(route('back.reports.edit', $report))
            ->put(route('back.reports.update', $report), $this->validPayload(['status' => 'inexistant']))
            ->assertSessionHasErrors(['status']);
    }
}
