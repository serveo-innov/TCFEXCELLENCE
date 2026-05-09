<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
            $table->uuid('coach_id');
            $table->foreign('coach_id')->references('user_id')->on('coaches')->cascadeOnDelete();
            $table->text('corrected_text')->nullable();
            $table->string('audio_feedback_url')->nullable();
            $table->decimal('score', 5, 2);
            $table->text('feedback');
            $table->boolean('is_ai_assisted')->default(false);
            $table->json('ai_raw_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};