<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcm_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('qcm_question_id');
            $table->foreign('qcm_question_id')->references('id')->on('qcm_questions')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcm_options');
    }
};