<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Section 16 — the database's own refusal to destroy academic record.
 *
 * The application guard (CurriculumImpactService::assertDeletable) is what an instructor
 * actually meets, and it is what the tests cover. This migration is the backstop beneath
 * it: if a future code path ever deletes a lesson, assessment or assignment without
 * asking, the FK refuses rather than silently cascading away a student's progress,
 * attempts, submissions and grades.
 *
 * It never fires in normal use — the guards block those deletes first, and a course with
 * no enrolments has no student rows beneath it for the constraint to catch.
 *
 * Only the CONTENT side is restricted. The user_id foreign keys stay cascade: deleting a
 * person is a deliberate erasure (data-protection request) and should still take their
 * rows with it.
 *
 * SQLite cannot alter a foreign key without rebuilding the table, so this is skipped
 * there. The test suite runs on SQLite and therefore exercises the application guard
 * rather than this constraint — see docs/decisions.md, Section 16.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, references: string}>
     */
    private array $foreignKeys = [
        ['table' => 'lesson_progress', 'column' => 'lesson_id', 'references' => 'lessons'],
        ['table' => 'attempts', 'column' => 'assessment_id', 'references' => 'assessments'],
        ['table' => 'submissions', 'column' => 'assignment_id', 'references' => 'assignments'],
        ['table' => 'grades', 'column' => 'submission_id', 'references' => 'submissions'],
    ];

    public function up(): void
    {
        $this->rewrite(restrict: true);
    }

    public function down(): void
    {
        $this->rewrite(restrict: false);
    }

    private function rewrite(bool $restrict): void
    {
        if (! $this->supportsAlteringForeignKeys()) {
            return;
        }

        foreach ($this->foreignKeys as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk, $restrict) {
                $table->dropForeign([$fk['column']]);

                $definition = $table->foreign($fk['column'])
                    ->references('id')
                    ->on($fk['references']);

                $restrict ? $definition->restrictOnDelete() : $definition->cascadeOnDelete();
            });
        }
    }

    private function supportsAlteringForeignKeys(): bool
    {
        return DB::getDriverName() !== 'sqlite';
    }
};
