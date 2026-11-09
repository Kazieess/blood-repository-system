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
        Schema::create('blood_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 80)->unique();
            $table->string('blood_type', 3)->index();
            $table->string('component', 80);
            $table->string('location', 100);
            $table->timestamp('collected_at');
            $table->timestamp('expires_at')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key');
            $table->char('creation_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['created_by', 'creation_key']);
            $table->index(['blood_type', 'component', 'location']);
        });
        Schema::create('inventory_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('blood_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('reason', 500);
            $table->timestamp('created_at');
        });
        Schema::create('stock_rules', function (Blueprint $table) {
            $table->id();
            $table->string('blood_type', 3);
            $table->string('component', 80);
            $table->string('location', 100);
            $table->unsignedInteger('threshold');
            $table->json('recipient_ids');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unique(['blood_type', 'component', 'location']);
            $table->timestamps();
        });
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_rule_id')->constrained()->restrictOnDelete();
            $table->string('condition', 20);
            $table->unsignedInteger('available_count');
            $table->boolean('open')->default(true);
            $table->foreignId('open_rule_id')->nullable()->unique()->constrained('stock_rules')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('alert_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unique(['stock_alert_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['alert_receipts', 'stock_alerts', 'stock_rules', 'inventory_events', 'blood_units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
