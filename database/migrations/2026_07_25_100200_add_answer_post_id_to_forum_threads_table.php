<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The thread's accepted answer, set by the thread author or an instructor. Its
     * presence is what flags a thread "Answered". Added after forum_posts exists so
     * the FK has a real target; nullOnDelete so hard-removing the post clears it (the
     * service also clears it when the post is soft-deleted).
     */
    public function up(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->foreignId('answer_post_id')->nullable()->after('is_locked')
                ->constrained('forum_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('answer_post_id');
        });
    }
};
