<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['slug' => 'developer', 'name' => 'Desenvolvedor'],
            ['slug' => 'quality_assurance', 'name' => 'Quality Assurance'],
            ['slug' => 'requirement_analyst', 'name' => 'Analista de Requisitos'],
            ['slug' => 'project_manager', 'name' => 'Gerente de Projeto'],
            ['slug' => 'admin', 'name' => 'Administrador'],
        ];

        foreach ($profiles as $profile) {
            Profile::updateOrCreate(['slug' => $profile['slug']], $profile);
        }
    }
}
