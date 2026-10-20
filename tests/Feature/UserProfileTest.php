<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function png(string $name = 'avatar.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode(self::PNG_1X1, true),
        );
    }

    public function test_guest_cannot_access_profile_or_photo_routes(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));

        $this->patch('/profile', ['name' => 'Intrus', 'email' => 'intrus@example.com'])
            ->assertRedirect(route('login'));

        $this->post('/profile/photo', ['photo' => $this->png()])
            ->assertRedirect(route('login'));

        $this->delete('/profile/photo')->assertRedirect(route('login'));
        $this->delete('/profile', ['password' => 'password'])->assertRedirect(route('login'));
    }

    public function test_citizen_profile_uses_front_office_layout(): void
    {
        $user = User::factory()->citoyen()->create(['name' => 'Zelda Citoyenne']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk()
            ->assertSee(asset('front/images/logo/favicon.svg'))
            ->assertDontSee(asset('back/favicon.png'))
            ->assertSee('Z</span>', false)
            ->assertSee('Photo de profil')
            ->assertSee('Changer le mot de passe');
    }

    public function test_staff_profile_uses_back_office_layout(): void
    {
        foreach ([UserRole::Gestionnaire, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/profile')
                ->assertOk()
                ->assertSee(asset('back/favicon.png'))
                ->assertDontSee(asset('front/images/logo/favicon.svg'))
                ->assertSee('Mon profil');
        }
    }

    public function test_profile_update_requires_valid_name_and_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'deja@example.com']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => '', 'email' => 'pas-un-email'])
            ->assertSessionHasErrors(['name', 'email']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $other->email])
            ->assertSessionHasErrors('email');

        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_profile_update_cannot_change_role_or_account_status(): void
    {
        $user = User::factory()->citoyen()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nom Modifié',
                'email' => $user->email,
                'role' => UserRole::Admin->value,
                'is_active' => false,
                'avatar_path' => 'avatars/pirate.png',
                'id' => 999,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Nom Modifié', $user->name);
        $this->assertSame(UserRole::Citoyen, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->avatar_path);
    }

    public function test_changing_email_requires_a_new_verification(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'nouvelle-adresse@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Vos informations ont été mises à jour. Votre nouvelle adresse e-mail doit être vérifiée.')
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('nouvelle-adresse@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_unverified_user_sees_a_resend_verification_action_on_profile(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee("Votre adresse e-mail n'est pas encore vérifiée", false)
            ->assertSee(route('verification.send'));
    }

    public function test_keeping_the_same_email_preserves_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nouveau Nom',
                'email' => $user->email,
            ])
            ->assertSessionHas('success', 'Vos informations ont été mises à jour.');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_valid_png_photo_is_stored_with_a_generated_name_and_displayed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => $this->png()])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Votre photo de profil a été mise à jour.')
            ->assertRedirect('/profile');

        $path = $user->refresh()->avatar_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertNotSame('avatar.png', basename($path));
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path));
    }

    public function test_non_image_content_is_rejected_and_nothing_is_stored(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent('fake.png', '<?php echo "pwned"; ?>');

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => $file])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photo_larger_than_two_megabytes_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->createWithContent(
            'grande.png',
            base64_decode(self::PNG_1X1, true).str_repeat("\0", 2 * 1024 * 1024),
        );

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => $file])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_replacing_photo_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', ['photo' => $this->png()]);
        $previousPath = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($previousPath);

        $this->actingAs($user)->post('/profile/photo', ['photo' => $this->png('autre.png')]);
        $newPath = $user->refresh()->avatar_path;

        $this->assertNotSame($previousPath, $newPath);
        Storage::disk('public')->assertMissing($previousPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_photo_can_be_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', ['photo' => $this->png()]);
        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)
            ->delete('/profile/photo')
            ->assertSessionHas('success', 'Votre photo de profil a été supprimée.')
            ->assertRedirect('/profile');

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_photo_actions_only_affect_the_authenticated_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $other = User::factory()->create();

        Storage::disk('public')->put('avatars/other.png', 'photo existante');
        $other->forceFill(['avatar_path' => 'avatars/other.png'])->save();

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => $this->png(), 'user_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->avatar_path);
        $this->assertSame('avatars/other.png', $other->refresh()->avatar_path);
        Storage::disk('public')->assertExists('avatars/other.png');

        $this->actingAs($user)
            ->delete('/profile/photo', ['user_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_path);
        $this->assertSame('avatars/other.png', $other->refresh()->avatar_path);
        Storage::disk('public')->assertExists('avatars/other.png');
    }

    public function test_account_deletion_requires_the_current_password(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('avatars/user.png', 'photo');
        $user->forceFill(['avatar_path' => 'avatars/user.png'])->save();

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        Storage::disk('public')->assertExists('avatars/user.png');
    }

    public function test_account_deletion_logs_out_cleans_photo_and_preserves_reports(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $report = Report::factory()->create(['user_id' => $user->id]);

        Storage::disk('public')->put('avatars/user.png', 'photo');
        $user->forceFill(['avatar_path' => 'avatars/user.png'])->save();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Votre compte a bien été supprimé.')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        Storage::disk('public')->assertMissing('avatars/user.png');

        $this->assertNotNull($report->fresh());
        $this->assertNull($report->fresh()->user_id);
    }

    public function test_deletion_cannot_target_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password', 'user_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh());
        $this->assertNotNull($other->fresh());
    }

    public function test_account_deletion_is_not_reachable_over_get(): void
    {
        $route = Route::getRoutes()->getByName('profile.destroy');

        $this->assertNotNull($route);
        $this->assertContains('DELETE', $route->methods());
        $this->assertNotContains('GET', $route->methods());

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile?password=password')
            ->assertOk();

        $this->assertNotNull($user->fresh());
    }
}
