<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Collapse three parallel `position` sequences into one sequence per curriculum bucket.
 *
 * Before: lessons.position ran per module, assessments.position per (module, placement)
 * and assignments.position per module — three ladders that could never interleave, which
 * is why a quiz could not be dropped between lesson 2 and lesson 3.
 *
 * After: a bucket is a module (or the course-level bucket, module_id = null) and every
 * lesson, assessment and assignment in it shares one 1..n ladder.
 *
 * This backfill renumbers each bucket in EXACTLY the order it renders today —
 * pre-module assessments → lessons → post-module assessments → assignments — so no live
 * course changes visibly. Written against the tables directly rather than the models: a
 * migration has to keep producing this result years from now, whatever the models grow
 * into.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('courses')->orderBy('id')->pluck('id')->each(function (int $courseId) {
            $moduleIds = DB::table('modules')
                ->where('course_id', $courseId)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id');

            foreach ($moduleIds as $moduleId) {
                $this->renumber(
                    // Today's render order inside a module.
                    $this->assessments($courseId, $moduleId, 'pre_module')
                        ->concat($this->lessons($moduleId))
                        ->concat($this->assessments($courseId, $moduleId, 'post_module'))
                        // Any other placement value on a module-attached assessment
                        // (a standalone that kept its module) sorts with post-module.
                        ->concat($this->assessments($courseId, $moduleId, null, ['pre_module', 'post_module']))
                        ->concat($this->assignments($courseId, $moduleId))
                );
            }

            // The course-level bucket: standalone assessments, then standalone assignments.
            $this->renumber(
                $this->assessments($courseId, null)
                    ->concat($this->assignments($courseId, null))
            );
        });
    }

    /**
     * Irreversible by design — the three original ladders cannot be recovered from the
     * merged one, and re-splitting them would reorder live courses. Rolling back leaves
     * the merged positions in place, which every reader still understands.
     */
    public function down(): void {}

    /**
     * @param  list<string>  $exceptPlacements  used to sweep up unexpected placement values
     * @return Collection<int, array{table: string, id: int}>
     */
    private function assessments(int $courseId, ?int $moduleId, ?string $placement = null, array $exceptPlacements = []): Collection
    {
        $query = DB::table('assessments')
            ->where('course_id', $courseId)
            ->when($moduleId === null, fn ($q) => $q->whereNull('module_id'), fn ($q) => $q->where('module_id', $moduleId))
            ->when($placement !== null, fn ($q) => $q->where('placement', $placement))
            ->when($exceptPlacements !== [], fn ($q) => $q->whereNotIn('placement', $exceptPlacements));

        return $query->orderBy('position')->orderBy('id')->pluck('id')
            ->map(fn (int $id) => ['table' => 'assessments', 'id' => $id]);
    }

    /**
     * @return Collection<int, array{table: string, id: int}>
     */
    private function assignments(int $courseId, ?int $moduleId): Collection
    {
        return DB::table('assignments')
            ->where('course_id', $courseId)
            ->when($moduleId === null, fn ($q) => $q->whereNull('module_id'), fn ($q) => $q->where('module_id', $moduleId))
            ->orderBy('position')->orderBy('id')->pluck('id')
            ->map(fn (int $id) => ['table' => 'assignments', 'id' => $id]);
    }

    /**
     * @return Collection<int, array{table: string, id: int}>
     */
    private function lessons(int $moduleId): Collection
    {
        return DB::table('lessons')
            ->where('module_id', $moduleId)
            ->orderBy('position')->orderBy('id')->pluck('id')
            ->map(fn (int $id) => ['table' => 'lessons', 'id' => $id]);
    }

    /**
     * @param  Collection<int, array{table: string, id: int}>  $rows
     */
    private function renumber(Collection $rows): void
    {
        foreach ($rows->values() as $index => $row) {
            DB::table($row['table'])->where('id', $row['id'])->update(['position' => $index + 1]);
        }
    }
};
