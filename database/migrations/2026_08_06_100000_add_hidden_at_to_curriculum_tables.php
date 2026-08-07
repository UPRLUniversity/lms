<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 16 — "hide from students" as the safe alternative to deleting curriculum that
 * already carries student work.
 *
 * One nullable timestamp across all four curriculum tables rather than four different
 * status vocabularies: a hidden item leaves the learner's outline and stops counting
 * toward completion and grades, while every attempt, submission and grade attached to it
 * stays intact and queryable.
 *
 * Indexed alongside the owning key because every learner-facing curriculum read filters
 * on it.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>  table => owning foreign key it is queried with
     */
    private array $tables = [
        'modules' => 'course_id',
        'lessons' => 'module_id',
        'assessments' => 'course_id',
        'assignments' => 'course_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $owner) {
            Schema::table($table, function (Blueprint $t) use ($table, $owner) {
                $t->timestamp('hidden_at')->nullable()->after('position');
                $t->index([$owner, 'hidden_at'], "{$table}_{$owner}_hidden_at_index");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $owner) {
            Schema::table($table, function (Blueprint $t) use ($table, $owner) {
                $t->dropIndex("{$table}_{$owner}_hidden_at_index");
                $t->dropColumn('hidden_at');
            });
        }
    }
};
