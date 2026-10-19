<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentMapTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function incident(array $overrides = []): Incident
    {
        return Incident::factory()->create($overrides);
    }

    private function mapData(User $user, array $query = [])
    {
        return $this->actingAs($user)
            ->getJson(route('back.incidents.map.data', $query));
    }

    public function test_gestionnaire_can_view_map_page(): void
    {
        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.map'))
            ->assertOk()
            ->assertSee('Carte des incidents')
            ->assertSee('Zones de vigilance')
            ->assertSee('Réinitialiser');
    }

    public function test_admin_can_view_map_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('back.incidents.map'))
            ->assertOk();
    }

    public function test_citizen_cannot_view_map_page(): void
    {
        $this->actingAs(User::factory()->citoyen()->create())
            ->get(route('back.incidents.map'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_map_page(): void
    {
        $this->get(route('back.incidents.map'))
            ->assertRedirect(route('login'));
    }

    public function test_json_endpoint_is_protected(): void
    {
        $this->getJson(route('back.incidents.map.data'))
            ->assertUnauthorized();

        $this->actingAs(User::factory()->citoyen()->create())
            ->getJson(route('back.incidents.map.data'))
            ->assertForbidden();
    }

    public function test_json_endpoint_returns_only_map_fields(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Zone Centre']);
        $infrastructure = Infrastructure::factory()->create([
            'nom' => 'Réservoir Nord',
            'zone_id' => $zone->id,
        ]);

        $incident = $this->incident([
            'zone_id' => $zone->id,
            'infrastructure_id' => $infrastructure->id,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'severity' => 'haute',
            'status' => 'nouveau',
            'description' => 'NOTE INTERNE À NE PAS EXPOSER',
        ]);

        $response = $this->mapData($this->gestionnaire())
            ->assertOk()
            ->assertJsonStructure([
                'incidents' => [[
                    'id',
                    'reference',
                    'title',
                    'severity',
                    'severity_label',
                    'status',
                    'status_label',
                    'reported_at',
                    'latitude',
                    'longitude',
                    'zone' => ['id', 'nom'],
                    'infrastructure' => ['id', 'nom'],
                    'url',
                ]],
                'clusters',
                'counts' => ['total', 'placed', 'displayed', 'unplaced', 'critical', 'unresolved', 'clusters'],
                'meta' => ['radius', 'limit', 'truncated'],
            ])
            ->assertJsonPath('incidents.0.id', $incident->id)
            ->assertJsonPath('incidents.0.zone.nom', 'Zone Centre')
            ->assertJsonPath('incidents.0.infrastructure.nom', 'Réservoir Nord')
            ->assertJsonPath('meta.radius', 500);

        $payload = $response->json('incidents.0');

        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('user', $payload);
        $this->assertArrayNotHasKey('comments', $payload);
        $this->assertStringContainsString($incident->reference, json_encode($payload));
    }

    public function test_incidents_without_valid_coordinates_are_excluded(): void
    {
        $valid = $this->incident(['latitude' => 48.8566, 'longitude' => 2.3522]);
        $missing = $this->incident(['latitude' => null, 'longitude' => null]);
        $outOfRange = $this->incident(['latitude' => 120, 'longitude' => 200]);

        $response = $this->mapData($this->gestionnaire())
            ->assertOk()
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.id', $valid->id)
            ->assertJsonPath('counts.total', 3)
            ->assertJsonPath('counts.placed', 1)
            ->assertJsonPath('counts.displayed', 1)
            ->assertJsonPath('counts.unplaced', 2);

        $response->assertJsonMissing(['reference' => $missing->reference]);
        $response->assertJsonMissing(['reference' => $outOfRange->reference]);
    }

    public function test_filters_combine_severity_status_zone_infrastructure_and_period(): void
    {
        $zoneA = Zone::factory()->create();
        $zoneB = Zone::factory()->create();
        $infrastructureA = Infrastructure::factory()->create(['zone_id' => $zoneA->id]);
        $infrastructureB = Infrastructure::factory()->create(['zone_id' => $zoneB->id]);

        $target = $this->incident([
            'zone_id' => $zoneA->id,
            'infrastructure_id' => $infrastructureA->id,
            'severity' => 'critique',
            'status' => 'traite',
            'reported_at' => '2026-10-10 10:00:00',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);

        $this->incident([
            'zone_id' => $zoneA->id,
            'infrastructure_id' => $infrastructureA->id,
            'severity' => 'haute',
            'status' => 'nouveau',
            'reported_at' => '2026-10-11 10:00:00',
            'latitude' => 48.8567,
            'longitude' => 2.3523,
        ]);

        $criticalInZoneB = $this->incident([
            'zone_id' => $zoneB->id,
            'infrastructure_id' => $infrastructureB->id,
            'severity' => 'critique',
            'status' => 'nouveau',
            'reported_at' => '2026-10-12 10:00:00',
            'latitude' => 48.8600,
            'longitude' => 2.3600,
        ]);

        $oldTreated = $this->incident([
            'zone_id' => $zoneB->id,
            'infrastructure_id' => $infrastructureB->id,
            'severity' => 'faible',
            'status' => 'traite',
            'reported_at' => '2025-01-01 10:00:00',
            'latitude' => 48.8601,
            'longitude' => 2.3601,
        ]);

        $resolvedSeverity = $this->mapData($this->gestionnaire(), ['severity' => 'critique'])
            ->assertOk()
            ->assertJsonCount(2, 'incidents');
        $this->assertEqualsCanonicalizing(
            [$target->id, $criticalInZoneB->id],
            collect($resolvedSeverity->json('incidents'))->pluck('id')->all(),
        );

        $resolvedStatus = $this->mapData($this->gestionnaire(), ['status' => 'traite'])
            ->assertOk()
            ->assertJsonCount(2, 'incidents');
        $this->assertEqualsCanonicalizing(
            [$target->id, $oldTreated->id],
            collect($resolvedStatus->json('incidents'))->pluck('id')->all(),
        );

        $resolvedZone = $this->mapData($this->gestionnaire(), ['zone_id' => $zoneA->id])
            ->assertOk()
            ->assertJsonCount(2, 'incidents');

        $resolvedInfrastructure = $this->mapData($this->gestionnaire(), ['infrastructure_id' => $infrastructureB->id])
            ->assertOk()
            ->assertJsonCount(2, 'incidents');

        $resolvedPeriod = $this->mapData($this->gestionnaire(), ['date_from' => '2026-10-01'])
            ->assertOk()
            ->assertJsonCount(3, 'incidents');

        // Une borne de fin seule reste valide, sans date de début.
        $this->mapData($this->gestionnaire(), ['date_to' => '2026-10-31'])
            ->assertOk()
            ->assertJsonCount(4, 'incidents');

        $this->mapData($this->gestionnaire(), [
            'severity' => 'critique',
            'status' => 'traite',
            'zone_id' => $zoneA->id,
            'infrastructure_id' => $infrastructureA->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ])
            ->assertOk()
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.id', $target->id);
    }

    public function test_two_nearby_critical_incidents_form_a_cluster(): void
    {
        $first = $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $second = $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8570,
            'longitude' => 2.3525,
        ]);

        $response = $this->mapData($this->gestionnaire(), ['radius' => 500])
            ->assertOk()
            ->assertJsonCount(1, 'clusters')
            ->assertJsonPath('clusters.0.count', 2)
            ->assertJsonPath('clusters.0.radius', 500)
            ->assertJsonPath('counts.clusters', 1);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($response->json('clusters.0.incidents'))->pluck('id')->all(),
        );
    }

    public function test_no_cluster_when_only_one_critical_incident_exists(): void
    {
        $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);

        $this->incident([
            'severity' => 'haute',
            'latitude' => 48.8567,
            'longitude' => 2.3523,
        ]);

        $this->mapData($this->gestionnaire(), ['radius' => 500])
            ->assertOk()
            ->assertJsonCount(0, 'clusters')
            ->assertJsonPath('counts.clusters', 0);
    }

    public function test_no_cluster_when_critical_incidents_are_beyond_the_radius(): void
    {
        $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8666,
            'longitude' => 2.3522,
        ]);

        $this->mapData($this->gestionnaire(), ['radius' => 500])
            ->assertOk()
            ->assertJsonCount(0, 'clusters');

        $this->mapData($this->gestionnaire(), ['radius' => 1500])
            ->assertOk()
            ->assertJsonCount(1, 'clusters')
            ->assertJsonPath('clusters.0.count', 2);
    }

    public function test_cluster_does_not_count_the_same_incident_twice(): void
    {
        $incidents = collect([
            ['latitude' => 48.8566, 'longitude' => 2.3522],
            ['latitude' => 48.8570, 'longitude' => 2.3524],
            ['latitude' => 48.8568, 'longitude' => 2.3527],
        ])->map(fn (array $coordinates) => $this->incident([
            'severity' => 'critique',
            ...$coordinates,
        ]));

        $response = $this->mapData($this->gestionnaire(), ['radius' => 500])
            ->assertOk()
            ->assertJsonCount(1, 'clusters')
            ->assertJsonPath('clusters.0.count', 3);

        $ids = collect($response->json('clusters.0.incidents'))->pluck('id');

        $this->assertCount(3, $ids);
        $this->assertCount(3, $ids->unique());
        $this->assertEqualsCanonicalizing($incidents->pluck('id')->all(), $ids->all());
    }

    public function test_overlapping_groups_are_deterministic_and_do_not_chain(): void
    {
        // A et B sont à ~445 m, B et C à ~445 m, A et C à ~890 m.
        // Ancre A : B rejoint A. C reste hors du groupe (pas de fusion en chaîne).
        $first = $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $second = $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8606,
            'longitude' => 2.3522,
        ]);
        $third = $this->incident([
            'severity' => 'critique',
            'latitude' => 48.8646,
            'longitude' => 2.3522,
        ]);

        $firstResponse = $this->mapData($this->gestionnaire(), ['radius' => 500])->assertOk();
        $secondResponse = $this->mapData($this->gestionnaire(), ['radius' => 500])->assertOk();

        $this->assertSame($firstResponse->json('clusters'), $secondResponse->json('clusters'));
        $this->assertCount(1, $firstResponse->json('clusters'));

        $clusterIds = collect($firstResponse->json('clusters.0.incidents'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $clusterIds);
        $this->assertNotContains($third->id, $clusterIds);
    }

    public function test_missing_zone_and_infrastructure_do_not_break_payload(): void
    {
        $incident = $this->incident([
            'zone_id' => null,
            'infrastructure_id' => null,
            'latitude' => 43.6047,
            'longitude' => 1.4442,
        ]);

        $this->mapData($this->gestionnaire())
            ->assertOk()
            ->assertJsonCount(1, 'incidents')
            ->assertJsonPath('incidents.0.id', $incident->id)
            ->assertJsonPath('incidents.0.zone', null)
            ->assertJsonPath('incidents.0.infrastructure', null);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = $this->gestionnaire();

        $this->mapData($user, ['severity' => 'extreme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('severity');

        $this->mapData($user, ['status' => 'inconnu'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->mapData($user, ['zone_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('zone_id');

        $this->mapData($user, ['infrastructure_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('infrastructure_id');

        $this->mapData($user, ['date_from' => '2026-10-10', 'date_to' => '2026-10-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');

        $this->mapData($user, ['radius' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('radius');

        $this->mapData($user, ['radius' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('radius');
    }

    public function test_counts_reflect_filters_and_open_statuses(): void
    {
        $this->incident(['severity' => 'critique', 'status' => 'nouveau', 'latitude' => 48.8566, 'longitude' => 2.3522]);
        $this->incident(['severity' => 'haute', 'status' => 'en_cours', 'latitude' => 48.8567, 'longitude' => 2.3523]);
        $this->incident(['severity' => 'moyenne', 'status' => 'traite', 'latitude' => 48.8568, 'longitude' => 2.3524]);
        $this->incident(['severity' => 'faible', 'status' => 'rejete', 'latitude' => 48.8569, 'longitude' => 2.3525]);
        $this->incident(['severity' => 'critique', 'status' => 'en_attente', 'latitude' => null, 'longitude' => null]);

        $this->mapData($this->gestionnaire())
            ->assertOk()
            ->assertJsonPath('counts.total', 5)
            ->assertJsonPath('counts.placed', 4)
            ->assertJsonPath('counts.displayed', 4)
            ->assertJsonPath('counts.unplaced', 1)
            ->assertJsonPath('counts.critical', 2)
            ->assertJsonPath('counts.unresolved', 3);
    }
}
