<?php

namespace Tests\Feature\Back;

use App\Models\Drought\ConsumptionReading;
use App\Models\Drought\Restriction;
use App\Models\Drought\ScheduledCut;
use App\Models\Drought\WaterLevel;
use App\Models\Infrastructure\Zone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroughtExportsTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(): User
    {
        return User::factory()->gestionnaire()->create();
    }

    public function test_guest_and_citizen_cannot_download_drought_exports(): void
    {
        $this->get(route('back.water-levels.export', ['format' => 'csv']))
            ->assertRedirect(route('login'));

        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->get(route('back.water-levels.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_gestionnaire_can_download_each_drought_export_as_excel_html(): void
    {
        $gestionnaire = $this->gestionnaire();
        $zone = Zone::factory()->create(['nom' => 'Zone Export']);

        WaterLevel::factory()->create([
            'zone_id' => $zone->id,
            'nom_source' => 'Barrage Export',
        ]);
        ConsumptionReading::factory()->create([
            'zone_id' => $zone->id,
            'periode' => now()->startOfMonth()->toDateString(),
            'consommation_m3' => 12500,
        ]);
        Restriction::factory()->create([
            'zone_id' => $zone->id,
            'titre' => 'Restriction Export',
        ]);
        ScheduledCut::factory()->create([
            'zone_id' => $zone->id,
            'motif' => 'Coupure Export',
        ]);

        $cases = [
            ['back.water-levels.export', 'drought-water-levels-', 'Barrage Export'],
            ['back.consumption-readings.export', 'drought-consumption-readings-', 'Zone Export'],
            ['back.restrictions.export', 'drought-restrictions-', 'Restriction Export'],
            ['back.scheduled-cuts.export', 'drought-scheduled-cuts-', 'Coupure Export'],
        ];

        foreach ($cases as [$routeName, $filenamePrefix, $expectedContent]) {
            $response = $this->actingAs($gestionnaire)->get(route($routeName, ['format' => 'csv']));

            $response->assertOk();
            $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
            $this->assertStringContainsString($filenamePrefix, (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString('.xls', (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString($expectedContent, $response->getContent());
            $this->assertStringContainsString('<table>', $response->getContent());
        }
    }

    public function test_excel_export_is_filtered_by_zone(): void
    {
        $gestionnaire = $this->gestionnaire();
        $selected = Zone::factory()->create();
        $other = Zone::factory()->create();

        WaterLevel::factory()->create(['zone_id' => $selected->id, 'nom_source' => 'Barrage retenu']);
        WaterLevel::factory()->create(['zone_id' => $other->id, 'nom_source' => 'Barrage exclu']);

        $response = $this->actingAs($gestionnaire)->get(route('back.water-levels.export', [
            'format' => 'excel',
            'zone_id' => $selected->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Barrage retenu', $response->getContent());
        $this->assertStringNotContainsString('Barrage exclu', $response->getContent());
    }

    public function test_excel_export_escapes_untrusted_values(): void
    {
        $gestionnaire = $this->gestionnaire();

        WaterLevel::factory()->create([
            'nom_source' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($gestionnaire)->get(route('back.water-levels.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
    }

    public function test_pdf_export_returns_a_pdf_document(): void
    {
        $gestionnaire = $this->gestionnaire();

        WaterLevel::factory()->create();

        $response = $this->actingAs($gestionnaire)->get(route('back.water-levels.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
