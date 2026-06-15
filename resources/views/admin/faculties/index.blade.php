@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Academic structure">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Academic structure</h2>
                <p class="mt-1 text-ink/70">Faculties and their departments. Courses belong to a department.</p>
            </div>
            @if ($canManage)
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" :href="route('admin.departments.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Department
                    </x-ui.button>
                    <x-ui.button :href="route('admin.faculties.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Faculty
                    </x-ui.button>
                </div>
            @endif
        </div>

        @if ($faculties->isEmpty())
            <x-ui.empty-state
                icon="graduation"
                title="No faculties yet"
                description="Create a faculty, then add departments to it. Courses are organised under departments.">
                @if ($canManage)
                    <x-slot name="action">
                        <x-ui.button :href="route('admin.faculties.create')">
                            <x-ui.icon name="plus" class="h-5 w-5" /> Add a faculty
                        </x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach ($faculties as $faculty)
                    <x-ui.card :padding="false">
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <h3 class="font-display text-lg font-semibold text-ink">{{ $faculty->name }}</h3>
                                @if ($faculty->description)
                                    <p class="mt-0.5 text-sm text-ink/60">{{ $faculty->description }}</p>
                                @endif
                                <p class="mt-1 text-xs text-ink/50">
                                    {{ $faculty->departments_count }} {{ Str::plural('department', $faculty->departments_count) }} ·
                                    {{ $faculty->courses_count }} {{ Str::plural('course', $faculty->courses_count) }}
                                </p>
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-2">
                                    <x-ui.button size="sm" variant="ghost" :href="route('admin.faculties.edit', $faculty)">
                                        <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('admin.faculties.destroy', $faculty) }}"
                                          onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete this faculty?', text: 'Departments inside it will be removed too.', confirmText: 'Delete', danger: true }).then(ok => ok && this.submit());">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button size="sm" variant="danger" type="submit">Delete</x-ui.button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @if ($faculty->departments->isNotEmpty())
                            <ul class="divide-y divide-line border-t border-line">
                                @foreach ($faculty->departments as $department)
                                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-ink">{{ $department->name }}</p>
                                            @if ($department->description)
                                                <p class="truncate text-xs text-ink/50">{{ $department->description }}</p>
                                            @endif
                                        </div>
                                        @if ($canManage)
                                            <div class="flex items-center gap-1">
                                                <x-ui.button size="sm" variant="ghost" :href="route('admin.departments.edit', $department)">Edit</x-ui.button>
                                                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}"
                                                      onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete this department?', confirmText: 'Delete', danger: true }).then(ok => ok && this.submit());">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-ui.button size="sm" variant="ghost" type="submit" class="text-crimson">Delete</x-ui.button>
                                                </form>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="border-t border-line px-5 py-3 text-sm text-ink/40">No departments yet.</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
