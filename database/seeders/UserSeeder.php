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
            'role'     => 'admin', // ROL ADMIN - necesario para acceder a sincronización
            // Admin bypasses all permission checks, but setting true for consistency
            'can_sync_calls'      => true,
            'can_sync_extensions' => true,
            'can_sync_queues'     => true,
            'can_edit_extensions' => true,
            'can_update_ips'      => true,
            'can_edit_rates'      => true,
            'can_manage_pbx'      => true,
            'can_export_pdf'      => true,
            'can_export_excel'    => true,
            'can_view_charts'     => true,
            'can_view_extensions' => true,
            'can_view_rates'      => true,
        ]);

        // 2. Crear Usuario Trabajador
        // Lee los datos desde config/services.php -> users
        User::create([
            'name'     => config('services.users.name'),
            'email'    => config('services.users.email'),
            'password' => Hash::make(config('services.users.pass')),
            'role'     => 'user', // ROL USUARIO - acceso limitado
            // Permisos de visualización habilitados por defecto
            'can_view_extensions' => true,
            'can_view_rates'      => true,
            'can_export_pdf'      => true,
            'can_export_excel'    => true,
        ]);
    }
}