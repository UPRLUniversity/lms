<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One-level replies only: a reply points at a top-level post, never at
            // another reply (enforced in the service). nullOnDelete so soft-moderating
            // a parent never orphans a child's FK integrity.
            $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->nullOnDelete();
            $table->text('body'); // sanitized on save via the RichHtml cast ('basic' profile)
            $table->timestamps();
            $table->softDeletes(); // moderation: a removed post is hidden, not erased

            $table->index(['forum_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
