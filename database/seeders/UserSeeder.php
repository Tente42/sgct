<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear Usuario Administrador
        // Lee los datos desde config/services.php -> admins
        User::create([
            'name'     => config('services.admins.name'),
            'email'    => config('services.admins.email'),
            'password' => Hash::make(config('services.admins.pass')),
            // 'role' => 'admin', // Descomentar si implementas roles a futuro
        ]);

        // 2. Crear Usuario Trabajador
        // Lee los datos desde config/services.php -> users
        User::create([
            'name'     => config('services.users.name'),
            'email'    => config('services.users.email'),
            'password' => Hash::make(config('services.users.pass')),
        ]);
    }
}