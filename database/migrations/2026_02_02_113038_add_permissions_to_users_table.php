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
        Schema::table('users', function (Blueprint $table) {
            // Permisos granulares
            $table->boolean('can_sync_calls')->default(false)->after('role');
            $table->boolean('can_edit_extensions')->default(false)->after('can_sync_calls');
            $table->boolean('can_update_ips')->default(false)->after('can_edit_extensions');
            $table->boolean('can_edit_rates')->default(false)->after('can_update_ips');
            $table->boolean('can_manage_pbx')->default(false)->after('can_edit_rates');
            $table->boolean('can_export_pdf')->default(true)->after('can_manage_pbx');
            $table->boolean('can_export_excel')->default(true)->after('can_export_pdf');
            $table->boolean('can_view_charts')->default(true)->after('can_export_excel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'can_sync_calls',
                'can_edit_extensions',
                'can_update_ips',
                'can_edit_rates',
                'can_manage_pbx',
                'can_export_pdf',
                'can_export_excel',
                'can_view_charts',
            ]);
        });
    }
};
