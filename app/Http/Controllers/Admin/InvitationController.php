<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\InvitationService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function index(Request $request): ViewContract
    {
        $this->authorize('invite', User::class);

        $data = [
            'invitations' => UserInvitation::with('inviter')->latest()->paginate(15),
            'roles' => $this->grantableRoles(),
        ];

        // AJAX → swap only the table region so an action never reloads the page.
        if ($request->ajax() || $request->wantsJson()) {
            return view('admin.invitations._table', $data);
        }

        return view('admin.invitations.index', $data);
    }

    public function store(InviteUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->invitations->invite(
            $data['name'],
            $data['email'],
            Role::from($data['role']),
            $request->user(),
        );

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', "Invitation sent to {$data['email']}.");
    }

    public function resend(Request $request, UserInvitation $invitation): RedirectResponse|JsonResponse
    {
        $this->authorize('invite', User::class);

        if ($invitation->isAccepted()) {
            return $this->respond($request, 'That invitation has already been accepted.');
        }

        $this->invitations->resend($invitation);

        return $this->respond($request, "Invitation re-sent to {$invitation->email}.");
    }

    public function destroy(Request $request, UserInvitation $invitation): RedirectResponse|JsonResponse
    {
        $this->authorize('invite', User::class);

        $invitation->delete();

        return $this->respond($request, 'Invitation revoked.');
    }

    /**
     * JSON for AJAX (the live table), a flash redirect for a normal request.
     */
    protected function respond(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * @return array<int, Role>
     */
    protected function grantableRoles(): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => auth()->user()->can('grantRole', $role->value),
        ));
    }
}
