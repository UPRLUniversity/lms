<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the Section-10 dashboards and reports demand: the enrolment trend groups on
 * enrolled_at, the certification report orders on issued_at, the assessment analytics
 * sweep attempts by (assessment_id, status) and attempt_answers by question_id, and the
 * "active users (30d)" figure filters on last_login_at. Append-only — no existing index
 * is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('enrolled_at');
        });

        Schema::table('attempts', function (Blueprint $table) {
            $table->index(['assessment_id', 'status']);
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->index('question_id');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->index('issued_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropIndex(['enrolled_at']));
        Schema::table('attempts', fn (Blueprint $table) => $table->dropIndex(['assessment_id', 'status']));
        Schema::table('attempt_answers', fn (Blueprint $table) => $table->dropIndex(['question_id']));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropIndex(['issued_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['last_login_at']));
    }
};
