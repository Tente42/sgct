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
        // ============================================
        // ASEGURAR QUE EXISTE EL REGISTRO PBX ID=1
        // ============================================
        if (DB::table('pbx_connections')->where('id', 1)->doesntExist()) {
            DB::table('pbx_connections')->insert([
                'id' => 1,
                'name' => 'Central Principal',
                'ip' => '10.36.1.10',
                'port' => 7110,
                'username' => 'cdrapi',
                'password' => encrypt('123api'),
                'verify_ssl' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================
        // MODIFICAR TABLA CALLS
        // ============================================
        Schema::table('calls', function (Blueprint $table) {
            // 1. Agregar columna pbx_connection_id con valor por defecto 1
            $table->foreignId('pbx_connection_id')
                  ->default(1)
                  ->after('id')
                  ->constrained('pbx_connections')
                  ->onDelete('cascade');

            // 2. Eliminar restricción unique actual de unique_id
            $table->dropUnique(['unique_id']);

            // 3. Agregar índice simple a pbx_connection_id
            $table->index('pbx_connection_id', 'calls_pbx_connection_id_index');

            // 4. Agregar índice compuesto (pbx_connection_id + start_time)
            $table->index(['pbx_connection_id', 'start_time'], 'calls_pbx_connection_start_time_index');

            // 5. Agregar clave única compuesta (pbx_connection_id + unique_id)
            $table->unique(['pbx_connection_id', 'unique_id'], 'calls_pbx_unique_id_unique');
        });

        // ============================================
        // MODIFICAR TABLA EXTENSIONS
        // ============================================
        Schema::table('extensions', function (Blueprint $table) {
            // 1. Agregar columna pbx_connection_id con valor por defecto 1
            $table->foreignId('pbx_connection_id')
                  ->default(1)
                  ->after('id')
                  ->constrained('pbx_connections')
                  ->onDelete('cascade');

            // 2. Eliminar restricción unique actual de extension
            $table->dropUnique(['extension']);

            // 3. Agregar índice simple a pbx_connection_id
            $table->index('pbx_connection_id', 'extensions_pbx_connection_id_index');

            // 4. Agregar índice compuesto (pbx_connection_id + extension)
            $table->index(['pbx_connection_id', 'extension'], 'extensions_pbx_connection_extension_index');

            // 5. Agregar clave única compuesta (pbx_connection_id + extension)
            $table->unique(['pbx_connection_id', 'extension'], 'extensions_pbx_extension_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ============================================
        // REVERTIR CAMBIOS EN TABLA CALLS
        // ============================================
        Schema::table('calls', function (Blueprint $table) {
            // 1. Eliminar clave única compuesta
            $table->dropUnique('calls_pbx_unique_id_unique');

            // 2. Eliminar índice compuesto
            $table->dropIndex('calls_pbx_connection_start_time_index');

            // 3. Eliminar índice simple
            $table->dropIndex('calls_pbx_connection_id_index');

            // 4. Restaurar unique original en unique_id
            $table->unique('unique_id');

            // 5. Eliminar foreign key y columna
            $table->dropForeign(['pbx_connection_id']);
            $table->dropColumn('pbx_connection_id');
        });

        // ============================================
        // REVERTIR CAMBIOS EN TABLA EXTENSIONS
        // ============================================
        Schema::table('extensions', function (Blueprint $table) {
            // 1. Eliminar clave única compuesta
            $table->dropUnique('extensions_pbx_extension_unique');

            // 2. Eliminar índice compuesto
            $table->dropIndex('extensions_pbx_connection_extension_index');

            // 3. Eliminar índice simple
            $table->dropIndex('extensions_pbx_connection_id_index');

            // 4. Restaurar unique original en extension
            $table->unique('extension');

            // 5. Eliminar foreign key y columna
            $table->dropForeign(['pbx_connection_id']);
            $table->dropColumn('pbx_connection_id');
        });
    }
};
