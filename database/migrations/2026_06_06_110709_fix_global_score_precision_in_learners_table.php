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
    Schema::table('learners', function (Blueprint $table) {
        $table->decimal('global_score', 5, 2)->default(0)->change();
    });
}

public function down(): void
{
    Schema::table('learners', function (Blueprint $table) {
        $table->decimal('global_score', 3, 2)->default(0)->change();
    });
}
};
