<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->longText('content_text')->nullable();   // rich HTML for text lessons
            $table->string('video_url')->nullable();        // YouTube/Vimeo source URL
            $table->string('video_provider')->nullable();   // youtube | vimeo | upload
            $table->string('external_url')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
