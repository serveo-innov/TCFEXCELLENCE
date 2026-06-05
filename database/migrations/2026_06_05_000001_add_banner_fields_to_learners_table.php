<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->timestamp('banner_hidden_until')->nullable()->after('last_active_at');
            $table->text('private_note')->nullable()->after('banner_hidden_until');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['banner_hidden_until', 'private_note']);
        });
    }
};
