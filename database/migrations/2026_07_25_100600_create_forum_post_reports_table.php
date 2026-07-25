<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member flags a forum post for admin review. Moderation itself (removing the
     * post) is a separate action; this is only the queue of things to look at.
     */
    public function up(): void
    {
        Schema::create('forum_post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // reporter
            $table->string('reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One open report per user per post — reporting twice just updates the row.
            $table->unique(['forum_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_reports');
    }
};
