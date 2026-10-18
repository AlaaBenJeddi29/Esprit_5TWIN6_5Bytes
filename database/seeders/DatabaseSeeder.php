<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the demo dataset.
     *
     * DemoUsersSeeder runs first : DemoDataSeeder bails without an existing
     * gestionnaire/admin account. Each seeder self-guards and skips in production.
     */
    public function run(): void
    {
        $this->call([
            DemoUsersSeeder::class,
            DemoDataSeeder::class,
            ReportSeeder::class,
        ]);
    }
}
