@props([
    'role' => null,   // a role name string (e.g. "super-admin") or App\Enums\Role
])

@php
    $enum = $role instanceof \App\Enums\Role
        ? $role
        : \App\Enums\Role::tryFrom((string) $role);
@endphp

@if ($enum)
    <x-ui.badge :variant="$enum->badge()">{{ $enum->label() }}</x-ui.badge>
@elseif ($role)
    <x-ui.badge variant="neutral">{{ \Illuminate\Support\Str::headline((string) $role) }}</x-ui.badge>
@endif
