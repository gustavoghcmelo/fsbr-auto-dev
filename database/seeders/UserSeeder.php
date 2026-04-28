<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Dev User', 'email' => 'dev@fsbr.local', 'profile' => 'developer'],
            ['name' => 'QA User', 'email' => 'qa@fsbr.local', 'profile' => 'quality_assurance'],
            ['name' => 'RA User', 'email' => 'ra@fsbr.local', 'profile' => 'requirement_analyst'],
            ['name' => 'PM User', 'email' => 'pm@fsbr.local', 'profile' => 'project_manager'],
            ['name' => 'Admin User', 'email' => 'admin@fsbr.local', 'profile' => 'admin'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'profile_id' => Profile::where('slug', $user['profile'])->value('id'),
                ]
            );
        }
    }
}
