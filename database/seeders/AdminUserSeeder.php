<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crée le tout premier compte administrateur.
 *
 * Sécurité :
 * - aucun identifiant ni mot de passe n'est codé en dur ; ils proviennent de
 *   la configuration (`config('aquasecure.admin')`, alimentée par des variables
 *   d'environnement) ;
 * - si les identifiants ne sont pas configurés, le seeder s'arrête sans erreur
 *   et ne crée aucun compte ;
 * - idempotent : si un utilisateur avec cet email existe déjà, rien n'est créé
 *   ni écrasé.
 *
 * Ce seeder est appelé par DatabaseSeeder ; il peut aussi être déclenché seul
 * (php artisan db:seed --class=AdminUserSeeder).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('aquasecure.admin.email');
        $password = config('aquasecure.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'AdminUserSeeder ignoré : AQUASECURE_ADMIN_EMAIL et AQUASECURE_ADMIN_PASSWORD '
                .'doivent être configurés. Aucun compte administrateur créé.'
            );

            return;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->command?->info("AdminUserSeeder : un compte existe déjà pour {$email}, aucune action.");

            return;
        }

        User::create([
            'name' => config('aquasecure.admin.name', 'Administrateur'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("AdminUserSeeder : compte administrateur créé pour {$email}.");
    }
}
