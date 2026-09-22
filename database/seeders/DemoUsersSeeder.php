<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo accounts. Idempotent and free of dev-only dependencies (no Faker), so it
 * can run on every boot of a demo deployment (SEED_DEMO=true).
 */
class DemoUsersSeeder extends Seeder
{
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

        foreach ($users as $attributes) {
            if (User::query()->where('email', $attributes['email'])->exists()) {
                continue;
            }

            $user = new User([
                ...$attributes,
                'institution_id' => $institution->id,
                'status' => UserStatus::Active,
                'password' => 'password',
            ]);
            $user->email_verified_at = Carbon::now();
            $user->save();
        }
    }
}
