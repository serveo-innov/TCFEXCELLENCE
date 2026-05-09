<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id');
            $table->foreign('learner_id')->references('user_id')->on('learners')->cascadeOnDelete();
            $table->uuid('competence_id');
            $table->foreign('competence_id')->references('id')->on('competences')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->enum('level', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['learner_id', 'competence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress');
    }
};