<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            InventoryFoundationSeeder::class,
            SystemSettingSeeder::class,
            UserSeeder::class,
            DummyInventorySeeder::class,
        ]);
    }
}
