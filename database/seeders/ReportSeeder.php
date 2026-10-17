<?php

namespace Database\Seeders;

use App\Models\Report;
use Illuminate\Database\Seeder;

/**
 * Jeu de données de DÉMONSTRATION.
 *
 * Ces signalements sont fictifs et servent uniquement à visualiser le Back Office.
 * Ils ne représentent aucune donnée métier réelle.
 */
class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demo = [
            [
                'type' => 'fuite',
                'title' => 'Fuite visible rue des Lilas',
                'description' => "Écoulement continu d'eau sur la chaussée depuis un regard, au niveau du n°12.",
                'address' => '12 rue des Lilas',
                'status' => 'nouveau',
            ],
            [
                'type' => 'coupure',
                'title' => 'Coupure non annoncée quartier Centre',
                'description' => "Plus d'eau au robinet depuis ce matin dans tout l'îlot.",
                'address' => 'Quartier Centre',
                'status' => 'en_cours',
            ],
            [
                'type' => 'qualite',
                'title' => 'Eau trouble au robinet',
                'description' => "L'eau présente une couleur brunâtre à l'ouverture du robinet.",
                'address' => '5 avenue de la Gare',
                'status' => 'en_cours',
            ],
            [
                'type' => 'fuite',
                'title' => 'Fuite sous trottoir',
                'description' => "Affaissement du trottoir laissant supposer une fuite sous la chaussée.",
                'address' => 'Place du Marché',
                'status' => 'traite',
            ],
            [
                'type' => 'autre',
                'title' => "Couvercle d'un regard déplacé",
                'description' => "Le couvercle d'un regard d'eau est déplacé et présente un danger.",
                'address' => 'Rue Victor Hugo',
                'status' => 'rejete',
            ],
        ];

        foreach ($demo as $attributes) {
            Report::create($attributes + [
                'user_id' => null,
                'reported_at' => now()->subDays(random_int(1, 20)),
            ]);
        }
    }
}
