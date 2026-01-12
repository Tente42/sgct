<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('extensions', function (Blueprint $table) {
        $table->id();
        
        // El número de anexo (Ej: "101") - Será único
        $table->string('extension')->unique();
        
        // El nombre real (Ej: "Juan Perez")
        $table->string('fullname')->nullable();
        
        // Email (por si acaso quieres enviarles reportes después)
        $table->string('email')->nullable();
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
