<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('registration_type', ['SOLO', 'COACHED']);
            $table->string('country')->nullable();
            $table->date('target_exam_date')->nullable();
            $table->enum('estimated_level', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'])->nullable();
            $table->decimal('global_score', 3, 2)->default(0);
            $table->boolean('is_expert_candidate')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};