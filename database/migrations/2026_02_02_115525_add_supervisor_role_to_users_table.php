<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para incluir 'supervisor'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'supervisor', 'user') DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero cambiar supervisores a users para evitar errores
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'user']);
        
        // Restaurar el ENUM original
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user') DEFAULT 'user'");
    }
};
