<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 16 — the readable history of what changed in a course, and when.
 *
 * Distinct from the audit log on purpose: the audit trail is forensic (full before/after
 * payloads, every model, administrators only), while this is the narrative students and
 * instructors read — one sentence per change, in their words.
 *
 * Insert-only; the model refuses updates and deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // Nullable so a system-driven change still records, and so removing a member
            // of staff never erases the history of what they changed.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // The item the change was made to, when it outlived the change.
            $table->nullableMorphs('subject');

            $table->string('action');           // created | updated | hidden | restored | reordered
            $table->string('significance');     // cosmetic | material
            $table->text('summary');            // the sentence a student reads
            $table->text('note')->nullable();   // the instructor's optional message

            $table->timestamp('created_at')->nullable();

            // The learner panel reads "material changes on this course since I enrolled".
            $table->index(['course_id', 'significance', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_changes');
    }
};
