<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id');
            $table->foreign('learner_id')->references('user_id')->on('learners')->cascadeOnDelete();
            $table->uuid('exercise_id');
            $table->foreign('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->enum('type', ['TEXT', 'AUDIO', 'QCM']);
            $table->text('content_text')->nullable();
            $table->string('audio_url')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['PENDING', 'CORRECTED'])->default('PENDING');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};