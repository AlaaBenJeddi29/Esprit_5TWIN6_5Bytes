<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->citoyen()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // Un citoyen est redirigé vers le Front Office (dispatch selon le rôle).
        $response->assertRedirect(route('front.home'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_invalid_credentials_display_a_french_error_message(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Ces identifiants ne correspondent à aucun compte.',
        ]);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Trop de tentatives de connexion',
            session('errors')->first('email')
        );
    }

    public function test_authenticated_users_are_redirected_away_from_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/register')->assertRedirect(route('dashboard'));
    }

    public function test_auth_forms_expose_csrf_tokens_and_logout_only_accepts_post(): void
    {
        $this->get('/login')->assertSee('name="_token"', false);
        $this->get('/register')->assertSee('name="_token"', false);

        $logout = Route::getRoutes()->getByName('logout');

        $this->assertNotNull($logout);
        $this->assertSame(['POST'], $logout->methods());

        $this->get('/logout')->assertStatus(405);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
