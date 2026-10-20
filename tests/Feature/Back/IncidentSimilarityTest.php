<?php

namespace Tests\Feature\Back;

use App\Jobs\DetectIncidentSimilarities;
use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentSimilarityService;
use App\Services\SimilarityReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class IncidentSimilarityTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(array $overrides = []): Incident
    {
        return Incident::factory()->create(array_merge([
            'zone_id' => Zone::factory(),
            'type' => 'fuite',
            'title' => "Fuite d'eau sur la chaussée devant l'école",
            'description' => "Une fuite importante d'eau potable inonde la chaussée devant l'école depuis ce matin.",
            'severity' => 'haute',
            'status' => 'nouveau',
            'priority' => 'haute',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'is_duplicate' => false,
            'duplicate_of_id' => null,
            'reported_at' => now(),
        ], $overrides));
    }

    public function test_candidate_selection_excludes_self_other_zones_rejected_and_out_of_window(): void
    {
        $zoneA = Zone::factory()->create();
        $zoneB = Zone::factory()->create();

        $source = $this->incident(['zone_id' => $zoneA->id]);
        $sameZone = $this->incident(['zone_id' => $zoneA->id]);
        $otherZone = $this->incident(['zone_id' => $zoneB->id]);
        $rejected = $this->incident(['zone_id' => $zoneA->id, 'status' => 'rejete']);
        $outOfWindow = $this->incident([
            'zone_id' => $zoneA->id,
            'reported_at' => now()->subDays(45),
        ]);

        $ids = app(IncidentSimilarityService::class)
            ->detect($source)
            ->pluck('similar_incident_id')
            ->all();

        $this->assertContains($sameZone->id, $ids);
        $this->assertNotContains($source->id, $ids);
        $this->assertNotContains($otherZone->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
        $this->assertNotContains($outOfWindow->id, $ids);
    }

    public function test_candidate_order_is_deterministic_when_scores_and_timestamps_tie(): void
    {
        $source = $this->incident();
        $reportedAt = now()->startOfSecond();
        $first = $this->incident(['zone_id' => $source->zone_id, 'reported_at' => $reportedAt]);
        $second = $this->incident(['zone_id' => $source->zone_id, 'reported_at' => $reportedAt]);

        $ids = app(IncidentSimilarityService::class)
            ->detect($source)
            ->pluck('similar_incident_id')
            ->all();

        // Même score et même horodatage : l'identifiant décroissant tranche,
        // afin que MySQL et MariaDB produisent le même ordre.
        $this->assertSame([$second->id, $first->id], $ids);
    }

    public function test_detection_is_idempotent_and_never_creates_self_similarity(): void
    {
        $source = $this->incident();
        $candidate = $this->incident(['zone_id' => $source->zone_id]);

        $service = app(IncidentSimilarityService::class);
        $service->detect($source);
        $service->detect($source);

        $this->assertSame(1, IncidentSimilarity::query()
            ->where('incident_id', $source->id)
            ->where('similar_incident_id', $candidate->id)
            ->count());

        $this->assertSame(0, IncidentSimilarity::query()
            ->where('incident_id', $source->id)
            ->where('similar_incident_id', $source->id)
            ->count());
    }

    public function test_rerun_replaces_pending_suggestions_but_preserves_decisions(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $first = $this->incident(['zone_id' => $source->zone_id]);
        $second = $this->incident(['zone_id' => $source->zone_id]);

        $service = app(IncidentSimilarityService::class);
        $service->detect($source);

        $firstSuggestion = IncidentSimilarity::query()
            ->where('incident_id', $source->id)
            ->where('similar_incident_id', $first->id)
            ->firstOrFail();

        app(SimilarityReviewService::class)->decide($firstSuggestion, 'ignorer', 'Déjà connu.', $gestionnaire);

        $service->detect($source);

        $firstSuggestion->refresh();

        $this->assertSame(IncidentSimilarity::STATUS_REJECTED, $firstSuggestion->status);
        $this->assertSame('Déjà connu.', $firstSuggestion->decision_note);

        $this->assertDatabaseHas('incident_similarities', [
            'incident_id' => $source->id,
            'similar_incident_id' => $second->id,
            'status' => IncidentSimilarity::STATUS_SUGGESTED,
        ]);

        $this->assertSame(1, IncidentSimilarity::query()
            ->where('incident_id', $source->id)
            ->where('similar_incident_id', $first->id)
            ->count());
    }

    public function test_duplicate_decision_uses_existing_duplicate_fields_without_changing_status(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $target = $this->incident(['zone_id' => $source->zone_id]);

        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $target->id,
            'score' => 88,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'doublon',
                'note' => 'Même fuite au même endroit.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $similarity->refresh();
        $source->refresh();

        $this->assertSame(IncidentSimilarity::STATUS_DUPLICATE, $similarity->status);
        $this->assertSame($gestionnaire->id, $similarity->reviewed_by);
        $this->assertNotNull($similarity->reviewed_at);
        $this->assertSame('Même fuite au même endroit.', $similarity->decision_note);

        $this->assertTrue($source->is_duplicate);
        $this->assertSame($target->id, $source->duplicate_of_id);
        $this->assertSame('nouveau', $source->status);
    }

    public function test_link_and_ignore_decisions_are_recorded(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();

        $link = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
        ]);

        $ignore = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $link]), [
                'decision' => 'lie',
                'note' => 'Même secteur, problème voisin.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $ignore]), [
                'decision' => 'ignorer',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(IncidentSimilarity::STATUS_LINKED, $link->fresh()->status);
        $this->assertSame(IncidentSimilarity::STATUS_REJECTED, $ignore->fresh()->status);
        $this->assertSame('Même secteur, problème voisin.', $link->fresh()->decision_note);
    }

    public function test_a_second_decision_on_the_same_suggestion_is_refused(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
            'status' => IncidentSimilarity::STATUS_LINKED,
            'reviewed_by' => $gestionnaire->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(IncidentSimilarity::STATUS_LINKED, $similarity->fresh()->status);
    }

    public function test_decision_on_a_suggestion_removed_by_a_rerun_is_refused_gracefully(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();

        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
        ]);

        $similarity->delete();

        $this->expectException(ValidationException::class);

        app(SimilarityReviewService::class)->decide($similarity, 'ignorer', null, $gestionnaire);
    }

    public function test_duplicate_decision_is_refused_when_incident_is_already_duplicate_of_another(): void
    {
        $gestionnaire = $this->gestionnaire();
        $other = $this->incident();
        $source = $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $other->id,
        ]);
        $target = $this->incident();

        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $target->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $similarity->fresh()->status);
        $this->assertSame($other->id, $source->fresh()->duplicate_of_id);
    }

    public function test_duplicate_decision_is_refused_when_the_target_is_already_a_duplicate(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $other = $this->incident();
        $target = $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $other->id,
        ]);

        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $target->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $similarity->fresh()->status);
        $this->assertFalse($source->fresh()->is_duplicate);
        $this->assertNull($source->fresh()->duplicate_of_id);
        $this->assertSame('nouveau', $source->fresh()->status);
        $this->assertSame($other->id, $target->fresh()->duplicate_of_id);
    }

    public function test_duplicate_decision_is_refused_when_the_source_is_already_the_target_of_a_duplicate(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $target = $this->incident();

        $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $source->id,
        ]);

        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $target->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $similarity->fresh()->status);
        $this->assertFalse($source->fresh()->is_duplicate);
        $this->assertNull($source->fresh()->duplicate_of_id);
        $this->assertSame('nouveau', $source->fresh()->status);
        $this->assertFalse($target->fresh()->is_duplicate);
    }

    public function test_duplicate_decisions_cannot_create_a_cycle(): void
    {
        $gestionnaire = $this->gestionnaire();
        $first = $this->incident();
        $second = $this->incident();

        $firstToSecond = IncidentSimilarity::factory()->create([
            'incident_id' => $first->id,
            'similar_incident_id' => $second->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$first, $firstToSecond]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($first->fresh()->is_duplicate);
        $this->assertSame($second->id, $first->fresh()->duplicate_of_id);

        $secondToFirst = IncidentSimilarity::factory()->create([
            'incident_id' => $second->id,
            'similar_incident_id' => $first->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$second, $secondToFirst]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertFalse($second->fresh()->is_duplicate);
        $this->assertNull($second->fresh()->duplicate_of_id);
        $this->assertSame('nouveau', $second->fresh()->status);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $secondToFirst->fresh()->status);
        $this->assertSame($second->id, $first->fresh()->duplicate_of_id);
    }

    public function test_duplicate_decision_is_refused_for_a_cycle_through_a_chain(): void
    {
        $gestionnaire = $this->gestionnaire();
        $first = $this->incident();
        $second = $this->incident();
        $third = $this->incident();

        $first->update(['is_duplicate' => true, 'duplicate_of_id' => $second->id]);
        $second->update(['is_duplicate' => true, 'duplicate_of_id' => $third->id]);

        $thirdToFirst = IncidentSimilarity::factory()->create([
            'incident_id' => $third->id,
            'similar_incident_id' => $first->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$third, $thirdToFirst]), [
                'decision' => 'doublon',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertFalse($third->fresh()->is_duplicate);
        $this->assertNull($third->fresh()->duplicate_of_id);
        $this->assertSame('nouveau', $third->fresh()->status);
        $this->assertSame(IncidentSimilarity::STATUS_SUGGESTED, $thirdToFirst->fresh()->status);
        $this->assertSame($second->id, $first->fresh()->duplicate_of_id);
        $this->assertSame($third->id, $second->fresh()->duplicate_of_id);
    }

    public function test_link_and_ignore_decisions_remain_allowed_when_duplicate_guards_apply(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $other = $this->incident();

        $duplicateTarget = $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $other->id,
        ]);

        $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $source->id,
        ]);

        $link = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $duplicateTarget->id,
        ]);

        $secondDuplicateTarget = $this->incident([
            'is_duplicate' => true,
            'duplicate_of_id' => $other->id,
        ]);

        $ignore = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $secondDuplicateTarget->id,
        ]);

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $link]), [
                'decision' => 'lie',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $ignore]), [
                'decision' => 'ignorer',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(IncidentSimilarity::STATUS_LINKED, $link->fresh()->status);
        $this->assertSame(IncidentSimilarity::STATUS_REJECTED, $ignore->fresh()->status);
        $this->assertFalse($source->fresh()->is_duplicate);
        $this->assertNull($source->fresh()->duplicate_of_id);
        $this->assertSame('nouveau', $source->fresh()->status);
    }

    public function test_decision_route_is_rate_limited(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
        ]);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($gestionnaire)
                ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                    'decision' => 'ignorer',
                ])
                ->assertRedirect();
        }

        $this->actingAs($gestionnaire)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'ignorer',
            ])
            ->assertStatus(429);
    }

    public function test_similarity_endpoints_are_protected(): void
    {
        $source = $this->incident();
        $similarity = IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $this->incident()->id,
        ]);

        $this->post(route('back.incidents.similarities.detect', $source))
            ->assertRedirect(route('login'));

        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->post(route('back.incidents.similarities.detect', $source))
            ->assertForbidden();

        $this->actingAs($citizen)
            ->patch(route('back.incidents.similarities.decide', [$source, $similarity]), [
                'decision' => 'ignorer',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_relaunch_detection(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $this->incident(['zone_id' => $source->zone_id]);

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.similarities.detect', $source))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, IncidentSimilarity::query()->where('incident_id', $source->id)->count());
    }

    public function test_recurring_problem_is_detected_and_hidden_from_citizens(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();
        $source = $this->incident([
            'user_id' => $citizen->id,
            'zone_id' => $zone->id,
        ]);

        $this->incident(['zone_id' => $zone->id]);
        $this->incident(['zone_id' => $zone->id]);
        $this->incident(['zone_id' => $zone->id]);

        $service = app(IncidentSimilarityService::class);
        $service->detect($source);

        $recurring = $service->recurringProblem($source);

        $this->assertSame(3, $recurring['count']);
        $this->assertTrue($recurring['is_recurring']);

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.show', $source))
            ->assertOk()
            ->assertSee('Problème récurrent');

        $this->actingAs($citizen)
            ->get(route('citizen.incidents.show', $source))
            ->assertOk()
            ->assertDontSee('Incidents similaires');
    }

    public function test_no_candidates_shows_an_empty_state(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.similarities.detect', $source))
            ->assertRedirect()
            ->assertSessionHas('success', 'Aucun incident similaire détecté.');

        $this->assertDatabaseCount('incident_similarities', 0);

        $this->actingAs($gestionnaire)
            ->get(route('back.incidents.show', $source))
            ->assertOk()
            ->assertSee('Aucune suggestion pour le moment.', false);
    }

    public function test_detection_job_is_dispatched_after_citizen_creation(): void
    {
        Queue::fake();

        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'type' => 'fuite',
            'title' => "Fuite devant l'école",
            'description' => "Une fuite d'eau importante est visible devant l'école depuis ce matin.",
            'zone_id' => $zone->id,
            'severity' => 'haute',
        ])->assertRedirect();

        $incident = Incident::query()->latest('id')->firstOrFail();

        Queue::assertPushed(
            DetectIncidentSimilarities::class,
            fn (DetectIncidentSimilarities $job) => $job->incidentId === $incident->id,
        );
    }

    public function test_detection_job_is_dispatched_after_back_office_creation(): void
    {
        Queue::fake();

        $this->actingAs($this->gestionnaire())->post(route('back.incidents.store'), [
            'type' => 'fuite',
            'title' => "Fuite devant l'école",
            'description' => "Une fuite d'eau importante est visible devant l'école depuis ce matin.",
            'zone_id' => Zone::factory()->create()->id,
            'severity' => 'haute',
            'priority' => 'haute',
        ])->assertRedirect();

        $incident = Incident::query()->latest('id')->firstOrFail();

        Queue::assertPushed(
            DetectIncidentSimilarities::class,
            fn (DetectIncidentSimilarities $job) => $job->incidentId === $incident->id,
        );
    }

    public function test_a_failing_detection_never_blocks_citizen_creation(): void
    {
        $this->mock(IncidentSimilarityService::class, function ($mock) {
            $mock->shouldReceive('detect')->once()->andThrow(new RuntimeException('Échec simulé.'));
        });

        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'type' => 'fuite',
            'title' => "Fuite devant l'école",
            'description' => "Une fuite d'eau importante est visible devant l'école depuis ce matin.",
            'zone_id' => $zone->id,
            'severity' => 'haute',
        ])->assertRedirect();

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_detection_button_is_rate_limited(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($gestionnaire)
                ->post(route('back.incidents.similarities.detect', $source))
                ->assertRedirect();
        }

        $this->actingAs($gestionnaire)
            ->post(route('back.incidents.similarities.detect', $source))
            ->assertStatus(429);
    }

    public function test_incident_list_shows_a_badge_and_supports_the_similarity_filter(): void
    {
        $gestionnaire = $this->gestionnaire();
        $source = $this->incident();
        $candidate = $this->incident(['zone_id' => $source->zone_id]);

        IncidentSimilarity::factory()->create([
            'incident_id' => $source->id,
            'similar_incident_id' => $candidate->id,
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.incidents.index'))
            ->assertOk()
            ->assertSee('1 similaire');

        $this->actingAs($gestionnaire)
            ->get(route('back.incidents.index', ['similar' => 1]))
            ->assertOk()
            ->assertSee($source->reference)
            ->assertDontSee($candidate->reference);
    }

    public function test_artisan_command_supports_dry_run_then_persists(): void
    {
        $zone = Zone::factory()->create();
        $this->incident(['zone_id' => $zone->id]);
        $this->incident(['zone_id' => $zone->id]);

        $this->artisan('incidents:detect-similar', ['--days' => 30, '--dry-run' => true])
            ->expectsOutputToContain('suggestion(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('incident_similarities', 0);

        $this->artisan('incidents:detect-similar', ['--days' => 30])
            ->assertSuccessful();

        $this->assertGreaterThan(0, IncidentSimilarity::count());
    }
}
