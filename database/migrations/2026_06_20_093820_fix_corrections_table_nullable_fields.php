<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('corrections', function (Blueprint $table) {
            $table->dropForeign(['coach_id']);
            $table->string('coach_id')->nullable()->change();
            $table->decimal('score', 5, 2)->nullable()->change();
            $table->foreign('coach_id')->references('user_id')->on('coaches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corrections', function (Blueprint $table) {
            $table->dropForeign(['coach_id']);
            $table->string('coach_id')->nullable(false)->change();
            $table->decimal('score', 5, 2)->nullable(false)->change();
            $table->foreign('coach_id')->references('user_id')->on('coaches');
        });
    }
};
