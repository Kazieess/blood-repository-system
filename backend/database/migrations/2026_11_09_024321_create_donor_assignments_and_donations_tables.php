<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('donor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->json('clinical_permissions')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['donor_id', 'user_id']);
        });
        DB::table('donors')->orderBy('id')->chunk(200, function ($donors): void {
            foreach ($donors as $donor) {
                DB::table('donor_assignments')->insert(['donor_id' => $donor->id, 'user_id' => $donor->created_by, 'assigned_by' => $donor->created_by, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donor_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->date('donated_on')->index();
            $table->string('location', 100);
            $table->string('status', 20)->default('completed')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['created_by', 'creation_key']);
            $table->index(['donor_id', 'status', 'donated_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
        Schema::dropIfExists('donor_assignments');
    }
};
