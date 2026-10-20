<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this->from('/register')->post('/register', []);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name' => 'Le champ nom est obligatoire.',
            'email' => 'Le champ adresse e-mail est obligatoire.',
            'password' => 'Le champ mot de passe est obligatoire.',
        ]);
    }

    public function test_registration_requires_a_valid_email_and_matching_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'adresse-invalide',
            'password' => 'motdepasse',
            'password_confirmation' => 'autre-mot-de-passe',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'email' => 'Le champ adresse e-mail doit être une adresse e-mail valide.',
            'password' => 'La confirmation du champ mot de passe ne correspond pas.',
        ]);
    }

    public function test_password_must_have_at_least_eight_characters(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'court',
            'password_confirmation' => 'court',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'Le texte mot de passe doit contenir au moins 8 caractères.',
        ]);
    }

    public function test_registration_hashes_the_password(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_registration_is_rate_limited(): void
    {
        $payload = [
            'name' => '',
            'email' => 'adresse-invalide',
            'password' => 'court',
            'password_confirmation' => 'court',
        ];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/register', $payload)->assertRedirect();
        }

        $this->post('/register', $payload)->assertStatus(429);
    }
}
