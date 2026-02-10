<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega el campo userfield para clasificar llamadas por tipo UCM
     * Valores: 'Inbound', 'Outbound', 'Internal', o vacío
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('userfield', 50)->nullable()->after('disposition');
            $table->index('userfield', 'calls_userfield_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('calls_userfield_index');
            $table->dropColumn('userfield');
        });
    }
};
