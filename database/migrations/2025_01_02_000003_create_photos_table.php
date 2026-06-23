<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('analysis_id');
            $table->integer('position');
            $table->string('storage_path')->default('');
            $table->timestamp('created_at')->nullable();

            $table->foreign('analysis_id')->references('id')->on('analyses')->cascadeOnDelete();
            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
