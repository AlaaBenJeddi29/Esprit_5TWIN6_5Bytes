<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Quality\QualityPlanification;
use App\Models\Quality\QualityZoneAssignment;
use App\Models\Quality\WaterQualitySample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QualityPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow();
        config(['services.gemini.api_key' => null]);
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    private function assign(User $user, Zone ...$zones): void
    {
        foreach ($zones as $zone) {
            QualityZoneAssignment::create([
                'user_id' => $user->id,
                'zone_id' => $zone->id,
            ]);
        }
    }

    public function test_admin_can_create_update_and_delete_a_planification(): void
    {
        $admin = $this->admin();
        $zone = Zone::factory()->create(['nom' => 'Zone Planifiée']);

        $this->actingAs($admin)
            ->get(route('back.quality-planifications.create'))
            ->assertOk()
            ->assertSee('Planifier un contrôle');

        $this->actingAs($admin)
            ->post(route('back.quality-planifications.store'), [
                'zone_id' => $zone->id,
                'date_prevue' => '2026-11-02',
                'frequence' => 'hebdomadaire',
                'notes' => 'Contrôle hebdomadaire du réservoir.',
            ])
            ->assertRedirect(route('back.quality-planifications.index'));

        $planification = QualityPlanification::firstOrFail();

        $this->assertSame($zone->id, $planification->zone_id);
        $this->assertSame($admin->id, $planification->user_id);
        $this->assertSame('hebdomadaire', $planification->frequence);
        $this->assertSame('2026-11-02', $planification->date_prevue->format('Y-m-d'));

        $this->actingAs($admin)
            ->get(route('back.quality-planifications.edit', $planification))
            ->assertOk()
            ->assertSee('Modifier le contrôle du 02/11/2026');

        $this->actingAs($admin)
            ->put(route('back.quality-planifications.update', $planification), [
                'zone_id' => $zone->id,
                'date_prevue' => '2026-11-09',
                'frequence' => 'mensuelle',
            ])
            ->assertRedirect(route('back.quality-planifications.index'));

        $planification->refresh();
        $this->assertSame('2026-11-09', $planification->date_prevue->format('Y-m-d'));
        $this->assertSame('mensuelle', $planification->frequence);

        $this->actingAs($admin)
            ->delete(route('back.quality-planifications.destroy', $planification))
            ->assertRedirect(route('back.quality-planifications.index'));

        $this->assertDatabaseMissing('quality_planifications', ['id' => $planification->id]);
    }

    public function test_planification_validation_rejects_invalid_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('back.quality-planifications.store'), [
                'zone_id' => 999999,
                'date_prevue' => 'pas-une-date',
                'frequence' => 'quotidienne',
            ])
            ->assertSessionHasErrors(['zone_id', 'date_prevue', 'frequence']);

        $this->assertDatabaseCount('quality_planifications', 0);
    }

    public function test_gestionnaire_cannot_create_update_delete_or_assign(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $planification = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-11-02',
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($gestionnaire)->get(route('back.quality-planifications.create'))->assertForbidden();
        $this->actingAs($gestionnaire)->post(route('back.quality-planifications.store'), [
            'zone_id' => $zone->id,
            'date_prevue' => '2026-11-02',
            'frequence' => 'mensuelle',
        ])->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.quality-planifications.edit', $planification))->assertForbidden();
        $this->actingAs($gestionnaire)->put(route('back.quality-planifications.update', $planification), [
            'zone_id' => $zone->id,
            'date_prevue' => '2026-11-03',
            'frequence' => 'mensuelle',
        ])->assertForbidden();
        $this->actingAs($gestionnaire)->delete(route('back.quality-planifications.destroy', $planification))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.quality-assignments.index'))->assertForbidden();
        $this->actingAs($gestionnaire)->put(route('back.quality-assignments.update'), [
            'gestionnaire_id' => $gestionnaire->id,
            'zone_ids' => [$zone->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('quality_planifications', [
            'id' => $planification->id,
            'date_prevue' => '2026-11-02',
        ]);
        $this->assertDatabaseCount('quality_zone_assignments', 0);
    }

    public function test_gestionnaire_only_sees_controls_of_assigned_zones(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zoneAutorisee = Zone::factory()->create(['nom' => 'Zone Autorisée']);
        $zoneInterdite = Zone::factory()->create(['nom' => 'Zone Interdite']);
        $this->assign($gestionnaire, $zoneAutorisee);

        QualityPlanification::create([
            'zone_id' => $zoneAutorisee->id,
            'date_prevue' => '2026-11-02',
            'frequence' => 'mensuelle',
        ]);
        $controleInterdit = QualityPlanification::create([
            'zone_id' => $zoneInterdite->id,
            'date_prevue' => '2026-11-03',
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index'))
            ->assertOk()
            ->assertSee('Zone Autorisée')
            ->assertDontSee('Zone Interdite');

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index', ['zone_id' => $zoneInterdite->id]))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.calendar', ['zone_id' => $zoneInterdite->id]))
            ->assertForbidden();

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.create', ['planification' => $controleInterdit->id]))
            ->assertForbidden();
    }

    public function test_gestionnaire_without_assignment_has_an_empty_scope(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Non Affectée']);

        QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-11-02',
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index'))
            ->assertOk()
            ->assertDontSee('Zone Non Affectée')
            ->assertViewHas('planifications', fn ($planifications) => $planifications->total() === 0);
    }

    public function test_statuses_are_derived_from_the_planned_date_and_the_linked_sample(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');

        $zone = Zone::factory()->create();
        $aVenir = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-25',
            'frequence' => 'hebdomadaire',
        ]);
        $enRetard = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-10',
            'frequence' => 'hebdomadaire',
        ]);
        $sample = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-12 09:00:00',
        ]);
        $effectue = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-12',
            'frequence' => 'hebdomadaire',
            'water_quality_sample_id' => $sample->id,
        ]);

        $this->assertSame('a_venir', $aVenir->statut);
        $this->assertSame('en_retard', $enRetard->statut);
        $this->assertSame('effectue', $effectue->statut);

        $gestionnaire = $this->gestionnaire();
        $this->assign($gestionnaire, $zone);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index', ['statut' => 'en_retard']))
            ->assertOk()
            ->assertViewHas('planifications', fn ($planifications) => $planifications->getCollection()->pluck('id')->all() === [$enRetard->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index', ['statut' => 'effectue']))
            ->assertOk()
            ->assertViewHas('planifications', fn ($planifications) => $planifications->getCollection()->pluck('id')->all() === [$effectue->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index', ['statut' => 'a_venir']))
            ->assertOk()
            ->assertViewHas('planifications', fn ($planifications) => $planifications->getCollection()->pluck('id')->all() === [$aVenir->id]);
    }

    public function test_frequency_computes_the_next_date_without_month_end_overflow(): void
    {
        $zone = Zone::factory()->create();

        $hebdomadaire = new QualityPlanification([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-19',
            'frequence' => 'hebdomadaire',
        ]);
        $mensuelle = new QualityPlanification([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-01-31',
            'frequence' => 'mensuelle',
        ]);
        $trimestrielle = new QualityPlanification([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-11-30',
            'frequence' => 'trimestrielle',
        ]);

        $this->assertSame('2026-10-26', $hebdomadaire->prochaineDate()->format('Y-m-d'));
        $this->assertSame('2026-02-28', $mensuelle->prochaineDate()->format('Y-m-d'));
        $this->assertSame('2027-02-28', $trimestrielle->prochaineDate()->format('Y-m-d'));
    }

    public function test_registering_a_sample_marks_the_control_done_and_creates_the_next_occurrence(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Récurrente']);
        $this->assign($gestionnaire, $zone);

        $controle = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-19',
            'frequence' => 'hebdomadaire',
            'notes' => 'Contrôle récurrent.',
        ]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.create', ['planification' => $controle->id]))
            ->assertOk()
            ->assertSee('Prélèvement du contrôle planifié')
            ->assertSee('Zone Récurrente');

        $this->actingAs($gestionnaire)
            ->post(route('back.quality-samples.store'), [
                'planification_id' => $controle->id,
                'zone_id' => $zone->id,
                'date_prelevement' => '2026-10-19 09:00:00',
                'parametres' => [
                    'ph' => ['valeur' => 7.2],
                ],
            ])
            ->assertRedirect();

        $sample = WaterQualitySample::firstOrFail();
        $controle->refresh();

        $this->assertSame($sample->id, $controle->water_quality_sample_id);
        $this->assertSame('effectue', $controle->statut);

        $suivant = QualityPlanification::where('parent_id', $controle->id)->firstOrFail();

        $this->assertSame($zone->id, $suivant->zone_id);
        $this->assertSame('hebdomadaire', $suivant->frequence);
        $this->assertSame('2026-10-26', $suivant->date_prevue->format('Y-m-d'));
        $this->assertNull($suivant->water_quality_sample_id);
        $this->assertSame('a_venir', $suivant->statut);
        $this->assertSame(2, QualityPlanification::count());
    }

    public function test_sample_zone_must_match_the_planned_control_zone(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zoneControle = Zone::factory()->create();
        $autreZone = Zone::factory()->create();
        $this->assign($gestionnaire, $zoneControle, $autreZone);

        $controle = QualityPlanification::create([
            'zone_id' => $zoneControle->id,
            'date_prevue' => '2026-10-19',
            'frequence' => 'mensuelle',
        ]);

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.create', ['planification' => $controle->id]))
            ->post(route('back.quality-samples.store'), [
                'planification_id' => $controle->id,
                'zone_id' => $autreZone->id,
                'date_prelevement' => '2026-10-19 09:00:00',
                'parametres' => [
                    'ph' => ['valeur' => 7.2],
                ],
            ])
            ->assertRedirect(route('back.quality-samples.create', ['planification' => $controle->id]))
            ->assertSessionHasErrors('zone_id');

        $this->assertDatabaseCount('water_quality_samples', 0);
        $this->assertNull($controle->fresh()->water_quality_sample_id);
    }

    public function test_control_is_never_done_without_a_linked_sample(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $this->assign($gestionnaire, $zone);

        $controle = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-01',
            'frequence' => 'mensuelle',
        ]);

        $this->assertNull($controle->water_quality_sample_id);
        $this->assertSame('en_retard', $controle->statut);
        $this->assertSame(0, WaterQualitySample::count());

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index', ['statut' => 'effectue']))
            ->assertOk()
            ->assertViewHas('planifications', fn ($planifications) => $planifications->total() === 0);
    }

    public function test_calendar_filters_by_month_zone_and_status(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');

        $gestionnaire = $this->gestionnaire();
        $zoneAlpha = Zone::factory()->create(['nom' => 'Zone Alpha']);
        $zoneBeta = Zone::factory()->create(['nom' => 'Zone Beta']);
        $zoneGamma = Zone::factory()->create(['nom' => 'Zone Gamma']);
        $this->assign($gestionnaire, $zoneAlpha, $zoneBeta, $zoneGamma);

        QualityPlanification::create([
            'zone_id' => $zoneAlpha->id,
            'date_prevue' => '2026-10-20',
            'frequence' => 'mensuelle',
        ]);
        QualityPlanification::create([
            'zone_id' => $zoneBeta->id,
            'date_prevue' => '2026-10-05',
            'frequence' => 'mensuelle',
        ]);
        QualityPlanification::create([
            'zone_id' => $zoneGamma->id,
            'date_prevue' => '2026-11-15',
            'frequence' => 'mensuelle',
        ]);

        $zoneIds = fn ($controlsByDay) => $controlsByDay->flatten(1)->pluck('zone_id')->unique()->sort()->values()->all();

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Octobre 2026')
            ->assertViewHas('controlsByDay', fn ($controlsByDay) => $zoneIds($controlsByDay) === [$zoneAlpha->id, $zoneBeta->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.calendar', ['month' => '2026-10', 'statut' => 'en_retard']))
            ->assertOk()
            ->assertViewHas('controlsByDay', fn ($controlsByDay) => $zoneIds($controlsByDay) === [$zoneBeta->id]);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.calendar', ['month' => '2026-10', 'zone_id' => $zoneAlpha->id]))
            ->assertOk()
            ->assertViewHas('controlsByDay', fn ($controlsByDay) => $zoneIds($controlsByDay) === [$zoneAlpha->id]);
    }

    public function test_reminders_display_upcoming_controls_and_late_count(): void
    {
        Carbon::setTestNow('2026-10-19 10:00:00');

        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Rappels']);
        $this->assign($gestionnaire, $zone);

        QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-22',
            'frequence' => 'hebdomadaire',
        ]);
        QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-25',
            'frequence' => 'hebdomadaire',
        ]);
        QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-01',
            'frequence' => 'hebdomadaire',
        ]);

        $sample = WaterQualitySample::factory()->conforme()->create([
            'zone_id' => $zone->id,
            'date_prelevement' => '2026-10-02 09:00:00',
        ]);
        QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-02',
            'frequence' => 'hebdomadaire',
            'water_quality_sample_id' => $sample->id,
        ]);

        $expected = fn (array $reminders) => $reminders['a_venir'] === 2
            && $reminders['en_retard'] === 1
            && $reminders['effectues'] === 1
            && $reminders['prochains']->count() === 2;

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-planifications.index'))
            ->assertOk()
            ->assertViewHas('reminders', $expected);

        $this->actingAs($gestionnaire)
            ->get(route('back.quality-samples.index'))
            ->assertOk()
            ->assertViewHas('planningReminders', $expected);
    }

    public function test_admin_can_update_a_gestionnaire_zone_scope(): void
    {
        $admin = $this->admin();
        $gestionnaire = $this->gestionnaire();
        $zoneA = Zone::factory()->create();
        $zoneB = Zone::factory()->create();

        $this->actingAs($admin)
            ->get(route('back.quality-assignments.index'))
            ->assertOk()
            ->assertSee($gestionnaire->name);

        $this->actingAs($admin)
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $gestionnaire->id,
                'zone_ids' => [$zoneA->id, $zoneB->id],
            ])
            ->assertRedirect(route('back.quality-assignments.index'));

        $this->assertDatabaseHas('quality_zone_assignments', [
            'user_id' => $gestionnaire->id,
            'zone_id' => $zoneA->id,
        ]);
        $this->assertDatabaseHas('quality_zone_assignments', [
            'user_id' => $gestionnaire->id,
            'zone_id' => $zoneB->id,
        ]);

        $this->actingAs($admin)
            ->put(route('back.quality-assignments.update'), [
                'gestionnaire_id' => $gestionnaire->id,
                'zone_ids' => [$zoneB->id],
            ])
            ->assertRedirect(route('back.quality-assignments.index'));

        $this->assertDatabaseMissing('quality_zone_assignments', [
            'user_id' => $gestionnaire->id,
            'zone_id' => $zoneA->id,
        ]);
        $this->assertDatabaseHas('quality_zone_assignments', [
            'user_id' => $gestionnaire->id,
            'zone_id' => $zoneB->id,
        ]);
    }

    public function test_completed_control_is_preserved_in_history(): void
    {
        $admin = $this->admin();
        $zone = Zone::factory()->create();
        $sample = WaterQualitySample::factory()->conforme()->create(['zone_id' => $zone->id]);
        $controle = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-12',
            'frequence' => 'mensuelle',
            'water_quality_sample_id' => $sample->id,
        ]);

        $this->actingAs($admin)
            ->from(route('back.quality-planifications.index'))
            ->put(route('back.quality-planifications.update', $controle), [
                'zone_id' => $zone->id,
                'date_prevue' => '2026-12-12',
                'frequence' => 'mensuelle',
            ])
            ->assertRedirect(route('back.quality-planifications.index'))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->from(route('back.quality-planifications.index'))
            ->delete(route('back.quality-planifications.destroy', $controle))
            ->assertRedirect(route('back.quality-planifications.index'))
            ->assertSessionHas('error');

        $controle->refresh();
        $this->assertSame('2026-10-12', $controle->date_prevue->format('Y-m-d'));
        $this->assertDatabaseHas('quality_planifications', ['id' => $controle->id]);
    }

    public function test_linked_sample_cannot_be_deleted_and_its_zone_cannot_change(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $autreZone = Zone::factory()->create();
        $this->assign($gestionnaire, $zone, $autreZone);

        $sample = WaterQualitySample::factory()->conforme()->create(['zone_id' => $zone->id]);
        $controle = QualityPlanification::create([
            'zone_id' => $zone->id,
            'date_prevue' => '2026-10-12',
            'frequence' => 'mensuelle',
            'water_quality_sample_id' => $sample->id,
        ]);

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.show', $sample))
            ->delete(route('back.quality-samples.destroy', $sample))
            ->assertRedirect(route('back.quality-samples.show', $sample))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('water_quality_samples', ['id' => $sample->id]);

        $this->actingAs($gestionnaire)
            ->from(route('back.quality-samples.edit', $sample))
            ->put(route('back.quality-samples.update', $sample), [
                'zone_id' => $autreZone->id,
                'date_prelevement' => $sample->date_prelevement->format('Y-m-d H:i:s'),
                'parametres' => [
                    'ph' => ['valeur' => 7.2],
                ],
            ])
            ->assertRedirect(route('back.quality-samples.edit', $sample))
            ->assertSessionHasErrors('zone_id');

        $this->assertSame($zone->id, $sample->fresh()->zone_id);
        $this->assertSame($sample->id, $controle->fresh()->water_quality_sample_id);
    }
}
