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
    Schema::create('calls', function (Blueprint $table) {
        $table->id(); // ID interno de Laravel

        // --- CLAVE PARA NO DUPLICAR ---
        $table->string('unique_id')->unique(); // El ID de la Central

        // --- DATOS DE LA LLAMADA ---
        $table->dateTime('start_time'); 
        $table->string('source');       // Quién llama
        $table->string('destination');  // A quién llama
        
        // --- TIEMPOS ---
        $table->integer('duration')->default(0); 
        $table->integer('billsec')->default(0);  // Tiempo hablado real

        // --- EXTRAS ---
        $table->string('disposition');  // FAILED, ANSWERED
        $table->string('caller_name')->nullable(); 
        $table->string('recording_file')->nullable(); 

        $table->timestamps();
    });
}
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
