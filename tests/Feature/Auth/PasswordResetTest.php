<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            fn (ResetPassword $notification, array $channels) => in_array('mail', $channels, true)
        );
    }

    public function test_unknown_email_receives_the_same_neutral_response(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'inconnu@example.com']);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NouveauMotDePasse!42',
                'password_confirmation' => 'NouveauMotDePasse!42',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', __('passwords.reset'));

            $this->assertTrue(Hash::check('NouveauMotDePasse!42', $user->fresh()->password));
            $this->assertGuest();

            return true;
        });
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $payload = [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NouveauMotDePasse!42',
                'password_confirmation' => 'NouveauMotDePasse!42',
            ];

            $this->post('/reset-password', $payload)->assertRedirect(route('login'));

            $response = $this->post('/reset-password', $payload);

            $response->assertSessionHasErrors(['email' => __('passwords.token')]);
            $this->assertTrue(Hash::check('NouveauMotDePasse!42', $user->fresh()->password));

            return true;
        });
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(61),
        ]);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NouveauMotDePasse!42',
            'password_confirmation' => 'NouveauMotDePasse!42',
        ]);

        $response->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/reset-password', [
            'token' => Str::random(64),
            'email' => $user->email,
            'password' => 'NouveauMotDePasse!42',
            'password_confirmation' => 'NouveauMotDePasse!42',
        ]);

        $response->assertSessionHasErrors(['email' => __('passwords.token')]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_token_is_bound_to_the_requested_email_address(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $otherUser) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $otherUser->email,
                'password' => 'NouveauMotDePasse!42',
                'password_confirmation' => 'NouveauMotDePasse!42',
            ]);

            $response->assertSessionHasErrors(['email' => __('passwords.token')]);
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
            $this->assertTrue(Hash::check('password', $otherUser->fresh()->password));

            return true;
        });
    }

    public function test_password_reset_requires_confirmation_and_a_strong_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'court',
                'password_confirmation' => 'différent',
            ]);

            $response->assertSessionHasErrors('password');
            $this->assertTrue(Hash::check('password', $user->fresh()->password));

            return true;
        });
    }

    public function test_reset_email_is_in_french_and_uses_the_configured_application_url(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertStringContainsString('Réinitialisation de votre mot de passe', $mail->subject);
            $this->assertStringStartsWith(config('app.url'), $mail->actionUrl);
            $this->assertStringContainsString('/reset-password/'.$notification->token, $mail->actionUrl);
            $this->assertStringContainsString(urlencode($user->email), $mail->actionUrl);
            $this->assertSame('Réinitialiser mon mot de passe', $mail->actionText);

            return true;
        });
    }
}
