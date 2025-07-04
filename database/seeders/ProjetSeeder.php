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
        Projet::create([
            'nom_projet' => 'ERP',
            'lien_git' => 'https://github.com/tayeb-a11/erp.git'
        ]);

        Projet::create([
            'nom_projet' => 'Commercial',
            'lien_git' => 'https://github.com/tayeb-a11/comercial.git'
        ]);
    }
}
