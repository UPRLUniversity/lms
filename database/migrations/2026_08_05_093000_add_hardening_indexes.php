<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the heavy paths, from the Section 15 hardening sweep.
 *
 * Each one below was chosen against a query that actually exists, not added
 * speculatively — an unused index is write cost with no read benefit.
 *
 * The recurring shape is "filter by a parent, order by position". A separate index on
 * each column cannot serve that: MySQL uses one of them to filter and then sorts the
 * result. A COMPOSITE in (filter, sort) order serves both, and the sort disappears.
 * That is the whole story for the curriculum tables, whose ordering queries became far
 * hotter in Section 14 when lessons, assessments and assignments started sharing one
 * position ladder per bucket and the outline began reading all three on every render.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Unified curriculum ordering (Section 14).
        | CurriculumOrderService::bucketRows() runs, per bucket:
        |   where module_id = ? order by position, id
        | three times — once per item type — on every builder render and every reorder.
        */
        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['module_id', 'position'], 'lessons_module_position_index');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index(['module_id', 'position'], 'assessments_module_position_index');
            // The course-level bucket: module_id IS NULL, scoped by course.
            $table->index(['course_id', 'position'], 'assessments_course_position_index');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->index(['module_id', 'position'], 'assignments_module_position_index');
            $table->index(['course_id', 'position'], 'assignments_course_position_index');
        });

        Schema::table('modules', function (Blueprint $table) {
            // Module ordering within a course — `position` alone was indexed, which is
            // useless for "this course's modules, in order".
            $table->index(['course_id', 'position'], 'modules_course_position_index');
        });

        /*
        | Commerce catalogue + public site.
        | Course::inCatalogue() filters status AND visibility together, on the busiest
        | guest-facing query in the app (homepage, catalogue, every programme page).
        | Only `status` was indexed, so visibility was always a scan of the matches.
        */
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['status', 'visibility'], 'courses_status_visibility_index');
        });

        /*
        | Audit trail (this section).
        | The viewer filters by event and by date range, and always orders newest-first.
        | activitylog ships indexes for log_name, subject and causer but not these.
        */
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('event', 'activity_log_event_index');
            $table->index('created_at', 'activity_log_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $t) => $t->dropIndex('lessons_module_position_index'));

        Schema::table('assessments', function (Blueprint $t) {
            $t->dropIndex('assessments_module_position_index');
            $t->dropIndex('assessments_course_position_index');
        });

        Schema::table('assignments', function (Blueprint $t) {
            $t->dropIndex('assignments_module_position_index');
            $t->dropIndex('assignments_course_position_index');
        });

        Schema::table('modules', fn (Blueprint $t) => $t->dropIndex('modules_course_position_index'));
        Schema::table('courses', fn (Blueprint $t) => $t->dropIndex('courses_status_visibility_index'));

        Schema::table('activity_log', function (Blueprint $t) {
            $t->dropIndex('activity_log_event_index');
            $t->dropIndex('activity_log_created_at_index');
        });
    }
};
