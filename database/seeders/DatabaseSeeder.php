<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Lance les comptes de démonstration puis le jeu de données complet.
     * Les deux seeders s'arrêtent sans erreur si leur configuration est
     * absente ou en production.
     */
    public function run(): void
    {
        $this->call([
            DemoUsersSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
