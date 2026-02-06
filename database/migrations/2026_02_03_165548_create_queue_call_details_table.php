<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Esta tabla almacena datos detallados de llamadas de cola obtenidos desde queueapi
     * para evitar llamadas lentas a la API en tiempo real.
     */
    public function up(): void
    {
        Schema::create('queue_call_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbx_connection_id')->constrained()->onDelete('cascade');
            
            // Identificación de la llamada
            $table->string('queue', 20)->index();          // Número de cola (6500)
            $table->string('caller', 50);                   // Quien llamó a la cola
            $table->string('agent', 20)->nullable();        // Agente que atendió/intentó (4444, 4445, NONE)
            
            // Timestamps de la central
            $table->dateTime('call_time');                  // Hora de la llamada
            
            // Métricas
            $table->integer('wait_time')->default(0);       // Tiempo de espera en segundos
            $table->integer('talk_time')->default(0);       // Tiempo de conversación en segundos
            $table->boolean('connected')->default(false);    // Si fue contestada o no
            
            // Metadatos
            $table->timestamps();
            
            // Índices para consultas frecuentes
            $table->index(['pbx_connection_id', 'queue', 'call_time']);
            $table->index(['pbx_connection_id', 'agent', 'call_time']);
            $table->index(['call_time', 'connected']);
            
            // Índice único para evitar duplicados
            $table->unique(['pbx_connection_id', 'queue', 'caller', 'agent', 'call_time'], 'queue_call_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_call_details');
    }
};
