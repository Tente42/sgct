<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            // Agregamos solo la columna nueva
            $table->integer('max_contacts')->default(1)->after('do_not_disturb');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            // Para borrarla si deshaces cambios
            $table->dropColumn('max_contacts');
        });
    }
};