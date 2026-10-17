<?php

namespace Tests\Feature\Back;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionnaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('back.gestionnaires.index'))->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_gestionnaires(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->get(route('back.gestionnaires.index'))
            ->assertForbidden();
    }

    public function test_gestionnaire_cannot_access_gestionnaires(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)
            ->get(route('back.gestionnaires.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_gestionnaires(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->gestionnaire()->create(['name' => 'Jean Gestion']);

        $this->actingAs($admin)
            ->get(route('back.gestionnaires.index'))
            ->assertOk()
            ->assertSee('Jean Gestion');
    }

    public function test_admin_can_create_gestionnaire(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('back.gestionnaires.store'), [
            'name' => 'Marie Nouvelle',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('back.gestionnaires.index'));

        $created = User::where('email', 'marie@example.com')->firstOrFail();
        $this->assertSame(UserRole::Gestionnaire, $created->role);
        $this->assertTrue($created->is_active);
    }

    public function test_role_cannot_be_escalated_through_creation_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('back.gestionnaires.store'), [
            'name' => 'Faux Admin',
            'email' => 'fake@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value, // tentative d'élévation
        ]);

        $created = User::where('email', 'fake@example.com')->firstOrFail();
        $this->assertSame(UserRole::Gestionnaire, $created->role);
    }

    public function test_creation_requires_valid_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('back.gestionnaires.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_admin_can_update_gestionnaire(): void
    {
        $admin = User::factory()->admin()->create();
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($admin)->put(route('back.gestionnaires.update', $gestionnaire), [
            'name' => 'Nom Modifié',
            'email' => $gestionnaire->email,
            'is_active' => '1',
        ])->assertRedirect(route('back.gestionnaires.index'));

        $this->assertSame('Nom Modifié', $gestionnaire->fresh()->name);
    }

    public function test_admin_can_toggle_gestionnaire_activation(): void
    {
        $admin = User::factory()->admin()->create();
        $gestionnaire = User::factory()->gestionnaire()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('back.gestionnaires.toggle', $gestionnaire))
            ->assertRedirect();

        $this->assertFalse($gestionnaire->fresh()->is_active);
    }

    public function test_editing_a_non_gestionnaire_returns_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($admin)
            ->get(route('back.gestionnaires.edit', $citizen))
            ->assertNotFound();
    }
}
