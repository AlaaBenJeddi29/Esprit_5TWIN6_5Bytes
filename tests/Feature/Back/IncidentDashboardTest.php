<?php

namespace Tests\Feature\Back;

use App\Models\Incident\Incident;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\IncidentStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-10-01';

    private const TO = '2026-10-31';

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Jeu de données de référence sur octobre 2026 :
     * 8 incidents dans la période (dont 1 rejeté et 1 doublon),
     * 1 incident hors période.
     */
    private function seedReferenceDataset(): void
    {
        $zoneA = Zone::factory()->create(['nom' => 'Zone Alpha']);
        $zoneB = Zone::factory()->create(['nom' => 'Zone Bêta']);
        Zone::factory()->create(['nom' => 'Zone Gamma']);

        $assignee = $this->gestionnaire();

        // 1 — critique ouvert, affecté, zone A
        Incident::factory()->enCours()->create([
            'severity' => 'critique',
            'zone_id' => $zoneA->id,
            'assigned_to' => $assignee->id,
            'reported_at' => '2026-10-02 09:00:00',
        ]);

        // 2 — critique ouvert, non affecté, zone A
        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'nouveau',
            'assigned_to' => null,
            'zone_id' => $zoneA->id,
            'reported_at' => '2026-10-03 09:00:00',
        ]);

        // 3 — critique ouvert, non affecté, zone B
        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'en_attente',
            'assigned_to' => null,
            'zone_id' => $zoneB->id,
            'reported_at' => '2026-10-04 09:00:00',
        ]);

        // 4 — critique traité en 5 h, zone B
        Incident::factory()->traite()->create([
            'severity' => 'critique',
            'zone_id' => $zoneB->id,
            'reported_at' => '2026-10-05 08:00:00',
            'resolved_at' => '2026-10-05 13:00:00',
        ]);

        // 5 — moyenne traité en 3 h, zone B
        Incident::factory()->traite()->create([
            'severity' => 'moyenne',
            'zone_id' => $zoneB->id,
            'reported_at' => '2026-10-06 08:00:00',
            'resolved_at' => '2026-10-06 11:00:00',
        ]);

        // 6 — rejeté : exclu du taux de résolution
        Incident::factory()->rejete()->create([
            'severity' => 'haute',
            'zone_id' => $zoneB->id,
            'reported_at' => '2026-10-07 08:00:00',
        ]);

        // 7 — doublon ouvert : exclu des non affectés et du dénominateur
        Incident::factory()->create([
            'severity' => 'haute',
            'status' => 'nouveau',
            'is_duplicate' => true,
            'zone_id' => $zoneB->id,
            'reported_at' => '2026-10-08 08:00:00',
        ]);

        // 8 — faible ouvert, non affecté, sans zone
        Incident::factory()->create([
            'severity' => 'faible',
            'status' => 'nouveau',
            'assigned_to' => null,
            'zone_id' => null,
            'reported_at' => '2026-10-09 08:00:00',
        ]);

        // 9 — hors période : ne doit jamais être compté
        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'nouveau',
            'assigned_to' => null,
            'zone_id' => $zoneA->id,
            'reported_at' => '2026-09-15 08:00:00',
        ]);
    }

    public function test_summary_computes_all_kpis_on_known_data(): void
    {
        $this->seedReferenceDataset();

        $summary = app(IncidentStatsService::class)->summary(self::FROM, self::TO);

        $this->assertSame(3, $summary['critical_open']);
        $this->assertSame(3, $summary['unassigned_open']);
        $this->assertSame(4.0, $summary['avg_resolution_h']);
        $this->assertSame(33.3, $summary['resolution_rate']);

        $byZone = collect($summary['by_zone'])->keyBy('zone');

        $this->assertCount(4, $summary['by_zone']);
        $this->assertSame(2, $byZone['Zone Alpha']['total']);
        $this->assertSame(2, $byZone['Zone Alpha']['open']);
        $this->assertSame(2, $byZone['Zone Alpha']['critical']);
        $this->assertSame(5, $byZone['Zone Bêta']['total']);
        $this->assertSame(2, $byZone['Zone Bêta']['open']);
        $this->assertSame(2, $byZone['Zone Bêta']['critical']);
        $this->assertSame(0, $byZone['Zone Gamma']['total']);
        $this->assertNull($byZone['Sans zone']['zone_id']);
        $this->assertSame(1, $byZone['Sans zone']['total']);
        $this->assertSame(1, $byZone['Sans zone']['open']);
        $this->assertSame(0, $byZone['Sans zone']['critical']);
    }

    public function test_rejected_and_duplicate_incidents_are_excluded_from_rate_and_unassigned(): void
    {
        Zone::factory()->create(['nom' => 'Zone Test']);

        Incident::factory()->rejete()->create([
            'status' => 'rejete',
            'assigned_to' => null,
            'reported_at' => '2026-10-05 08:00:00',
        ]);

        Incident::factory()->create([
            'status' => 'nouveau',
            'is_duplicate' => true,
            'assigned_to' => null,
            'reported_at' => '2026-10-06 08:00:00',
        ]);

        $summary = app(IncidentStatsService::class)->summary(self::FROM, self::TO);

        $this->assertSame(0, $summary['unassigned_open']);
        $this->assertNull($summary['resolution_rate']);
        $this->assertNull($summary['avg_resolution_h']);
    }

    public function test_indicators_are_null_or_zero_without_data(): void
    {
        Zone::factory()->create(['nom' => 'Zone Vide']);

        $summary = app(IncidentStatsService::class)->summary(self::FROM, self::TO);

        $this->assertSame(0, $summary['critical_open']);
        $this->assertSame(0, $summary['unassigned_open']);
        $this->assertNull($summary['avg_resolution_h']);
        $this->assertNull($summary['resolution_rate']);

        $this->assertCount(1, $summary['by_zone']);
        $this->assertSame('Zone Vide', $summary['by_zone'][0]['zone']);
        $this->assertSame(0, $summary['by_zone'][0]['total']);
        $this->assertSame(0, $summary['by_zone'][0]['open']);
        $this->assertSame(0, $summary['by_zone'][0]['critical']);
    }

    public function test_period_bounds_exclude_incidents_outside_the_range(): void
    {
        Zone::factory()->create(['nom' => 'Zone Test']);

        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'nouveau',
            'reported_at' => '2026-10-01 00:00:00',
        ]);

        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'nouveau',
            'reported_at' => '2026-10-31 23:59:59',
        ]);

        Incident::factory()->create([
            'severity' => 'critique',
            'status' => 'nouveau',
            'reported_at' => '2026-11-01 00:00:00',
        ]);

        $service = app(IncidentStatsService::class);

        $this->assertSame(2, $service->summary('2026-10-01', '2026-10-31')['critical_open']);
        $this->assertSame(1, $service->summary('2026-10-01', '2026-10-01')['critical_open']);
        $this->assertSame(1, $service->summary('2026-11-01', '2026-11-30')['critical_open']);
    }

    public function test_dashboard_page_displays_indicators_and_zone_table(): void
    {
        $this->seedReferenceDataset();

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.dashboard', [
                'period' => 'custom',
                'from' => self::FROM,
                'to' => self::TO,
            ]))
            ->assertOk()
            ->assertSee('Incidents critiques ouverts')
            ->assertSee('Incidents non affectés')
            ->assertSee('Délai moyen de résolution')
            ->assertSee('Taux de résolution')
            ->assertSee('Incidents par zone')
            ->assertSee('Zone Alpha')
            ->assertSee('Sans zone')
            ->assertSee('33,3')
            ->assertSee('4,0');
    }

    public function test_pdf_export_returns_a_pdf_document(): void
    {
        $this->seedReferenceDataset();

        $response = $this->actingAs($this->admin())
            ->get(route('back.incidents.dashboard.pdf', [
                'period' => 'custom',
                'from' => self::FROM,
                'to' => self::TO,
            ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'indicateurs-incidents-'.now()->format('Ymd').'.pdf',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('back.incidents.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_citizen_is_forbidden_from_the_dashboard(): void
    {
        $this->actingAs(User::factory()->citoyen()->create())
            ->get(route('back.incidents.dashboard'))
            ->assertForbidden();

        $this->get(route('back.incidents.dashboard.pdf'))
            ->assertForbidden();
    }

    public function test_admin_can_access_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('back.incidents.dashboard'))
            ->assertOk();
    }

    public function test_invalid_period_returns_validation_errors(): void
    {
        $admin = $this->admin();

        // Période personnalisée sans dates.
        $this->actingAs($admin)
            ->getJson(route('back.incidents.dashboard', ['period' => 'custom']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);

        // Date de début postérieure à la date de fin.
        $this->actingAs($admin)
            ->getJson(route('back.incidents.dashboard', [
                'period' => 'custom',
                'from' => '2026-10-31',
                'to' => '2026-10-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        // Période inconnue.
        $this->actingAs($admin)
            ->getJson(route('back.incidents.dashboard', ['period' => 'semaine']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    public function test_main_dashboard_integrates_incident_kpis(): void
    {
        $this->seedReferenceDataset();

        $this->actingAs($this->gestionnaire())
            ->get(route('back.dashboard'))
            ->assertOk()
            ->assertSee('Incidents critiques')
            ->assertSee('Non affectés')
            ->assertSee('incidentStatusChart')
            ->assertSee('incidentSeverityChart')
            ->assertSee('incidentTrendChart');
    }

    public function test_incident_index_filters_open_unassigned_incidents(): void
    {
        $assignee = $this->gestionnaire();

        [$unassigned, $assigned] = [
            Incident::factory()->create([
                'title' => 'Fuite non affectée ouverte',
                'status' => 'nouveau',
                'assigned_to' => null,
                'is_duplicate' => false,
            ]),
            Incident::factory()->enCours()->create([
                'title' => 'Fuite affectée en cours',
                'assigned_to' => $assignee->id,
            ]),
        ];

        $this->actingAs($this->gestionnaire())
            ->get(route('back.incidents.index', ['unassigned' => 1, 'open' => 1]))
            ->assertOk()
            ->assertSee($unassigned->title)
            ->assertDontSee($assigned->title);
    }
}
