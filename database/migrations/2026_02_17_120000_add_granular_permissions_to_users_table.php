<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega permisos granulares adicionales:
     * - can_sync_extensions: Sincronizar anexos desde la central
     * - can_sync_queues: Sincronizar colas desde la central
     * - can_view_extensions: Ver la página de anexos
     * - can_view_rates: Ver la página de tarifas
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_sync_extensions')->default(false)->after('can_sync_calls');
            $table->boolean('can_sync_queues')->default(false)->after('can_sync_extensions');
            $table->boolean('can_view_extensions')->default(true)->after('can_view_charts');
            $table->boolean('can_view_rates')->default(true)->after('can_view_extensions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_sync_extensions',
                'can_sync_queues',
                'can_view_extensions',
                'can_view_rates',
            ]);
        });
    }
};
