<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de DÉMONSTRATION pour le développement local.
 *
 * Sécurité :
 * - aucun mot de passe n'est codé en dur ; il provient de
 *   AQUASECURE_DEMO_PASSWORD via `config('aquasecure.demo')` ;
 * - si le mot de passe ou l'un des emails est absent, le seeder s'arrête
 *   proprement sans créer de compte ;
 * - refuse de s'exécuter en production ;
 * - idempotent : relancer le seeder actualise les comptes existants ;
 * - ne journalise jamais les mots de passe.
 *
 * Appelé par DatabaseSeeder (php artisan db:seed) ; exécutable aussi seul :
 * php artisan db:seed --class=DemoUsersSeeder.
 */
class DemoUsersSeeder extends Seeder
{
    /**
     * @var array<string, UserRole>
     */
    private const ROLES = [
        'admin' => UserRole::Admin,
        'gestionnaire' => UserRole::Gestionnaire,
        'citoyen' => UserRole::Citoyen,
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoUsersSeeder ignoré : comptes de démonstration interdits en production.');

            return;
        }

        $password = config('aquasecure.demo.password');

        if (blank($password)) {
            $this->command?->warn(
                'DemoUsersSeeder ignoré : AQUASECURE_DEMO_PASSWORD doit être configuré. '
                .'Aucun compte de démonstration créé.'
            );

            return;
        }

        $configured = [];

        foreach (self::ROLES as $key => $role) {
            $email = config("aquasecure.demo.users.{$key}.email");

            if (blank($email)) {
                $this->command?->warn(
                    "DemoUsersSeeder ignoré : l'email de démonstration « {$key} » n'est pas configuré."
                );

                return;
            }

            $configured[$key] = [
                'name' => config("aquasecure.demo.users.{$key}.name", $role->label()),
                'email' => $email,
                'role' => $role,
            ];
        }

        $rows = [];

        foreach ($configured as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    // Le cast `hashed` du modèle User hache automatiquement la valeur.
                    'password' => $password,
                    'role' => $user['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $rows[] = [$user['role']->label(), $user['email']];
        }

        $this->command?->info('Comptes de démonstration créés/actualisés :');
        $this->command?->table(['Rôle', 'Email'], $rows);
        $this->command?->warn('Identifiants de DÉVELOPPEMENT uniquement — mot de passe issu de la configuration.');
    }
}
