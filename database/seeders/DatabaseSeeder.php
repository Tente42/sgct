<?php

namespace Database\Seeders;

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
        // Primero crear la conexión PBX (necesaria antes de calls/extensions)
        $this->call(PbxConnectionSeeder::class);

        // Luego los demás seeders
        $this->call(SettingSeeder::class);
        $this->call(UserSeeder::class);
    }
}
