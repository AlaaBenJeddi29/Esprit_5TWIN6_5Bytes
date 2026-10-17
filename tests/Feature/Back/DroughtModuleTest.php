<?php

namespace Tests\Feature\Back;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Infrastructure;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use App\Services\DroughtAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroughtModuleTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_citizen_cannot_access_drought_back_office(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)->get(route('back.water-levels.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.consumption-readings.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.restrictions.index'))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.scheduled-cuts.index'))->assertForbidden();
    }

    public function test_gestionnaire_can_view_drought_lists_and_forecast(): void
    {
        $gestionnaire = $this->gestionnaire();
        Zone::factory()->create();

        $this->actingAs($gestionnaire)->get(route('back.water-levels.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.consumption-readings.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.consumption-readings.forecast'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.restrictions.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.scheduled-cuts.index'))->assertOk();
        $this->actingAs($gestionnaire)->get(route('back.alert-subscriptions.index'))->assertOk();
    }

    public function test_gestionnaire_can_create_a_water_level(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('back.water-levels.store'), [
                'nom_source' => 'Barrage Test',
                'zone_id' => $zone->id,
                'type_source' => 'barrage',
                'niveau' => 18,
                'seuil_alerte' => 30,
                'seuil_critique' => 15,
                'date_mesure' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('back.water-levels.index'));

        $this->assertDatabaseHas('water_levels', [
            'nom_source' => 'Barrage Test',
            'niveau' => 18,
            'unite' => '%',
        ]);
    }

    public function test_water_level_validation_rejects_incoherent_thresholds(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->from(route('back.water-levels.create'))
            ->post(route('back.water-levels.store'), [
                'nom_source' => '',
                'zone_id' => $zone->id,
                'type_source' => 'inconnu',
                'niveau' => 150,
                'seuil_alerte' => 20,
                'seuil_critique' => 30,
                'date_mesure' => '',
            ])
            ->assertSessionHasErrors(['nom_source', 'type_source', 'niveau', 'seuil_critique', 'date_mesure']);

        $this->assertDatabaseCount('water_levels', 0);
    }

    public function test_consumption_reading_is_unique_per_zone_and_period(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $periode = now()->startOfMonth()->format('Y-m-d');

        $payload = [
            'zone_id' => $zone->id,
            'periode' => $periode,
            'consommation_m3' => 12000,
            'nombre_abonnes' => 4200,
        ];

        $this->actingAs($gestionnaire)
            ->post(route('back.consumption-readings.store'), $payload)
            ->assertRedirect(route('back.consumption-readings.index'));

        $this->actingAs($gestionnaire)
            ->from(route('back.consumption-readings.create'))
            ->post(route('back.consumption-readings.store'), $payload)
            ->assertSessionHasErrors(['periode']);

        $this->assertDatabaseCount('consumption_readings', 1);
    }

    public function test_gestionnaire_can_create_and_update_a_restriction(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();

        $this->actingAs($gestionnaire)
            ->post(route('back.restrictions.store'), [
                'zone_id' => $zone->id,
                'type' => 'alerte',
                'titre' => 'Limitation estivale',
                'description' => 'Niveau bas.',
                'usages_interdits' => 'Arrosage',
                'date_debut' => now()->format('Y-m-d H:i:s'),
                'date_fin' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'statut' => 'active',
            ])
            ->assertRedirect(route('back.restrictions.index'));

        $restriction = Restriction::firstOrFail();

        $this->actingAs($gestionnaire)
            ->put(route('back.restrictions.update', $restriction), [
                'zone_id' => $zone->id,
                'type' => 'alerte_renforcee',
                'titre' => 'Limitation renforcée',
                'date_debut' => now()->format('Y-m-d H:i:s'),
                'date_fin' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'statut' => 'active',
            ])
            ->assertRedirect(route('back.restrictions.index'));

        $this->assertSame('alerte_renforcee', $restriction->fresh()->type);
    }

    public function test_restriction_validation_rejects_invalid_dates_and_type(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.restrictions.create'))
            ->post(route('back.restrictions.store'), [
                'type' => 'inconnu',
                'titre' => '',
                'date_debut' => now()->format('Y-m-d'),
                'date_fin' => now()->subDay()->format('Y-m-d'),
                'statut' => 'inconnu',
            ])
            ->assertSessionHasErrors(['type', 'titre', 'date_fin', 'statut']);
    }

    public function test_gestionnaire_can_create_a_scheduled_cut(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($gestionnaire)
            ->post(route('back.scheduled-cuts.store'), [
                'zone_id' => $zone->id,
                'infrastructure_id' => $infrastructure->id,
                'motif' => 'Remplacement de vanne',
                'date_debut' => now()->addDay()->format('Y-m-d H:i:s'),
                'date_fin' => now()->addDay()->addHours(6)->format('Y-m-d H:i:s'),
                'statut' => 'planifiee',
            ])
            ->assertRedirect(route('back.scheduled-cuts.index'));

        $this->assertDatabaseHas('scheduled_cuts', [
            'zone_id' => $zone->id,
            'motif' => 'Remplacement de vanne',
        ]);
    }

    public function test_scheduled_cut_validation_rejects_end_before_start(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)
            ->from(route('back.scheduled-cuts.create'))
            ->post(route('back.scheduled-cuts.store'), [
                'motif' => '',
                'date_debut' => now()->addDay()->format('Y-m-d H:i:s'),
                'date_fin' => now()->format('Y-m-d H:i:s'),
                'statut' => 'planifiee',
            ])
            ->assertSessionHasErrors(['motif', 'date_fin']);
    }

    public function test_forecast_and_risk_score_are_computed_locally(): void
    {
        $zone = Zone::factory()->create();
        $analyzer = app(DroughtAnalyzer::class);

        foreach ([12000, 12600, 13100, 13800] as $index => $value) {
            ConsumptionReading::factory()->create([
                'zone_id' => $zone->id,
                'periode' => now()->startOfMonth()->subMonths(3 - $index)->format('Y-m-d'),
                'consommation_m3' => $value,
            ]);
        }

        WaterLevel::factory()->critique()->create(['zone_id' => $zone->id, 'niveau' => 10]);
        Restriction::factory()->create(['zone_id' => $zone->id, 'statut' => 'active']);

        $forecast = $analyzer->forecast($zone);

        $this->assertCount(4, $forecast['history']);
        $this->assertCount(3, $forecast['forecast']);
        $this->assertSame('hausse', $forecast['tendance']);

        $risk = $analyzer->riskScore($zone);

        $this->assertGreaterThanOrEqual(50, $risk['score']);
        $this->assertContains($risk['niveau'], ['eleve', 'critique']);
        $this->assertNotEmpty($risk['facteurs']);
    }

    public function test_public_drought_pages_are_available(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Zone Sécheresse']);
        WaterLevel::factory()->create(['zone_id' => $zone->id]);
        Restriction::factory()->create(['zone_id' => $zone->id, 'statut' => 'active', 'titre' => 'Restriction publique']);
        ScheduledCut::factory()->create(['zone_id' => $zone->id]);

        $this->get(route('public.drought.index'))->assertOk()->assertSee('Zone Sécheresse');
        $this->get(route('public.drought.zone', $zone))->assertOk()->assertSee('Restriction publique');
    }
}
