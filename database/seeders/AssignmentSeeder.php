<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Database\Seeder;

/**
 * A clickable assignment demo on the same published course the assessments live on:
 * a 3×3 rubric, a module-attached case study (due soon, rubric-graded) and a standalone
 * reflection brief (past due, late allowed) — plus submissions in every state: graded
 * via the rubric, returned-for-resubmission with a new version waiting, and a LATE
 * hand-in in the queue. Idempotent: it rebuilds the course's assignments each run.
 */
class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->published()->has('modules')->with('modules')->first();

        if (! $course) {
            return;
        }

        $instructor = $course->creator ?? $course->instructors()->first() ?? User::role('instructor')->first();
        if (! $instructor) {
            return;
        }

        $module = $course->modules->first();

        // Idempotent reset of this course's assignment data (media rows cascade-delete
        // is DB-level for submissions; files on disk are demo-only).
        $course->assignments()->delete();
        Rubric::where('created_by', $instructor->id)->delete();

        // ------------------------------------------------------------------ rubric
        $rubric = Rubric::create(['name' => 'PR writing rubric', 'created_by' => $instructor->id]);
        $levels = fn (array $pts) => [
            ['label' => 'Excellent', 'description' => 'Publication-ready; anticipates the audience.', 'points' => $pts[0]],
            ['label' => 'Good', 'description' => 'Solid work with minor gaps.', 'points' => $pts[1]],
            ['label' => 'Needs work', 'description' => 'Key elements missing or unclear.', 'points' => $pts[2]],
        ];
        $rubric->criteria()->createMany([
            ['title' => 'Message clarity', 'description' => 'Is the core message unmistakable?', 'levels' => $levels([10, 7, 3]), 'position' => 0],
            ['title' => 'Audience fit', 'description' => 'Tone and channel match the audience.', 'levels' => $levels([10, 7, 3]), 'position' => 1],
            ['title' => 'Craft & accuracy', 'description' => 'Grammar, structure, sourcing.', 'levels' => $levels([10, 6, 2]), 'position' => 2],
        ]);

        // ------------------------------------------------------- assignment 1 (rubric)
        $caseStudy = $course->assignments()->create([
            'module_id' => $module->id,
            'created_by' => $instructor->id,
            'title' => 'Crisis communication case study',
            'slug' => 'crisis-communication-case-study',
            'instructions' => '<p>Analyse the press-conference transcript in the brief. In <strong>800–1200 words</strong>, assess what the organisation got right, what failed, and draft the holding statement you would have issued in the first hour.</p><p>Submit as typed text, a PDF, or both.</p>',
            'type' => AssignmentType::Either->value,
            'due_at' => now()->addDays(7)->setTime(23, 59),
            'allow_late' => false,
            'max_points' => 30,
            'rubric_id' => $rubric->id,
            'status' => AssignmentStatus::Published->value,
            'is_required' => true,
            'position' => 1,
        ]);

        // ------------------------------------------- assignment 2 (standalone, late OK)
        $reflection = $course->assignments()->create([
            'module_id' => null,
            'created_by' => $instructor->id,
            'title' => 'Personal reflection brief',
            'slug' => 'personal-reflection-brief',
            'instructions' => '<p>In about 500 words, reflect on how this course changed the way you would advise a client under media pressure. Be specific: name one habit you are keeping and one you are dropping.</p>',
            'type' => AssignmentType::Text->value,
            'due_at' => now()->subDays(2)->setTime(17, 0),
            'allow_late' => true,
            'max_points' => 20,
            'status' => AssignmentStatus::Published->value,
            'is_required' => false,
            'position' => 1,
        ]);

        // ------------------------------------------------------------- student work
        $students = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->with('user')
            ->take(3)
            ->get()
            ->pluck('user')
            ->filter()
            ->values();

        if ($students->isEmpty()) {
            return;
        }

        $criteria = $rubric->criteria()->get();

        // Student A: v1 graded via the rubric (7 + 10 + 6 = 23/30).
        if ($first = $students->get(0)) {
            $graded = $this->submission($caseStudy, $first, 1, SubmissionStatus::Graded, '<p>The organisation\'s first statement arrived four hours late and blamed the supplier…</p>');
            Grade::create([
                'submission_id' => $graded->id,
                'grader_id' => $instructor->id,
                'criterion_scores' => $criteria->values()->map(fn ($c, $i) => [
                    'criterion_id' => $c->id,
                    'criterion_title' => $c->title,
                    'level_index' => [1, 0, 1][$i],
                    'level_label' => $c->levels[[1, 0, 1][$i]]['label'],
                    'points' => (float) $c->levels[[1, 0, 1][$i]]['points'],
                    'max_points' => $c->maxPoints(),
                ])->all(),
                'points_total' => 23,
                'feedback' => '<p>Strong audience analysis. Your holding statement buries the apology — lead with it next time.</p>',
                'graded_at' => now()->subDay(),
            ]);
            app(LearningService::class)->recalculate($first, $course);
        }

        // Student B: v1 returned with a note, v2 resubmitted and waiting in the queue.
        if ($second = $students->get(1)) {
            $returned = $this->submission($caseStudy, $second, 1, SubmissionStatus::ReturnedForResubmission, '<p>First analysis draft…</p>', daysAgo: 3);
            $returned->forceFill([
                'return_note' => 'Good instincts, but the holding statement is missing entirely — please add it and resubmit.',
                'returned_by' => $instructor->id,
                'returned_at' => now()->subDays(2),
            ])->save();

            $this->submission($caseStudy, $second, 2, SubmissionStatus::Submitted, '<p>Revised draft, now with the first-hour holding statement…</p>', daysAgo: 1);
        }

        // Student C: a LATE reflection waiting to be graded.
        if ($third = $students->get(2)) {
            $late = $this->submission($reflection, $third, 1, SubmissionStatus::Submitted, '<p>The habit I am keeping is the one-page brief…</p>');
            $late->forceFill(['is_late' => true, 'submitted_at' => now()->subDay()])->save();
        }
    }

    private function submission(
        $assignment,
        User $user,
        int $version,
        SubmissionStatus $status,
        string $body,
        int $daysAgo = 2,
    ): Submission {
        return Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'version' => $version,
            'body' => $body,
            'submitted_at' => now()->subDays($daysAgo),
            'is_late' => false,
            'status' => $status->value,
        ]);
    }
}
