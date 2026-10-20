<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_forces_citoyen_role_and_ignores_submitted_role(): void
    {
        $this->post('/register', [
            'name' => 'Nouveau Citoyen',
            'email' => 'citoyen@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value, // tentative d'élévation — doit être ignorée
        ]);

        $user = User::where('email', 'citoyen@example.com')->firstOrFail();

        $this->assertSame(UserRole::Citoyen, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_citizen_dashboard_dispatches_to_front_home(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->get('/dashboard')
            ->assertRedirect(route('front.home'));
    }

    public function test_gestionnaire_dashboard_dispatches_to_back_office(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)
            ->get('/dashboard')
            ->assertRedirect(route('back.dashboard'));
    }

    public function test_admin_dashboard_dispatches_to_back_office(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('back.dashboard'));
    }

    public function test_guest_is_redirected_to_login_from_back_office(): void
    {
        $this->get('/back/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_protected_routes(): void
    {
        foreach (['/dashboard', '/profile', '/mon-espace'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_citizen_cannot_access_back_office(): void
    {
        $citizen = User::factory()->citoyen()->create();

        $this->actingAs($citizen)
            ->get('/back/dashboard')
            ->assertForbidden();
    }

    public function test_gestionnaire_can_access_back_office(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)
            ->get('/back/dashboard')
            ->assertOk();
    }

    public function test_login_redirects_citizen_to_front_home(): void
    {
        $citizen = User::factory()->citoyen()->create(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $citizen->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($citizen);
        $response->assertRedirect(route('front.home'));
    }

    public function test_login_redirects_gestionnaire_to_back_office(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create(['password' => 'password']);

        $response = $this->post('/login', [
            'email' => $gestionnaire->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($gestionnaire);
        $response->assertRedirect(route('back.dashboard'));
    }

    public function test_intended_url_cannot_drop_citizen_into_back_office(): void
    {
        $citizen = User::factory()->citoyen()->create(['password' => 'password']);

        $response = $this
            ->withSession(['url.intended' => url('/back/dashboard')])
            ->post('/login', [
                'email' => $citizen->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($citizen);
        // L'URL mémorisée pointe vers le Back Office : non autorisée pour un citoyen.
        $response->assertRedirect(route('front.home'));
    }

    public function test_intended_url_allows_gestionnaire_into_back_office(): void
    {
        $gestionnaire = User::factory()->gestionnaire()->create(['password' => 'password']);

        $response = $this
            ->withSession(['url.intended' => url('/back/reports')])
            ->post('/login', [
                'email' => $gestionnaire->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($gestionnaire);
        $response->assertRedirect(url('/back/reports'));
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->gestionnaire()->create([
            'password' => 'password',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
