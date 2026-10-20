<?php

namespace Tests\Feature\Back;

use App\Models\Infrastructure\Zone;
use App\Models\Project\Contractor;
use App\Models\Project\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackOfficeEditPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_edit_page_is_available_to_back_office_staff(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Zone Édition']);

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->get(route('back.zones.edit', $zone))
            ->assertOk()
            ->assertSee('Zone Édition')
            ->assertSee(route('back.zones.update', $zone), false);
    }

    public function test_contractor_edit_page_is_available_to_back_office_staff(): void
    {
        $contractor = Contractor::factory()->create(['nom' => 'Entreprise Édition']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('back.contractors.edit', $contractor))
            ->assertOk()
            ->assertSee('Entreprise Édition')
            ->assertSee(route('back.contractors.update', $contractor), false);
    }

    public function test_project_edit_page_is_available_to_back_office_staff(): void
    {
        $project = Project::factory()->create(['titre' => 'Projet Édition']);

        $this->actingAs(User::factory()->gestionnaire()->create())
            ->get(route('back.projects.edit', $project))
            ->assertOk()
            ->assertSee('Projet Édition')
            ->assertSee(route('back.projects.update', $project), false);
    }

    public function test_citizen_cannot_reach_back_office_edit_pages(): void
    {
        $citizen = User::factory()->citoyen()->create();
        $zone = Zone::factory()->create();
        $contractor = Contractor::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($citizen)->get(route('back.zones.edit', $zone))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.contractors.edit', $contractor))->assertForbidden();
        $this->actingAs($citizen)->get(route('back.projects.edit', $project))->assertForbidden();
    }
}
