<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $institution = Institution::query()->firstOrCreate(
            ['slug' => 'sims-college-of-pharmacy'],
            [
                'name' => 'SIMS College of Pharmacy',
                'status' => 'active',
                'timezone' => 'Asia/Kolkata',
            ],
        );

        $users = [
            ['name' => 'Ananya Rao', 'email' => 'student@pharmalab.test', 'role' => UserRole::Student],
            ['name' => 'Dr. Meera Iyer', 'email' => 'faculty@pharmalab.test', 'role' => UserRole::Faculty],
            ['name' => 'Arjun Menon', 'email' => 'admin@pharmalab.test', 'role' => UserRole::Administrator],
        ];

        foreach ($users as $user) {
            User::factory()->create([
                ...$user,
                'institution_id' => $institution->id,
                'password' => 'password',
            ]);
        }
    }
}
