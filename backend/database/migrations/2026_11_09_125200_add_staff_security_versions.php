<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('session_version')->default(1);
            $table->uuid('provisioning_key')->nullable()->unique();
            $table->char('provisioning_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provisioning_key']);
            $table->dropColumn(['version', 'session_version', 'provisioning_key', 'provisioning_hash']);
        });
    }
};
