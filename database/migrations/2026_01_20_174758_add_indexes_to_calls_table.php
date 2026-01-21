<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega índices para mejorar el rendimiento de consultas frecuentes
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            // Índice para búsquedas por fecha (filtros de reporte)
            $table->index('start_time', 'calls_start_time_index');
            
            // Índice para búsquedas por origen/anexo
            $table->index('source', 'calls_source_index');
            
            // Índice compuesto para consultas que filtran por fecha Y anexo
            $table->index(['start_time', 'source'], 'calls_start_time_source_index');
            
            // Índice para filtrar por estado de llamada
            $table->index('disposition', 'calls_disposition_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('calls_start_time_index');
            $table->dropIndex('calls_source_index');
            $table->dropIndex('calls_start_time_source_index');
            $table->dropIndex('calls_disposition_index');
        });
    }
};