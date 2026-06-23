<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('analysis_id');
            $table->uuid('user_id');
            $table->integer('amount_cents');
            $table->string('currency')->default('usd');
            $table->string('stripe_session_id')->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('created_at')->nullable();

            $table->foreign('analysis_id')->references('id')->on('analyses')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
