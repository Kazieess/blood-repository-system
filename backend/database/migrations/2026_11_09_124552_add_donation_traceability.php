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
        Schema::table('blood_units', function (Blueprint $table) {
            $table->foreignUuid('donation_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('clinical_entries', function (Blueprint $table) {
            $table->foreignUuid('donation_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('donation_id'));
        Schema::table('blood_units', fn (Blueprint $table) => $table->dropConstrainedForeignId('donation_id'));
    }
};
