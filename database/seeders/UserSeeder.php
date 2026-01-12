<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Usuario Administrador
    \App\Models\User::create([
        'name' => 'Administrador',
        'email' => 'admin@empresa.com',
        'password' => \Illuminate\Support\Facades\Hash::make('admin123'), // <--- Cambia la clave aquí
       // 'role' => 'admin', // Opcional, si quieres distinguir permisos luego
    ]);

    // 2. Usuario Trabajador
    \App\Models\User::create([
        'name' => 'Usuario',
        'email' => 'usuario@empresa.com',
        'password' => \Illuminate\Support\Facades\Hash::make('usuario123'), // <--- Cambia la clave aquí
    ]);
    }
}
