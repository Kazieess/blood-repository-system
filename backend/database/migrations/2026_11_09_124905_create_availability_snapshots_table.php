<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_snapshots', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->char('request_hash', 64);
            $table->json('payload');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('valid_until');
            $table->timestamp('withdrawn_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_snapshots');
    }
};
