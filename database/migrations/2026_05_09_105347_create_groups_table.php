<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('type', ['MAIN', 'SUBGROUP', 'CORRECTION']);
            $table->enum('competence', ['CO', 'CE', 'EO', 'EE', 'GENERAL']);
            $table->uuid('coach_id');
            $table->foreign('coach_id')->references('user_id')->on('coaches')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};