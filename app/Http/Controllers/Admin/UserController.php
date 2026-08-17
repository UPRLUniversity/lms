<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Columns the table may be sorted by → the actual DB column. */
    private const SORTABLE = [
        'name' => 'name',
        'email' => 'email',
        'status' => 'is_active',
        'last_login' => 'last_login_at',
        'created' => 'created_at',
    ];

    /**
     * Paginated, searchable, sortable, role-filterable user table. Returns just
     * the table partial for AJAX (X-Requested-With) requests so the surrounding
     * page never reloads; the full page otherwise.
     */
    public function index(Request $request): ViewContract
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');

        [$sort, $direction] = $this->resolveSort($request);

        $users = User::query()
            // media, not just roles: every row renders <x-ui.avatar>, which resolves the
            // user's avatar through the polymorphic media relation. Without it the list
            // issued one extra query per person on the page.
            ->with(['roles', 'media'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($role, Role::values(), true), function ($query) use ($role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->orderBy(self::SORTABLE[$sort], $direction)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $data = [
            'users' => $users,
            'roles' => Role::cases(),
            'search' => $search,
            'activeRole' => $role,
            'sort' => $sort,
            'direction' => $direction,
        ];

        // AJAX (X-Requested-With from the live table, or an Accept: json client) →
        // swap only the table region; full page on a normal navigation.
        if ($request->ajax() || $request->wantsJson()) {
            return view('admin.users._table', $data);
        }

        return view('admin.users.index', $data);
    }

    /**
     * Validate the requested sort against the whitelist.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveSort(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, self::SORTABLE)) {
            $sort = 'name';
        }

        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        return [$sort, $direction];
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => $this->grantableRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'title' => $data['title'] ?? null,
            'password' => $data['password'],            // hashed by cast
        ]);

        // Admin-created accounts are pre-verified (email_verified_at is guarded,
        // so set it through the model API rather than mass assignment).
        $user->markEmailAsVerified();

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "{$user->name} was created.");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        // Admins may enrol a user directly from this page (status active). Offer the
        // published courses they aren't already actively enrolled in.
        $canEnroll = $this->user()->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
        $enrollableCourses = $canEnroll
            ? Course::query()
                ->where('status', CourseStatus::Published->value)
                ->whereDoesntHave('enrollments', fn ($q) => $q
                    ->where('user_id', $user->id)
                    ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
                ->orderBy('title')
                ->get(['id', 'title', 'code'])
            : collect();

        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->grantableRoles(),
            'currentRole' => $user->roles->first()?->name,
            'isSelf' => $this->user()->is($user),
            'canEnroll' => $canEnroll,
            'enrollableCourses' => $enrollableCourses,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'title' => $data['title'] ?? null,
        ]);

        // Never let anyone change their own role (self-promotion/demotion).
        if (! $this->user()->is($user) && $this->user()->can('assignRoles', User::class)) {
            $user->syncRoles([$data['role']]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', "{$user->name} was updated.");
    }

    /**
     * Activate / deactivate (no hard deletes). Toggles the is_active flag.
     * Answers JSON for AJAX so the table can act without a full reload.
     */
    public function setStatus(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorize('setActiveStatus', $user);

        $request->validate(['is_active' => ['required', 'boolean']]);

        // A backstop. Nothing reaches it today — only a super-admin gets past the policy
        // onto another super-admin, and that means two are active, so the target is not
        // the last. It lives here rather than in the policy because Gate::before waves a
        // super-admin past every policy method, and because a refusal on a destructive
        // action deserves a sentence rather than a bare 403.
        if (! $request->boolean('is_active') && $user->isLastActiveSuperAdmin()) {
            $message = 'That is the only active super-admin. Promote another account first, or nobody will be able to.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.users.index')->with('error', $message);
        }

        $user->update(['is_active' => $request->boolean('is_active')]);

        $verb = $user->is_active ? 'reactivated' : 'deactivated';
        $message = "{$user->name} was {$verb}.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.users.index')->with('status', $message);
    }

    /**
     * Roles the current admin is actually allowed to grant.
     *
     * @return array<int, Role>
     */
    protected function grantableRoles(): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => $this->user()->can('grantRole', $role->value),
        ));
    }

    protected function user(): User
    {
        return auth()->user();
    }
}
