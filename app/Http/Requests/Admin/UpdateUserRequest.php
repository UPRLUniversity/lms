<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $isSelf = $this->user()->is($target);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($target->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            // You can never change your OWN role (no self-promotion / self-demotion),
            // so for self the field is ignored entirely; the controller skips it too.
            'role' => $isSelf ? ['nullable'] : [
                'required',
                Rule::in(Role::values()),
                function ($attr, $value, $fail) use ($target) {
                    // Keeping the role unchanged is always fine; only a *change*
                    // into a privileged role needs the super-admin grant right.
                    if ($target->hasRole($value)) {
                        return;
                    }

                    // Two separate questions, and both have to be answered yes. May you
                    // take away what they hold, and may you hand out what you are giving
                    // them? Only the second used to be asked, which let an admin demote a
                    // super-admin by moving them to a role admins are allowed to grant.
                    if (Gate::denies('changeRole', $target)) {
                        $fail(__('You are not allowed to change this user’s role.'));

                        return;
                    }

                    if (Gate::denies('grantRole', $value)) {
                        $fail(__('You are not allowed to grant the :role role.', ['role' => $value]));

                        return;
                    }

                    // A backstop, and deliberately kept even though nothing can currently
                    // reach it: to get this far you must be a super-admin acting on a
                    // DIFFERENT super-admin, which means two of you are active and the
                    // target is by definition not the last. It costs one query and it is
                    // the check that stops the one mistake no screen here can undo, so it
                    // stays for whatever screen or command comes next.
                    if ($target->isLastActiveSuperAdmin()) {
                        $fail(__('This is the only active super-admin. Promote another account first, or the institution will be left with nobody who can.'));
                    }
                },
            ],
        ];
    }
}
