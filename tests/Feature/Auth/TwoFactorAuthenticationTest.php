<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function enableTwoFactor(User $user): array
    {
        $secret = (new Google2FA)->generateSecretKey();
        $codes = app(TwoFactorAuthenticationService::class)->enable($user, $secret);

        return [$secret, $codes];
    }

    private function currentCode(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    private function pendingSetupSecret(): string
    {
        return Crypt::decryptString(session('two-factor.setup.secret'));
    }

    /**
     * @return list<string>
     */
    private function flashedRecoveryCodes(): array
    {
        return json_decode(
            Crypt::decryptString(session('two-factor.recovery-codes-encrypted')),
            true,
        );
    }

    private function invalidCode(string $secret): string
    {
        $google2fa = new Google2FA;

        for ($i = 0; $i < 20; $i++) {
            $candidate = str_pad((string) $i, 6, '0', STR_PAD_LEFT);

            if (! $google2fa->verifyKey($secret, $candidate, 1)) {
                return $candidate;
            }
        }

        $this->fail('Unable to generate an invalid TOTP code.');
    }

    private function login(User $user): TestResponse
    {
        return $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_settings_page_is_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertSee('Authentification à deux facteurs')
            ->assertSee('Désactivée');
    }

    public function test_guest_cannot_access_two_factor_settings_or_actions(): void
    {
        $this->get(route('two-factor.show'))->assertRedirect(route('login'));

        foreach ([
            route('two-factor.setup'),
            route('two-factor.confirm'),
            route('two-factor.disable'),
            route('two-factor.recovery-codes'),
        ] as $uri) {
            $this->post($uri)->assertRedirect(route('login'));
        }
    }

    public function test_setup_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.setup'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertNull(session('two-factor.setup.secret'));
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_setup_initiation_renders_a_qr_code_without_exposing_the_secret(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.setup'), ['current_password' => 'password'])
            ->assertRedirect(route('two-factor.setup.show'));

        $secret = $this->pendingSetupSecret();

        $this->assertNotSame('', $secret);
        $this->assertNotSame($secret, session('two-factor.setup.secret'));
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)
            ->get(route('two-factor.setup.show'))
            ->assertOk()
            ->assertSee('Scanner le QR code')
            ->assertSee('<svg', false)
            ->assertDontSee($secret);
    }

    public function test_qr_payload_is_a_standard_totp_uri_without_exposing_the_secret_on_the_page(): void
    {
        $user = User::factory()->create();
        $secret = (new Google2FA)->generateSecretKey();
        $service = app(TwoFactorAuthenticationService::class);

        $url = $service->otpauthUrl($user, $secret);

        $this->assertStringStartsWith('otpauth://totp/', $url);
        $this->assertStringContainsString(rawurlencode($user->email), $url);
        $this->assertStringContainsString('secret='.$secret, $url);
        $this->assertStringContainsString('digits=6', $url);
        $this->assertStringContainsString('period=30', $url);
        $this->assertStringContainsString('algorithm=SHA1', $url);

        $this->actingAs($user)->post(route('two-factor.setup'), ['current_password' => 'password']);
        $this->actingAs($user)
            ->get(route('two-factor.setup.show'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertDontSee($secret)
            ->assertDontSee('otpauth://', false);
    }

    public function test_valid_totp_confirms_activation_and_shows_recovery_codes_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.setup'), ['current_password' => 'password']);
        $secret = $this->pendingSetupSecret();

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => $this->currentCode($secret)])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('success')
            ->assertSessionHas('two-factor.recovery-codes-encrypted');

        $codes = $this->flashedRecoveryCodes();
        $this->assertStringNotContainsString($codes[0], (string) session('two-factor.recovery-codes-encrypted'));
        $user->refresh();

        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNull(session('two-factor.setup.secret'));

        $rawSecret = DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $rawCodes = DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');

        $this->assertNotSame($secret, $rawSecret);
        $this->assertStringNotContainsString($secret, (string) $rawSecret);
        $this->assertStringNotContainsString($codes[0], (string) $rawCodes);

        $this->actingAs($user)
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertSee('Activée')
            ->assertSee($codes[0]);
    }

    public function test_invalid_totp_is_rejected_and_does_not_enable_two_factor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.setup'), ['current_password' => 'password']);
        $secret = $this->pendingSetupSecret();

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => $this->invalidCode($secret)])
            ->assertSessionHasErrors('code');

        $user->refresh();

        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame($secret, $this->pendingSetupSecret());
    }

    public function test_confirm_requires_a_pending_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => '123456'])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('error');

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_login_for_two_factor_user_requires_a_valid_challenge(): void
    {
        $user = User::factory()->citoyen()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user)
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($user->id, session('two-factor.login.id'));

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertSee('Authentification à deux facteurs')
            ->assertSee($user->email)
            ->assertDontSee($secret);

        $this->post(route('two-factor.login.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect(route('front.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('two-factor.login.id'));
    }

    public function test_login_without_two_factor_still_authenticates_normally(): void
    {
        $user = User::factory()->citoyen()->create();

        $this->login($user)->assertRedirect(route('front.home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_totp_at_login_keeps_the_user_unauthenticated(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user);

        $this->post(route('two-factor.login.store'), [
            'code' => $this->invalidCode($secret),
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame($user->id, session('two-factor.login.id'));
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        [, $codes] = $this->enableTwoFactor($user);
        $recoveryCode = $codes[0];

        $this->login($user);
        $this->post(route('two-factor.login.store'), [
            'recovery_code' => $recoveryCode,
        ])->assertRedirect(route('front.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->login($user);
        $this->post(route('two-factor.login.store'), [
            'recovery_code' => $recoveryCode,
        ])->assertSessionHasErrors('recovery_code');

        $this->assertGuest();
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_invalid_recovery_code_is_rejected(): void
    {
        $user = User::factory()->create();
        [, $codes] = $this->enableTwoFactor($user);

        $this->login($user);

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'AAAAA-AAAAA',
        ])->assertSessionHasErrors('recovery_code');

        $this->assertGuest();
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotContains('AAAAA-AAAAA', $codes);
    }

    public function test_regenerating_recovery_codes_invalidates_the_previous_codes(): void
    {
        $user = User::factory()->create();
        [, $oldCodes] = $this->enableTwoFactor($user);

        $this->actingAs($user)
            ->post(route('two-factor.recovery-codes'), ['current_password' => 'password'])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('two-factor.recovery-codes-encrypted');

        $newCodes = $this->flashedRecoveryCodes();
        $this->assertNotSame($oldCodes, $newCodes);
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout');

        $this->login($user);
        $this->post(route('two-factor.login.store'), [
            'recovery_code' => $oldCodes[0],
        ])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => $newCodes[0],
        ])->assertRedirect(route('front.home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_disable_requires_the_current_password_and_clears_all_two_factor_data(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());

        $this->actingAs($user)
            ->post(route('two-factor.disable'), ['current_password' => 'password'])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);

        $this->post('/logout');
        $this->login($user)->assertRedirect(route('front.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($secret, $user->fresh()->two_factor_secret);
    }

    public function test_challenge_cannot_be_reached_without_a_pending_login(): void
    {
        $this->get(route('two-factor.login'))->assertRedirect(route('login'));
        $this->post(route('two-factor.login.store'), ['code' => '123456'])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_pending_challenge_expires_after_ten_minutes(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user)->assertRedirect(route('two-factor.login'));

        $this->travel(11)->minutes();

        $this->get(route('two-factor.login'))->assertRedirect(route('login'));
        $this->post(route('two-factor.login.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('two-factor.login.id'));
    }

    public function test_too_many_failed_challenges_clear_the_pending_session(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user);
        $invalid = $this->invalidCode($secret);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.login.store'), ['code' => $invalid])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.login.store'), ['code' => $invalid])
            ->assertSessionHasErrors('code');

        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('code'));
        $this->assertNull(session('two-factor.login.id'));

        $this->post(route('two-factor.login.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_pending_challenge_is_cancelled_when_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user);
        $user->forceFill(['is_active' => false])->save();

        $this->post(route('two-factor.login.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_role_redirect_is_preserved_after_two_factor_challenge(): void
    {
        $user = User::factory()->gestionnaire()->create();
        [$secret] = $this->enableTwoFactor($user);

        $this->login($user)->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect(route('back.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_secrets_and_recovery_codes_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactor($user);
        $user->refresh();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
        $this->assertStringNotContainsString($secret, $user->toJson());
    }
}
