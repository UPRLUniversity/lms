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

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($target->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'role' => [
                'required',
                Rule::in(Role::values()),
                function ($attr, $value, $fail) use ($target) {
                    // Keeping the role unchanged is always fine; only a *change*
                    // into a privileged role needs the super-admin grant right.
                    if ($target->hasRole($value)) {
                        return;
                    }

                    if (Gate::denies('grantRole', $value)) {
                        $fail(__('You are not allowed to grant the :role role.', ['role' => $value]));
                    }
                },
            ],
        ];
    }
}
