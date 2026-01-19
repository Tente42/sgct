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
        Schema::table('extensions', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('extension');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->enum('permission', ['Internal', 'Local', 'National', 'International'])->default('Internal')->after('phone');
            $table->boolean('do_not_disturb')->default(false)->after('permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'permission', 'do_not_disturb']);
        });
    }
};
