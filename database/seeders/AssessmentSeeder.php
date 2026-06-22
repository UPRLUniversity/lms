<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\ReviewPolicy;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Services\Assessments\AttemptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A clickable assessment demo on one published course: a 26-question bank across every
 * type, a pre/post-module pair, a timed standalone exam, a pooled exam and an essay
 * assignment — plus a spread of attempts (graded, pooled, a pre→post gain, and one essay
 * awaiting grading). Idempotent: it rebuilds the chosen course's assessments each run.
 */
class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->published()->has('modules')->with('modules')->first();

        if (! $course) {
            return;
        }

        $instructor = $course->creator ?? $course->instructors()->first() ?? User::role('instructor')->first();
        $module = $course->modules->first();

        // Idempotent reset of this course's assessment data.
        $course->assessments()->delete();
        $course->questions()->forceDelete();
        $course->questionCategories()->delete();

        $fundamentals = $course->questionCategories()->create(['name' => 'Fundamentals', 'created_by' => $instructor?->id]);
        $application = $course->questionCategories()->create(['name' => 'Application', 'created_by' => $instructor?->id]);
        $advanced = $course->questionCategories()->create(['name' => 'Advanced', 'created_by' => $instructor?->id]);

        $bank = $this->seedBank($course, $instructor, [$fundamentals, $application, $advanced]);

        // Pre/post-module pair (fixed, 4 objective questions each).
        $pre = $this->assessment($course, $instructor, 'Module diagnostic (pre)', [
            'placement' => 'pre_module', 'module_id' => $module->id, 'passing_score' => 50,
        ]);
        $post = $this->assessment($course, $instructor, 'Module check (post)', [
            'placement' => 'post_module', 'module_id' => $module->id, 'passing_score' => 50,
        ]);
        $objective = $bank->filter(fn ($q) => in_array($q->type->value, ['mcq_single', 'true_false', 'fill_blank'], true))->take(4)->values();
        foreach ([$pre, $post] as $a) {
            $a->questions()->sync($objective->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i]])->all());
        }

        // Timed standalone exam — 2 attempts, shuffled, review after submitting.
        $exam = $this->assessment($course, $instructor, 'Final exam', [
            'placement' => 'standalone', 'passing_score' => 60, 'time_limit_minutes' => 20,
            'max_attempts' => 2, 'shuffle_questions' => true, 'shuffle_options' => true,
            'review_policy' => ReviewPolicy::Immediately->value,
        ]);
        $examQuestions = $bank->filter(fn ($q) => in_array($q->type->value, ['mcq_single', 'mcq_multi', 'true_false', 'matching'], true))->take(6)->values();
        $exam->questions()->sync($examQuestions->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i]])->all());

        // Pooled practice exam.
        $pool = $this->assessment($course, $instructor, 'Practice pool', [
            'placement' => 'standalone', 'selection_mode' => 'pooled', 'passing_score' => 50, 'is_required' => false,
        ]);
        $pool->poolRules()->create(['category_id' => $fundamentals->id, 'difficulty' => null, 'count' => 5]);

        // Essay assignment (for the grading-queue demo).
        $assignment = $this->assessment($course, $instructor, 'Reflection essay', [
            'placement' => 'standalone', 'passing_score' => 50,
        ]);
        $essay = $bank->first(fn ($q) => $q->type->value === 'essay');
        $assignment->questions()->sync([$essay->id => ['position' => 0]]);

        $this->seedAttempts($course, $pre, $post, $exam, $assignment);
    }

    /**
     * @param  array<int, QuestionCategory>  $categories
     * @return Collection<int, Question>
     */
    private function seedBank(Course $course, ?User $instructor, array $categories): Collection
    {
        [$fundamentals, $application, $advanced] = $categories;
        $bank = collect();

        // Hand-authored, public-relations-flavoured questions so every type renders with real
        // content; padded with factory questions for volume so the pool/filters have depth.
        foreach ($this->authored() as $row) {
            $cat = ['fundamentals' => $fundamentals, 'application' => $application, 'advanced' => $advanced][$row['cat']];
            $bank->push(Question::factory()->{$row['state']}(...($row['args'] ?? []))
                ->difficulty($row['difficulty'])
                ->create(array_merge([
                    'course_id' => $course->id,
                    'category_id' => $cat->id,
                    'created_by' => $instructor?->id,
                    'prompt' => '<p>'.$row['prompt'].'</p>',
                ], $row['overrides'] ?? [])));
        }

        // Volume padding so "Fundamentals" easily satisfies a 5-question pool and the bank
        // table/filters have something to page through.
        $difficulties = [QuestionDifficulty::Easy, QuestionDifficulty::Medium, QuestionDifficulty::Hard];
        $pad = function (string $state, QuestionCategory $cat, int $n) use ($course, $instructor, &$bank, $difficulties) {
            for ($i = 0; $i < $n; $i++) {
                $bank->push(Question::factory()->{$state}()->difficulty($difficulties[$i % 3])->create([
                    'course_id' => $course->id, 'category_id' => $cat->id, 'created_by' => $instructor?->id,
                ]));
            }
        };
        $pad('mcqSingle', $fundamentals, 6);
        $pad('trueFalse', $fundamentals, 3);
        $pad('mcqMulti', $application, 3);
        $pad('fillBlank', $application, 3);
        $pad('mcqSingle', $advanced, 3);
        $pad('matching', $advanced, 2);

        return $bank; // 30+ questions across all 7 types, 3 categories, 3 difficulties
    }

    /**
     * A small set of realistic, public-relations questions — one or more of every type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function authored(): array
    {
        return [
            ['cat' => 'fundamentals', 'state' => 'mcqSingle', 'difficulty' => QuestionDifficulty::Easy,
                'prompt' => 'Which document is issued first during a breaking crisis, before all facts are known?',
                'overrides' => ['payload' => ['options' => [
                    ['id' => 'o1', 'text' => 'A holding statement', 'is_correct' => true],
                    ['id' => 'o2', 'text' => 'The annual report', 'is_correct' => false],
                    ['id' => 'o3', 'text' => 'A media kit', 'is_correct' => false],
                    ['id' => 'o4', 'text' => 'A fact sheet', 'is_correct' => false],
                ]]]],
            ['cat' => 'fundamentals', 'state' => 'trueFalse', 'difficulty' => QuestionDifficulty::Easy, 'args' => [true],
                'prompt' => 'The "inverted pyramid" places the most important information at the top of a news release.'],
            ['cat' => 'fundamentals', 'state' => 'fillBlank', 'difficulty' => QuestionDifficulty::Medium,
                'args' => [['earned media', 'earned'], true],
                'prompt' => 'Coverage a brand gains through PR rather than paid placement is called ______ media.'],
            ['cat' => 'fundamentals', 'state' => 'fillBlank', 'difficulty' => QuestionDifficulty::Easy,
                'args' => [['embargo'], true],
                'prompt' => 'A request that journalists not publish information before a set time is called an ______.'],
            ['cat' => 'application', 'state' => 'mcqMulti', 'difficulty' => QuestionDifficulty::Medium,
                'prompt' => 'Which of the following belong in a SMART campaign objective? (Select all that apply.)',
                'overrides' => ['payload' => ['options' => [
                    ['id' => 'o1', 'text' => 'Measurable', 'is_correct' => true],
                    ['id' => 'o2', 'text' => 'Time-bound', 'is_correct' => true],
                    ['id' => 'o3', 'text' => 'Vague', 'is_correct' => false],
                    ['id' => 'o4', 'text' => 'Achievable', 'is_correct' => true],
                ]]]],
            ['cat' => 'application', 'state' => 'matching', 'difficulty' => QuestionDifficulty::Medium,
                'prompt' => 'Match each PR channel to what it is best suited for.',
                'overrides' => ['points' => 4, 'payload' => ['pairs' => [
                    ['id' => 'p1', 'left' => 'Press release', 'right' => 'Announcing news to journalists'],
                    ['id' => 'p2', 'left' => 'Media interview', 'right' => 'Adding a human, expert voice'],
                    ['id' => 'p3', 'left' => 'Social media', 'right' => 'Real-time two-way engagement'],
                    ['id' => 'p4', 'left' => 'Newsletter', 'right' => 'Nurturing an owned audience'],
                ]]]],
            ['cat' => 'application', 'state' => 'scenario', 'difficulty' => QuestionDifficulty::Hard, 'args' => [false],
                'prompt' => 'A food brand discovers a labelling error after products have shipped. Work through the response.'],
            ['cat' => 'advanced', 'state' => 'essay', 'difficulty' => QuestionDifficulty::Hard,
                'prompt' => 'Outline a stakeholder-communication plan for a university announcing a controversial fee change. Justify your channel choices.',
                'overrides' => ['points' => 15, 'payload' => ['guidance' => 'Look for stakeholder mapping, message tailoring, channel rationale, and an evaluation measure.']]],
            ['cat' => 'advanced', 'state' => 'essay', 'difficulty' => QuestionDifficulty::Medium,
                'prompt' => 'Explain the difference between outputs, outtakes and outcomes when evaluating a PR campaign.',
                'overrides' => ['points' => 10, 'payload' => ['guidance' => 'Reward clear definitions and a worked example of each.']]],
            ['cat' => 'advanced', 'state' => 'scenario', 'difficulty' => QuestionDifficulty::Hard, 'args' => [true],
                'prompt' => 'A non-profit faces a viral allegation on social media. Triage the first hour.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function assessment(Course $course, ?User $instructor, string $title, array $attrs): Assessment
    {
        return $course->assessments()->create(array_merge([
            'created_by' => $instructor?->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'published',
            'selection_mode' => 'fixed',
        ], $attrs));
    }

    private function seedAttempts(Course $course, Assessment $pre, Assessment $post, Assessment $exam, Assessment $assignment): void
    {
        $service = app(AttemptService::class);

        $students = User::role('student')->take(4)->get();
        if ($students->count() < 4) {
            return;
        }

        foreach ($students as $student) {
            Enrollment::firstOrCreate(
                ['user_id' => $student->id, 'course_id' => $course->id],
                ['status' => EnrollmentStatus::Active->value, 'enrolled_at' => now(), 'progress_percent' => 0],
            );
        }

        // Student 1: a pre→post knowledge gain (50% → 90%).
        $this->fakeGraded($pre, $students[0], 50);
        $this->fakeGraded($post, $students[0], 90);

        // Students 2 & 3: real graded attempts at the exam (auto-graded objective).
        foreach ([$students[1], $students[2]] as $student) {
            $attempt = $service->startAttempt($exam, $student);
            foreach ($attempt->layoutQuestions() as $row) {
                $q = Question::find($row['id']);
                $service->saveAnswer($attempt, $q->id, $this->plausibleAnswer($q, $attempt->layoutFor($q->id) ?? []));
            }
            $service->submit($attempt);
        }

        // Student 4: an essay attempt left awaiting grading.
        $attempt = $service->startAttempt($assignment, $students[3]);
        foreach ($attempt->questionIds() as $qid) {
            $service->saveAnswer($attempt, $qid, 'Public relations builds trust through consistent, honest communication.');
        }
        $service->submit($attempt); // stays 'submitted' until the instructor grades it
    }

    private function fakeGraded(Assessment $assessment, User $student, int $pct): void
    {
        $assessment->attempts()->create([
            'user_id' => $student->id,
            'attempt_number' => 1,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'score' => $pct,
            'max_score' => 100,
            'percentage' => $pct,
            'passed' => $pct >= $assessment->passing_score,
            'status' => 'graded',
            'layout' => ['questions' => []],
        ]);
    }

    /**
     * A roughly-correct answer for a question, for realistic seeded attempts.
     *
     * @param  array<string, mixed>  $layoutRow
     */
    private function plausibleAnswer(Question $question, array $layoutRow): mixed
    {
        return match ($question->type->value) {
            'mcq_single', 'true_false' => $question->correctOptionIds()[0] ?? null,
            'mcq_multi' => $question->correctOptionIds(),
            'fill_blank' => $question->acceptedAnswers()[0] ?? '',
            'matching' => collect($layoutRow['right_tokens'] ?? [])
                ->mapWithKeys(fn ($t) => [$t['pair_id'] => $t['token']])->all(),
            default => null,
        };
    }
}
