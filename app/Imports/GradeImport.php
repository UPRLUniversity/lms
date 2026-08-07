<?php

namespace App\Imports;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Services\Assignments\AssignmentGradingService;
use App\Support\Import\ImportColumn;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportFormatException;
use App\Support\Import\ImportRow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Upload marks for one assignment — the case where grading happened on paper, in an
 * exam hall, or in a spreadsheet the external examiner sent back.
 *
 * Scoped to a single assignment rather than a whole course, because a marks sheet is
 * always ABOUT one piece of work, and a course-wide sheet would need a column naming
 * the assignment on every row — more to get wrong, for nothing gained.
 *
 * Rubric-graded assignments are refused outright. A rubric's score is derived from the
 * level chosen against each criterion, and accepting a bare total would produce a grade
 * whose criterion breakdown contradicts it — the student would open feedback showing
 * levels that do not add up to their mark.
 */
class GradeImport implements ImportDefinition
{
    public const UNKNOWN_STUDENT = 'unknown_student';

    public const NO_SUBMISSION = 'no_submission';

    public const BAD_SCORE = 'bad_score';

    public const OVER_MAX = 'over_max';

    public const DUPLICATE = 'duplicate';

    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private readonly AssignmentGradingService $grading) {}

    public function key(): string
    {
        return 'grades';
    }

    public function title(): string
    {
        return 'Upload marks';
    }

    public function intro(): string
    {
        return 'Record marks for this assignment from a spreadsheet. Each student is notified exactly as if you had graded them by hand.';
    }

    public function noun(): string
    {
        return 'mark';
    }

    public function columns(): array
    {
        return [
            ImportColumn::required('email', 'Student email', 'Must match the account they submitted with.'),
            ImportColumn::required('score', 'Score', 'Out of the assignment\'s maximum points.'),
            ImportColumn::make('feedback', 'Feedback', hint: 'Shown to the student with their mark. Optional.'),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['email' => 'student1@uprl.test', 'score' => '72', 'feedback' => 'Strong analysis of the stakeholder map; tighten the recommendations.'],
            ['email' => 'student2@uprl.test', 'score' => '65', 'feedback' => ''],
        ];
    }

    public function authorize(User $user, ?Model $scope): bool
    {
        return $scope instanceof Assignment && $user->can('grade', $scope);
    }

    public function prepare(Collection $rows, ?Model $scope, User $actor): array
    {
        /** @var Assignment $assignment */
        $assignment = $scope;

        // Refused at the file level, not per row: there is no correct way to import a
        // bare total against a rubric (see the class docblock), so the human needs to
        // know before they look at a preview of rows that can never be imported.
        if ($assignment->rubric_id && $assignment->rubric?->criteria()->exists()) {
            throw new ImportFormatException(
                'This assignment is graded against a rubric, so marks cannot be uploaded from a '
                .'spreadsheet — a total on its own would contradict the criterion scores the '
                .'student sees. Grade it in the grading workspace, or detach the rubric first.'
            );
        }

        if ((float) ($assignment->max_points ?? 0) <= 0) {
            throw new ImportFormatException(
                'This assignment has no maximum points set, so there is nothing to mark against. '
                .'Set its maximum points first.'
            );
        }

        $emails = $rows
            ->map(fn (ImportRow $r) => Str::lower($r->get('email')))
            ->filter()
            ->unique();

        $users = User::query()
            ->whereIn('email', $emails)
            ->get()
            ->keyBy(fn (User $u) => Str::lower($u->email));

        // The latest submission version per student, in one query rather than one per
        // row — a resubmission creates a NEW row, so "latest" is what gets graded.
        $submissions = Submission::query()
            ->where('assignment_id', $assignment->id)
            ->whereIn('user_id', $users->pluck('id'))
            ->orderBy('version')
            ->get()
            ->keyBy('user_id');

        $this->seen = [];

        return [
            'users' => $users,
            'submissions' => $submissions,
            'max' => (float) ($assignment->max_points ?? 0),
        ];
    }

    public function inspect(ImportRow $row, array $context, ?Model $scope): void
    {
        if ($row->isBlank()) {
            $row->fail(ImportRow::EMPTY_ROW);

            return;
        }

        $email = Str::lower($row->get('email'));
        $user = $context['users']->get($email);

        if (! $user) {
            $row->fail(self::UNKNOWN_STUDENT);

            return;
        }

        $row->resolve(['student' => $user->name]);

        if (isset($this->seen[$email])) {
            $row->fail(self::DUPLICATE);

            return;
        }
        $this->seen[$email] = true;

        $submission = $context['submissions']->get($user->id);

        if (! $submission) {
            $row->fail(self::NO_SUBMISSION);

            return;
        }

        $score = $row->get('score');

        if (! is_numeric($score) || (float) $score < 0) {
            $row->fail(self::BAD_SCORE);

            return;
        }

        if ($context['max'] > 0 && (float) $score > $context['max']) {
            // Refused rather than silently clamped: a mark of 95 against a maximum of 50
            // means the sheet is out of step with the assignment, and quietly recording
            // 50 would hide that from whoever has to answer for the grade.
            $row->fail(self::OVER_MAX, ['max' => $context['max']]);

            return;
        }

        $row->resolve(['score' => $score.' / '.(int) $context['max']]);
    }

    public function apply(ImportRow $row, array $context, ?Model $scope, User $actor): bool
    {
        $user = $context['users']->get(Str::lower($row->get('email')));
        $submission = $user ? $context['submissions']->get($user->id) : null;

        if (! $submission) {
            return false;
        }

        // Through the same service the grading workspace uses, so the submission's
        // status, the student's progress and the graded notification all happen exactly
        // as they would for a hand-marked hand-in.
        $this->grading->grade($submission, $actor, [
            'points' => (float) $row->get('score'),
            'feedback' => $row->get('feedback') ?: null,
        ]);

        return true;
    }

    public function problemLabel(string $problem): ?string
    {
        return match ($problem) {
            self::UNKNOWN_STUDENT => 'No account with this email',
            self::NO_SUBMISSION => 'This student has not submitted',
            self::BAD_SCORE => 'Score must be a number of zero or more',
            self::OVER_MAX => 'Score is above the assignment maximum',
            self::DUPLICATE => 'Duplicate student earlier in this file',
            default => null,
        };
    }

    public function returnRoute(?Model $scope): array
    {
        return ['grading.assignments.index', []];
    }
}
