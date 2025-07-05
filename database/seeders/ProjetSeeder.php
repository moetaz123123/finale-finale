<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Projet;

class ProjetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projets = [
            [
                'nom_projet' => 'ERP',
                'lien_git' => 'https://github.com/tayeb-a11/erp.git'
            ],
            [
                'nom_projet' => 'Commercial',
                'lien_git' => 'https://github.com/tayeb-a11/comercial.git'
            ],
            [
                'nom_projet' => 'Restaurant',
                'lien_git' => 'https://github.com/tayeb-a11/resto.git'
            ]
        ];

        foreach ($projets as $projet) {
            Projet::firstOrCreate(
                ['nom_projet' => $projet['nom_projet']],
                $projet
            );
        }
    }
}
