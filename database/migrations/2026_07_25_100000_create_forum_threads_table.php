<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Optional "Discuss this lesson" link from the Section-4 player.
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body'); // sanitized on save via the RichHtml cast ('basic' profile)
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            // Mirrors the newest post's time (or creation) so the list can sort by
            // real activity without a per-row aggregate. answer_post_id is added by a
            // follow-up migration once forum_posts exists (a real FK needs the target).
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Pinned first, then most-recently-active — the list's default order.
            $table->index(['course_id', 'is_pinned', 'last_activity_at']);
            $table->index(['course_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_threads');
    }
};
