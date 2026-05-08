<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competence_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('competence', ['CO', 'CE', 'EO', 'EE']);
            $table->float('score')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'competence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competence_scores');
    }
};