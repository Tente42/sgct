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
        Schema::table('pbx_connections', function (Blueprint $table) {
            $table->enum('status', ['pending', 'syncing', 'ready', 'error'])
                  ->default('pending')
                  ->after('verify_ssl');
            $table->text('sync_message')->nullable()->after('status');
            $table->timestamp('last_sync_at')->nullable()->after('sync_message');
        });

        // Marcar las centrales existentes como "ready"
        \DB::table('pbx_connections')->update(['status' => 'ready']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pbx_connections', function (Blueprint $table) {
            $table->dropColumn(['status', 'sync_message', 'last_sync_at']);
        });
    }
};
