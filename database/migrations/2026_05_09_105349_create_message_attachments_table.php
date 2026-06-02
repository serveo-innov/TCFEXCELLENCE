<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->string('file_url');
            $table->string('file_name');
            $table->string('file_type'); // 'pdf', 'audio', 'image'
            $table->bigInteger('file_size'); // en bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};