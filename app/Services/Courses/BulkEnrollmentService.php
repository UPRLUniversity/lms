<?php

namespace App\Services\Courses;

use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
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

    public function __construct(private readonly EnrollmentService $enrollments) {}

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
            self::EMPTY_ROW => 'Empty row',
            default => 'Problem',
        };
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

        $seen = [];
        $rows = [];
        $counts = [self::OK => 0];

        foreach ($records as $record) {
            $email = $record['email'];
            $code = $record['course_code'];

            $user = $email !== '' ? $usersByEmail->get(Str::lower($email)) : null;
            $course = $code !== '' ? $coursesByCode->get($code) : null;

            $problem = $this->classify($email, $code, $user, $course, $existing, $seen);

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
            ];
        }

        return [
            'rows' => $rows,
            'counts' => [
                'total' => count($rows),
                'valid' => $counts[self::OK] ?? 0,
                'invalid' => count($rows) - ($counts[self::OK] ?? 0),
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

        foreach ($report['rows'] as $row) {
            if ($row['problem'] !== self::OK) {
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
                $this->enrollments->adminEnroll($student, $course, $actor, EnrollmentSource::Bulk);
                $imported++;
            } catch (\Throwable) {
                // A race (e.g. enrolled between preview and import) — count, don't fail.
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
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
     */
    private function classify(string $email, string $code, ?User $user, ?Course $course, Collection $existing, array $seen): string
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

        return self::OK;
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
