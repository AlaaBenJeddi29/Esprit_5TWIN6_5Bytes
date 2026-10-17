<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Models\Project\Funding;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function configureEmails(): void
    {
        config([
            'aquasecure.demo.users.admin.email' => 'demo.admin@example.test',
            'aquasecure.demo.users.gestionnaire.email' => 'demo.gestionnaire@example.test',
            'aquasecure.demo.users.citoyen.email' => 'demo.citoyen@example.test',
        ]);
    }

    public function test_demo_users_seeder_skips_when_password_is_not_configured(): void
    {
        config(['aquasecure.demo.password' => null]);
        $this->configureEmails();

        $this->seed(DemoUsersSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_users_seeder_skips_when_an_email_is_missing(): void
    {
        config([
            'aquasecure.demo.password' => 'demo-secret-from-config',
            'aquasecure.demo.users.admin.email' => null,
            'aquasecure.demo.users.gestionnaire.email' => null,
            'aquasecure.demo.users.citoyen.email' => null,
        ]);

        $this->seed(DemoUsersSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_users_seeder_creates_the_three_roles_from_config_and_is_idempotent(): void
    {
        config(['aquasecure.demo.password' => 'demo-secret-from-config']);
        $this->configureEmails();

        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->assertDatabaseCount('users', 3);

        $expected = [
            'demo.admin@example.test' => UserRole::Admin,
            'demo.gestionnaire@example.test' => UserRole::Gestionnaire,
            'demo.citoyen@example.test' => UserRole::Citoyen,
        ];

        foreach ($expected as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertSame($role, $user->role);
            $this->assertTrue($user->is_active);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check('demo-secret-from-config', $user->password));
        }
    }

    public function test_demo_data_seeder_skips_without_a_back_office_user(): void
    {
        config([
            'aquasecure.demo.users.gestionnaire.email' => null,
            'aquasecure.demo.users.citoyen.email' => null,
        ]);

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('zones', 0);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_demo_data_seeder_populates_all_modules_and_is_idempotent(): void
    {
        Storage::fake('local');

        config([
            'aquasecure.demo.users.gestionnaire.email' => 'demo.gestionnaire@example.test',
            'aquasecure.demo.users.citoyen.email' => 'demo.citoyen@example.test',
        ]);

        $gestionnaire = User::factory()->gestionnaire()->create([
            'email' => 'demo.gestionnaire@example.test',
        ]);
        $citoyen = User::factory()->citoyen()->create([
            'email' => 'demo.citoyen@example.test',
        ]);

        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('zones', 3);
        $this->assertDatabaseCount('infrastructures', 6);
        $this->assertDatabaseCount('maintenances', 4);
        $this->assertDatabaseCount('contractors', 3);
        $this->assertDatabaseCount('projects', 4);
        $this->assertDatabaseCount('project_phases', 8);
        $this->assertDatabaseCount('fundings', 8);
        $this->assertDatabaseCount('project_documents', 6);
        $this->assertDatabaseCount('incidents', 5);
        $this->assertDatabaseCount('incident_comments', 3);
        $this->assertDatabaseCount('incident_status_histories', 2);
        $this->assertDatabaseCount('quality_thresholds', 12);
        $this->assertDatabaseCount('water_quality_samples', 7);
        $this->assertDatabaseCount('water_quality_parameters', 22);
        $this->assertDatabaseCount('water_quality_alerts', 2);
        $this->assertDatabaseCount('water_levels', 4);
        $this->assertDatabaseCount('consumption_readings', 18);
        $this->assertDatabaseCount('restrictions', 3);
        $this->assertDatabaseCount('scheduled_cuts', 3);
        $this->assertDatabaseCount('alert_subscriptions', 2);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame(
            1,
            Funding::where('donateur_id', $citoyen->id)->where('source', 'don')->count(),
        );

        $document = \App\Models\Project\ProjectDocument::where('titre', 'Rapport de diagnostic')->firstOrFail();
        Storage::disk('local')->assertExists($document->chemin_fichier);

        $this->assertTrue(
            \App\Models\Quality\WaterQualityAlert::where('statut', 'active')->exists(),
        );

        $this->assertSame(
            3,
            \App\Models\Incident\Incident::whereNotNull('assigned_to')->count(),
        );

        $this->assertSame($gestionnaire->id, $document->project->responsable_id);
    }
}
