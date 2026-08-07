<?php

namespace App\Imports;

use App\Enums\Role;
use App\Models\User;
use App\Support\Import\ImportColumn;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportRow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Register many people at once — a matriculating cohort, a department's teaching staff.
 *
 * No password column, by design. A spreadsheet of passwords is a spreadsheet that gets
 * emailed, printed and left on a desk; instead each new account is created with an
 * unguessable random password and a set-password link is emailed, which is the same
 * path the invitation flow already uses. The sheet therefore never holds a credential.
 *
 * The privilege-escalation guard from StoreUserRequest applies per row: an admin
 * importing a file that tries to mint super-admins gets those rows refused, not obeyed.
 */
class UserImport implements ImportDefinition
{
    public const BAD_EMAIL = 'bad_email';

    public const NO_NAME = 'no_name';

    public const EXISTS = 'exists';

    public const DUPLICATE = 'duplicate';

    public const UNKNOWN_ROLE = 'unknown_role';

    public const FORBIDDEN_ROLE = 'forbidden_role';

    /**
     * Emails already claimed by an earlier row of the CURRENT file. Instance state reset
     * in prepare() rather than a static, so a second import in the same worker process
     * does not inherit the first one's emails and reject every row as a duplicate.
     *
     * @var array<string, true>
     */
    private array $seen = [];

    public function key(): string
    {
        return 'users';
    }

    public function title(): string
    {
        return 'Register people in bulk';
    }

    public function intro(): string
    {
        return 'Create many accounts at once. Each person is emailed a link to set their own password — no passwords go in the file.';
    }

    public function noun(): string
    {
        return 'account';
    }

    public function columns(): array
    {
        return [
            ImportColumn::required('name', 'Full name'),
            ImportColumn::required('email', 'Email', 'Must be unique. An existing account is skipped, never overwritten.'),
            ImportColumn::required('role', 'Role', 'student, instructor, admin, auditor. Only a super-admin may grant admin roles.'),
            ImportColumn::make('title', 'Title', hint: 'Dr, Prof, Mrs… Optional.'),
            ImportColumn::make('phone', 'Phone', hint: 'Optional.'),
        ];
    }

    public function sampleRows(): array
    {
        return [
            ['name' => 'Chinelo Okafor', 'email' => 'chinelo.okafor@example.com', 'role' => 'student', 'title' => '', 'phone' => '08031234567'],
            ['name' => 'Adaeze Nwosu', 'email' => 'adaeze.nwosu@example.com', 'role' => 'instructor', 'title' => 'Prof', 'phone' => ''],
            ['name' => 'Bello Sanusi', 'email' => 'bello.sanusi@example.com', 'role' => 'student', 'title' => '', 'phone' => ''],
        ];
    }

    public function authorize(User $user, ?Model $scope): bool
    {
        return $user->can('create', User::class);
    }

    public function prepare(Collection $rows, ?Model $scope, User $actor): array
    {
        $emails = $rows
            ->map(fn (ImportRow $r) => Str::lower($r->get('email')))
            ->filter()
            ->unique();

        $this->seen = [];

        return [
            // One query for every email in the file, rather than one per row.
            'existing' => User::query()
                ->whereIn('email', $emails)
                ->pluck('email')
                ->map(fn (string $e) => Str::lower($e))
                ->flip(),

            // The escalation guard is checked against the person who UPLOADED the file,
            // not whoever happens to be authenticated when the row is judged. On the
            // queued path nobody is authenticated at all, and a Gate::denies() reading
            // the session there would refuse every row of a perfectly good file.
            'actor' => $actor,
        ];
    }

    public function inspect(ImportRow $row, array $context, ?Model $scope): void
    {
        if ($row->isBlank()) {
            $row->fail(ImportRow::EMPTY_ROW);

            return;
        }

        if ($row->get('name') === '') {
            $row->fail(self::NO_NAME);

            return;
        }

        $email = Str::lower($row->get('email'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $row->fail(self::BAD_EMAIL);

            return;
        }

        if ($context['existing']->has($email)) {
            $row->fail(self::EXISTS);

            return;
        }

        // Duplicates WITHIN the file: the second occurrence is the problem, not the
        // first, so the human keeps one account and is told which line to delete.
        if (isset($this->seen[$email])) {
            $row->fail(self::DUPLICATE);

            return;
        }
        $this->seen[$email] = true;

        $role = $this->resolveRole($row->get('role'));

        if (! $role) {
            $row->fail(self::UNKNOWN_ROLE);

            return;
        }

        if (Gate::forUser($context['actor'])->denies('grantRole', $role->value)) {
            $row->fail(self::FORBIDDEN_ROLE);

            return;
        }

        $row->resolve(['role' => $role->label()]);
    }

    public function apply(ImportRow $row, array $context, ?Model $scope, User $actor): bool
    {
        $role = $this->resolveRole($row->get('role'));

        // Re-checked at write time against the same actor, so the guard holds even if
        // inspection and application are separated by the queue.
        if (! $role || Gate::forUser($actor)->denies('grantRole', $role->value)) {
            return false;
        }

        $user = User::create([
            'name' => $row->get('name'),
            'email' => Str::lower($row->get('email')),
            'title' => $row->get('title') ?: null,
            'phone' => $row->get('phone') ?: null,
            // Never a known or derivable value: the account is unusable until the
            // recipient follows the emailed link and sets their own.
            'password' => Str::password(32),
        ]);

        $user->syncRoles([$role->value]);

        // Queued (database driver), so a 500-row import doesn't send 500 mails inline.
        Password::sendResetLink(['email' => $user->email]);

        return true;
    }

    public function problemLabel(string $problem): ?string
    {
        return match ($problem) {
            self::NO_NAME => 'No name given',
            self::BAD_EMAIL => 'Not a valid email address',
            self::EXISTS => 'An account with this email already exists',
            self::DUPLICATE => 'Duplicate email earlier in this file',
            self::UNKNOWN_ROLE => 'Unknown role',
            self::FORBIDDEN_ROLE => 'Only a super-admin may grant this role',
            default => null,
        };
    }

    public function returnRoute(?Model $scope): array
    {
        return ['admin.users.index', []];
    }

    private function resolveRole(string $value): ?Role
    {
        $key = preg_replace('/[^a-z]/', '', Str::lower($value)) ?? '';

        return match ($key) {
            'student', 'learner' => Role::Student,
            'instructor', 'lecturer', 'teacher', 'staff' => Role::Instructor,
            'admin', 'administrator' => Role::Admin,
            'superadmin' => Role::SuperAdmin,
            'auditor', 'observer' => Role::Auditor,
            default => null,
        };
    }
}
