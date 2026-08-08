<?php

namespace App\Services\Courses;

use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\Courses\ProgressionVerdict;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Parses and validates an enrolment CSV (email,course_code) and, on confirmation,
 * performs the import. Validation is read-only and produces a per-row report flagging
 * each problem precisely; the import then enrols only the rows flagged OK.
 *
 * Used by both the synchronous path (≤100 rows, in the controller) and the queued
 * ProcessEnrollmentImport job (>100), so the rules live in exactly one place.
 */
class BulkEnrollmentService
{
    // Per-row problems, in the order they're checked. 'ok' means importable.
    public const OK = 'ok';

    public const UNKNOWN_EMAIL = 'unknown_email';

    public const UNKNOWN_CODE = 'unknown_code';

    public const DUPLICATE = 'duplicate';            // same pair earlier in the file

    public const ALREADY_ENROLLED = 'already_enrolled';

    public const EMPTY_ROW = 'empty';

    /**
     * Not a refusal — a WARNING. The row still imports (a bulk import is a staff action,
     * and the registrar admitting a cohort of transfer students is the normal reason to
     * reach for one), but naming it in the preview makes it a decision rather than an
     * accident. Every such row records a prerequisite override.
     */
    public const PREREQUISITE_NOT_MET = 'prerequisite_not_met';

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ProgressionService $progression,
    ) {}

    /**
     * Human label for a row problem (for the preview table).
     */
    public static function problemLabel(string $problem): string
    {
        return match ($problem) {
            self::OK => 'Ready to import',
            self::UNKNOWN_EMAIL => 'No account with this email',
            self::UNKNOWN_CODE => 'No course with this code',
            self::DUPLICATE => 'Duplicate row in this file',
            self::ALREADY_ENROLLED => 'Already enrolled',
            self::PREREQUISITE_NOT_MET => 'Prerequisite not met — will import as an override',
            self::EMPTY_ROW => 'Empty row',
            default => 'Problem',
        };
    }

    /**
     * Whether a flagged row still imports. Only the prerequisite warning does.
     */
    public static function isImportable(string $problem): bool
    {
        return $problem === self::OK || $problem === self::PREREQUISITE_NOT_MET;
    }

    /**
     * Validate raw CSV content into a structured report. Never writes anything.
     *
     * @return array{rows: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public function analyze(string $content): array
    {
        $records = $this->parse($content);

        // Resolve the lookups in two queries, not one-per-row.
        $emails = $records->pluck('email')->filter()->unique();
        $codes = $records->pluck('course_code')->filter()->unique();

        $usersByEmail = User::query()
            ->whereIn('email', $emails)
            ->get()
            ->keyBy(fn (User $u) => Str::lower($u->email));

        $coursesByCode = Course::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        // Existing live enrollments for the (user,course) pairs in this file.
        $userIds = $usersByEmail->pluck('id');
        $courseIds = $coursesByCode->pluck('id');
        $existing = Enrollment::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->filter(fn (Enrollment $e) => $e->status->isLive())
            ->map(fn (Enrollment $e) => $e->user_id.':'.$e->course_id)
            ->flip();

        // Progression verdicts for every (student, course) pair the file mentions,
        // resolved per student rather than per row so a 500-row import stays flat.
        $verdicts = $this->verdictsForFile($records, $usersByEmail, $coursesByCode);

        $seen = [];
        $rows = [];
        $counts = [self::OK => 0];

        foreach ($records as $record) {
            $email = $record['email'];
            $code = $record['course_code'];

            $user = $email !== '' ? $usersByEmail->get(Str::lower($email)) : null;
            $course = $code !== '' ? $coursesByCode->get($code) : null;

            $problem = $this->classify($email, $code, $user, $course, $existing, $seen, $verdicts);

            if ($user && $course) {
                $seen[$user->id.':'.$course->id] = true;
            }

            $counts[$problem] = ($counts[$problem] ?? 0) + 1;

            $rows[] = [
                'line' => $record['line'],
                'email' => $email,
                'course_code' => $code,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'course_id' => $course?->id,
                'course_title' => $course?->title,
                'problem' => $problem,
                'reason' => $problem === self::PREREQUISITE_NOT_MET
                    ? $verdicts->get($user->id.':'.$course->id)?->message()
                    : null,
            ];
        }

        $importable = ($counts[self::OK] ?? 0) + ($counts[self::PREREQUISITE_NOT_MET] ?? 0);

        return [
            'rows' => $rows,
            'counts' => [
                'total' => count($rows),
                // "valid" is what will actually be written, so the preview's headline
                // number never promises fewer enrolments than the import performs.
                'valid' => $importable,
                'invalid' => count($rows) - $importable,
            ] + $counts,
        ];
    }

    /**
     * Enrol every OK row from a fresh analysis of $content. Re-validates (state may
     * have changed since preview) and reports precisely what happened.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function import(string $content, User $actor): array
    {
        $report = $this->analyze($content);

        $imported = 0;
        $skipped = 0;

        $overridden = 0;

        foreach ($report['rows'] as $row) {
            if (! self::isImportable($row['problem'])) {
                $skipped++;

                continue;
            }

            $student = User::find($row['user_id']);
            $course = Course::find($row['course_id']);

            if (! $student || ! $course) {
                $skipped++;

                continue;
            }

            try {
                // The prerequisite gate is overridden for EVERY row, not only the ones the
                // preview flagged: state can move between preview and import, and a bulk
                // import that half-enforced would produce a result nobody could predict
                // from the screen they approved. Each override is recorded per enrolment.
                $this->enrollments->adminEnroll(
                    $student,
                    $course,
                    $actor,
                    EnrollmentSource::Bulk,
                    overridePrerequisites: true,
                    overrideReason: 'Bulk enrolment import by '.$actor->name,
                );
                $imported++;

                if ($row['problem'] === self::PREREQUISITE_NOT_MET) {
                    $overridden++;
                }
            } catch (\Throwable) {
                // A race (e.g. enrolled between preview and import) — count, don't fail.
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'overridden' => $overridden,
            'total' => $report['counts']['total'],
        ];
    }

    /**
     * The downloadable template — a header plus two illustrative rows.
     */
    public function template(): string
    {
        return "email,course_code\nstudent1@uprl.test,PRL101\nstudent2@uprl.test,LDS201\n";
    }

    /**
     * Decide the single most relevant problem for a row, in priority order.
     *
     * @param  Collection<string, int>  $existing
     * @param  array<string, bool>  $seen
     * @param  Collection<string, ProgressionVerdict>  $verdicts
     */
    private function classify(string $email, string $code, ?User $user, ?Course $course, Collection $existing, array $seen, Collection $verdicts): string
    {
        if ($email === '' && $code === '') {
            return self::EMPTY_ROW;
        }
        if (! $user) {
            return self::UNKNOWN_EMAIL;
        }
        if (! $course) {
            return self::UNKNOWN_CODE;
        }

        $key = $user->id.':'.$course->id;

        if (isset($seen[$key])) {
            return self::DUPLICATE;
        }
        if ($existing->has($key)) {
            return self::ALREADY_ENROLLED;
        }
        // Last, because it is the only flag that does not stop the row: a genuine problem
        // above should be reported instead of a warning about a row that will not import.
        if ($verdicts->get($key)?->isBlocked()) {
            return self::PREREQUISITE_NOT_MET;
        }

        return self::OK;
    }

    /**
     * Progression verdicts for every (student, course) pair the file names, keyed
     * "userId:courseId". One ProgressionService pass per DISTINCT STUDENT rather than per
     * row — a file listing 40 courses for the same student costs one pass, not 40.
     *
     * @param  Collection<int, array{email: string, course_code: string}>  $records
     * @param  Collection<string, User>  $usersByEmail
     * @param  Collection<string, Course>  $coursesByCode
     * @return Collection<string, ProgressionVerdict>
     */
    private function verdictsForFile(Collection $records, Collection $usersByEmail, Collection $coursesByCode): Collection
    {
        $wanted = $records
            ->map(fn (array $r) => [
                'user' => $r['email'] !== '' ? $usersByEmail->get(Str::lower($r['email'])) : null,
                'course' => $r['course_code'] !== '' ? $coursesByCode->get($r['course_code']) : null,
            ])
            ->filter(fn (array $pair) => $pair['user'] && $pair['course']);

        $verdicts = collect();

        foreach ($wanted->groupBy(fn (array $pair) => $pair['user']->id) as $pairs) {
            $student = $pairs->first()['user'];
            $courses = $pairs->pluck('course')->unique('id')->values();

            foreach ($this->progression->verdictsFor($student, $courses) as $courseId => $verdict) {
                $verdicts->put($student->id.':'.$courseId, $verdict);
            }
        }

        return $verdicts;
    }

    /**
     * Split CSV text into [line, email, course_code] records, skipping a header row
     * and blank lines. Tolerant of quoting and stray whitespace.
     *
     * @return Collection<int, array{line: int, email: string, course_code: string}>
     */
    private function parse(string $content): Collection
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        $records = collect();

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);
            $email = isset($cells[0]) ? trim((string) $cells[0]) : '';
            $code = isset($cells[1]) ? trim((string) $cells[1]) : '';

            // Skip an obvious header row.
            if ($index === 0 && Str::lower($email) === 'email' && Str::lower($code) === 'course_code') {
                continue;
            }

            $records->push([
                'line' => $index + 1,
                'email' => $email,
                'course_code' => Str::upper($code),
            ]);
        }

        return $records->values();
    }
}
