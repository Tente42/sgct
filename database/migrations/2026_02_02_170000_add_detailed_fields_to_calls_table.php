<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos adicionales para análisis detallado de llamadas:
     * - action_type: Filtrar llamadas de cola (QUEUE) de las normales (DIAL)
     * - answer_time: Calcular tiempo de espera (answer_time - start_time)
     * - dstanswer: Saber qué agente/extensión contestó la llamada
     * - lastapp: Saber qué aplicación procesó la llamada (Dial, Queue, Wait)
     * - channel: Canal utilizado para la llamada
     * - src_trunk_name: Nombre del trunk de origen (útil para identificar tipo de llamada)
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // Campo clave para identificar tipo de llamada (DIAL, QUEUE, etc.)
            $table->string('action_type', 50)->nullable()->after('disposition');
            
            // Momento en que se contestó la llamada (para calcular tiempo de espera)
            $table->datetime('answer_time')->nullable()->after('start_time');
            
            // Extensión/agente que contestó la llamada
            $table->string('dstanswer', 50)->nullable()->after('destination');
            
            // Última aplicación ejecutada (Dial, Queue, Wait, etc.)
            $table->string('lastapp', 50)->nullable()->after('action_type');
            
            // Canal de origen y destino
            $table->string('channel', 100)->nullable()->after('lastapp');
            $table->string('dst_channel', 100)->nullable()->after('channel');
            
            // Nombre del trunk de origen (identifica si es entrante/saliente)
            $table->string('src_trunk_name', 100)->nullable()->after('dst_channel');
            
            // Índices para búsquedas frecuentes
            $table->index('action_type');
            $table->index('dstanswer');
            $table->index('lastapp');
            $table->index(['action_type', 'disposition']); // Para análisis de abandono por tipo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex(['action_type']);
            $table->dropIndex(['dstanswer']);
            $table->dropIndex(['lastapp']);
            $table->dropIndex(['action_type', 'disposition']);
            
            // Eliminar columnas
            $table->dropColumn([
                'action_type',
                'answer_time',
                'dstanswer',
                'lastapp',
                'channel',
                'dst_channel',
                'src_trunk_name'
            ]);
        });
    }
};
