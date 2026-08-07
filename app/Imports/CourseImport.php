<?php

namespace App\Imports;

use App\Enums\CourseLevel;
use App\Enums\CourseRequirement;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Support\Import\ImportColumn;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportRow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Load a programme's course list straight from the printed prospectus — the one job in
 * this system that is otherwise hours of identical form-filling (CPR Part I alone is
 * eleven papers).
 *
 * Courses are created as DRAFT, always. A spreadsheet is a planning document, not an
 * editorial decision: nothing a bulk import creates should become visible to students
 * until somebody has opened it and published it deliberately. This is the same reason
 * the catalogue filters on Course::inCatalogue().
 *
 * A row may also PLACE the course in a programme part with its credit load and
 * compulsory/elective status, which is where the real tedium lives.
 */
class CourseImport implements ImportDefinition
{
    public const NO_CODE = 'no_code';

    public const NO_TITLE = 'no_title';

    public const EXISTS = 'exists';

    public const DUPLICATE = 'duplicate';

    public const UNKNOWN_PROGRAMME = 'unknown_programme';

    public const UNKNOWN_PART = 'unknown_part';

    public const UNKNOWN_DEPARTMENT = 'unknown_department';

    public const BAD_CREDITS = 'bad_credits';

    /** @var array<string, true> */
    private array $seenCodes = [];

    public function key(): string
    {
        return 'courses';
    }

    public function title(): string
    {
        return 'Import courses';
    }

    public function intro(): string
    {
        return 'Create a programme\'s course list from the prospectus. Every course arrives as a draft for you to flesh out and publish.';
    }

    public function noun(): string
    {
        return 'course';
    }

    public function columns(): array
    {
        return [
            ImportColumn::required('code', 'Code', 'e.g. CPR112. Must be unique — an existing code is skipped, never overwritten.'),
            ImportColumn::required('title', 'Title'),
            ImportColumn::make('credits', 'Credits', hint: 'Credit load for this placement, e.g. 3.'),
            ImportColumn::make('programme', 'Programme', hint: 'Programme name or code. Leave blank to create the course unplaced.'),
            ImportColumn::make('part', 'Part', hint: 'Part name within that programme, e.g. "Part I".'),
            ImportColumn::make('requirement', 'Requirement', hint: 'compulsory, required_elective, elective or gns. Defaults to compulsory.'),
            ImportColumn::make('level', 'Level', hint: 'certificate, undergraduate, postgraduate or professional. Defaults to certificate.'),
            ImportColumn::make('department', 'Department', hint: 'Department name. Optional.'),
            ImportColumn::make('summary', 'Summary', hint: 'One-line description for the catalogue card. Optional.'),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['code' => 'CPR112', 'title' => 'Principles of Public Relations', 'credits' => '3', 'programme' => 'CPR', 'part' => 'Part I', 'requirement' => 'compulsory', 'level' => 'certificate', 'department' => '', 'summary' => 'The foundations of PR practice, publics and purpose.'],
            ['code' => 'CPR115', 'title' => 'PR Media and Methods', 'credits' => '3', 'programme' => 'CPR', 'part' => 'Part I', 'requirement' => 'compulsory', 'level' => 'certificate', 'department' => '', 'summary' => ''],
            ['code' => 'CPR219', 'title' => 'Crisis Communication', 'credits' => '2', 'programme' => 'CPR', 'part' => 'Part II', 'requirement' => 'elective', 'level' => 'certificate', 'department' => '', 'summary' => ''],
        ];
    }

    public function authorize(User $user, ?Model $scope): bool
    {
        return $user->can('create', Course::class);
    }

    public function prepare(Collection $rows, ?Model $scope, User $actor): array
    {
        $codes = $rows->map(fn (ImportRow $r) => Str::upper($r->get('code')))->filter()->unique();

        $this->seenCodes = [];

        return [
            'existingCodes' => Course::query()
                ->whereIn('code', $codes)
                ->pluck('code')
                ->map(fn (string $c) => Str::upper($c))
                ->flip(),

            // Programmes keyed by BOTH name and code, lowercased, so the sheet may say
            // either "CPR" or "Certificate in Public Relations".
            'programmes' => Programme::query()->with('parts')->get(),

            'departments' => Department::query()
                ->get()
                ->keyBy(fn (Department $d) => Str::lower($d->name)),
        ];
    }

    public function inspect(ImportRow $row, array $context, ?Model $scope): void
    {
        if ($row->isBlank()) {
            $row->fail(ImportRow::EMPTY_ROW);

            return;
        }

        $code = Str::upper($row->get('code'));

        if ($code === '') {
            $row->fail(self::NO_CODE);

            return;
        }

        if ($row->get('title') === '') {
            $row->fail(self::NO_TITLE);

            return;
        }

        if ($context['existingCodes']->has($code)) {
            $row->fail(self::EXISTS);

            return;
        }

        if (isset($this->seenCodes[$code])) {
            $row->fail(self::DUPLICATE);

            return;
        }
        $this->seenCodes[$code] = true;

        if ($row->has('credits') && ! $this->isPositiveNumber($row->get('credits'))) {
            $row->fail(self::BAD_CREDITS);

            return;
        }

        if ($row->has('department') && ! $this->department($row, $context)) {
            $row->fail(self::UNKNOWN_DEPARTMENT);

            return;
        }

        // Placement is optional, but a HALF-specified placement is an error worth
        // surfacing: naming a programme whose part doesn't exist means the human
        // mistyped, and silently creating the course unplaced would hide that.
        if ($row->has('programme')) {
            $programme = $this->programme($row, $context);

            if (! $programme) {
                $row->fail(self::UNKNOWN_PROGRAMME);

                return;
            }

            $row->resolve(['programme' => $programme->name]);

            if ($row->has('part')) {
                $part = $this->part($programme, $row->get('part'));

                if (! $part) {
                    $row->fail(self::UNKNOWN_PART);

                    return;
                }

                $row->resolve(['part' => $part->name]);
            }
        }
    }

    public function apply(ImportRow $row, array $context, ?Model $scope, User $actor): bool
    {
        $code = Str::upper($row->get('code'));
        $title = $row->get('title');

        $course = Course::create([
            'code' => $code,
            'title' => $title,
            'slug' => $this->uniqueSlug($title, $code),
            'summary' => $row->get('summary') ?: null,
            'level' => $this->level($row->get('level'))->value,
            'department_id' => $this->department($row, $context)?->id,
            'created_by' => $actor->id,
            // Draft, always — see the class docblock.
            'status' => CourseStatus::Draft->value,
        ]);

        $programme = $row->has('programme') ? $this->programme($row, $context) : null;
        $part = $programme && $row->has('part') ? $this->part($programme, $row->get('part')) : null;

        if ($part) {
            // Through the canonical writer, not a raw attach: it is what enforces
            // "exactly one primary placement per course", which decides the course's
            // inherited price. A bulk import must not be the one path that skips it.
            $course->syncProgrammePlacements([[
                'programme_part_id' => $part->id,
                'credit_load' => $row->has('credits') ? (int) $row->get('credits') : null,
                'requirement' => $this->requirement($row->get('requirement'))->value,
                'is_primary' => true,
            ]]);
        }

        return true;
    }

    public function problemLabel(string $problem): ?string
    {
        return match ($problem) {
            self::NO_CODE => 'No course code',
            self::NO_TITLE => 'No title',
            self::EXISTS => 'A course with this code already exists',
            self::DUPLICATE => 'Duplicate code earlier in this file',
            self::UNKNOWN_PROGRAMME => 'No programme with that name or code',
            self::UNKNOWN_PART => 'That programme has no part with that name',
            self::UNKNOWN_DEPARTMENT => 'No department with that name',
            self::BAD_CREDITS => 'Credits must be a positive number',
            default => null,
        };
    }

    public function returnRoute(?Model $scope): array
    {
        return ['courses.index', []];
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    */

    private function programme(ImportRow $row, array $context): ?Programme
    {
        $needle = Str::lower(trim($row->get('programme')));

        /** @var Collection<int, Programme> $programmes */
        $programmes = $context['programmes'];

        return $programmes->first(fn (Programme $p) => Str::lower($p->name) === $needle
            || Str::lower((string) $p->code) === $needle);
    }

    private function part(Programme $programme, string $name): ?ProgrammePart
    {
        $needle = $this->normalisePart($name);

        return $programme->parts->first(fn (ProgrammePart $p) => $this->normalisePart($p->name) === $needle);
    }

    /**
     * "Part I", "part 1", "PART-I" and "I" all name the same part. Roman numerals are
     * folded to digits because the prospectus mixes both ("CPR Part I", "NPV Part 1").
     */
    private function normalisePart(string $value): string
    {
        $value = Str::lower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
        $value = preg_replace('/^part/', '', $value) ?? $value;

        return match ($value) {
            'i', '1' => '1',
            'ii', '2' => '2',
            'iii', '3' => '3',
            'iv', '4' => '4',
            default => $value,
        };
    }

    private function department(ImportRow $row, array $context): ?Department
    {
        if (! $row->has('department')) {
            return null;
        }

        return $context['departments']->get(Str::lower(trim($row->get('department'))));
    }

    /**
     * Certificate by default: UPRL's catalogue is overwhelmingly certificate-level, and
     * courses.level has no database default, so something has to be chosen.
     */
    private function level(string $value): CourseLevel
    {
        return CourseLevel::tryFrom(Str::lower(trim($value))) ?? CourseLevel::Certificate;
    }

    private function requirement(string $value): CourseRequirement
    {
        $key = preg_replace('/[^a-z]/', '', Str::lower($value)) ?? '';

        return match ($key) {
            'elective', 'optional' => CourseRequirement::Elective,
            'requiredelective', 'required' => CourseRequirement::RequiredElective,
            'gns', 'general', 'generalstudies' => CourseRequirement::Gns,
            default => CourseRequirement::Compulsory,
        };
    }

    /**
     * Slugs are public URLs and must be unique. The code is a natural disambiguator,
     * and it is already unique by the time apply() runs.
     */
    private function uniqueSlug(string $title, string $code): string
    {
        $base = Str::slug($title);

        if ($base === '' || Course::where('slug', $base)->exists()) {
            return Str::slug($title.' '.$code) ?: Str::lower($code);
        }

        return $base;
    }

    private function isPositiveNumber(string $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }
}
