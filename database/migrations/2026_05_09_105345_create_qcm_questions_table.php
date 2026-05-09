<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exercise_id');
            $table->foreign('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->text('question');
            $table->text('explanation')->nullable();
            $table->decimal('points', 5, 2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_questions');
    }
};