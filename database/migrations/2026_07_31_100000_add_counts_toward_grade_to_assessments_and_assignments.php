<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split the two meanings `is_required` was carrying at once.
 *
 *   is_required          the student must complete this to finish the course
 *   counts_toward_grade  this item's score is part of the course grade
 *
 * They are genuinely different questions: a practice quiz can be compulsory to work
 * through and still not belong in the transcript. Defaulting to TRUE reproduces
 * today's behaviour exactly — every existing required item stays in the gradebook —
 * so no backfill is needed, and none is possible to get wrong.
 *
 * A non-required item that IS graded is deliberately not offered: GradebookService
 * derives the gradebook from required items, so "graded but optional" would mean the
 * course could hit 100% with an ungraded score outstanding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->boolean('counts_toward_grade')->default(true)->after('is_required');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('counts_toward_grade')->default(true)->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('counts_toward_grade');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('counts_toward_grade');
        });
    }
};
