<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id');
            $table->foreign('learner_id')->references('user_id')->on('learners')->cascadeOnDelete();
            $table->uuid('coach_id');
            $table->foreign('coach_id')->references('user_id')->on('coaches')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_messages');
    }
};