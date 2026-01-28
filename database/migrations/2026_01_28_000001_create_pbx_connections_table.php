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
        Schema::create('pbx_connections', function (Blueprint $table) {
            $table->id();
            
            // Nombre descriptivo de la central (ej: "Central Oficina Principal")
            $table->string('name')->nullable();
            
            // Datos de conexión
            $table->string('ip');
            $table->integer('port');
            $table->string('username');
            $table->text('password'); // Se encripta via cast en el modelo
            
            // Configuración SSL
            $table->boolean('verify_ssl')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pbx_connections');
    }
};
