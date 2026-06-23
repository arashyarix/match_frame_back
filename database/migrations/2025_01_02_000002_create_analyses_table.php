<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name')->default('Photo test');
            // Lifecycle. "ready" = AI finished but HELD until reveal_at.
            $table->enum('status', ['draft', 'queued', 'processing', 'ready', 'revealed', 'failed'])
                ->default('draft');
            $table->string('audience')->default('w2534');
            $table->integer('photo_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable(); // when the AI finished
            $table->timestamp('reveal_at')->nullable();     // gate: hidden until now() >= reveal_at
            $table->json('report')->nullable();
            $table->text('error')->nullable();              // AI failure reason (shown in admin)

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
