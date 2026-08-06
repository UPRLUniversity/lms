<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 16 — freeze what a student was measured against, at the moment they finished.
 *
 * Course progress and grades were derived entirely live: "every published, required
 * assessment/assignment AS IT EXISTS RIGHT NOW". A student who completed in June was
 * therefore re-measured against August's curriculum whenever anything recomputed, so an
 * item added months later could restate a finished grade or un-complete a finished
 * course.
 *
 * This records the exact item set that counted, once, when the enrollment flips to
 * Completed. Written once and never rewritten (guarded on the model, like
 * Certificate.snapshot); active enrollments keep following the live course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->json('completion_snapshot')->nullable()->after('completed_at');
            $table->timestamp('completion_snapshot_at')->nullable()->after('completion_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['completion_snapshot', 'completion_snapshot_at']);
        });
    }
};
