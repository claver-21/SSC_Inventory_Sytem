<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', Role::SLUG_ADMIN)->firstOrFail();
        $memberRole = Role::where('slug', Role::SLUG_MEMBER)->firstOrFail();

        $users = [
            [
                'email' => 'admin@pcb-ssc.edu.ph',
                'name' => 'SSC Administrator',
                'role_id' => $adminRole->id,
                'password' => Hash::make('Admin@PCB2026!'),
                'school_id' => 'PCB-OSAS-001',
                'sex' => 'Male',
                'position' => 'System Administrator',
                'department' => 'Supreme Student Council',
                'phone' => '0917-000-0001',
                'is_active' => true,
                'mfa_enabled' => false,
                'privacy_consent_at' => now(),
                'privacy_consent_version' => 'v1.0',
            ],
            [
                'email' => 'student@pcb-ssc.edu.ph',
                'name' => 'SSC Student Representative',
                'role_id' => $memberRole->id,
                'password' => Hash::make('Student@PCB2026!'),
                'school_id' => 'PCB-SSC-2026-004',
                'sex' => 'Female',
                'position' => 'SSC Student',
                'department' => 'Supreme Student Council',
                'phone' => '0917-000-0004',
                'is_active' => true,
                'mfa_enabled' => false,
                'privacy_consent_at' => now(),
                'privacy_consent_version' => 'v1.0',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
