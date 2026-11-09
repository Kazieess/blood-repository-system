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
        Schema::create('clinical_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 100);
            $table->string('format', 20);
            $table->json('choices')->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('reference_text', 500)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('clinical_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donor_id')->constrained()->restrictOnDelete();
            $table->string('category', 20);
            $table->text('payload');
            $table->string('source_reference', 120);
            $table->timestamp('observed_at');
            $table->string('status', 20)->default('recorded');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['recorded_by', 'creation_key']);
            $table->index(['donor_id', 'category', 'created_at']);
        });
        Schema::create('clinical_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('clinical_entry_id')->constrained()->restrictOnDelete();
            $table->text('snapshot');
            $table->unsignedInteger('version');
            $table->timestamp('created_at');
            $table->unique(['clinical_entry_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_revisions');
        Schema::dropIfExists('clinical_entries');
        Schema::dropIfExists('clinical_fields');
    }
};
