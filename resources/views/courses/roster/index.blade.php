@php
    use App\Enums\EnrollmentStatus;
    use App\Enums\Role;
    $isAdmin = auth()->user()->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
@endphp

<x-app-layout :title="'Roster · '.$course->code">
    <div class="mx-auto max-w-7xl space-y-6"
         x-data="dataTable('{{ route('courses.roster', $course) }}', { params: { search: @js($search), status: @js($activeStatus) } })">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
                </a>
                <h2 class="mt-2 font-display text-2xl font-semibold text-ink">{{ $course->title }}</h2>
                <p class="mt-1 text-sm text-ink/60">Roster · {{ $course->enrollmentMode()->label() }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($isAdmin)
                    <x-ui.button variant="secondary" :href="route('enrollments.bulk.create')">
                        <x-ui.icon name="user-plus" class="h-5 w-5" /> Bulk import
                    </x-ui.button>
                @endif
                <x-ui.button variant="secondary" :href="route('courses.roster.export', $course)">
                    <x-ui.icon name="document-text" class="h-5 w-5" /> Export CSV
                </x-ui.button>
            </div>
        </div>

        {{-- Capacity meter --}}
        <x-ui.card>
            <x-courses.capacity-meter
                :taken="$seatsTaken"
                :capacity="$course->capacity"
                :waitlist="$waitlistCount" />
        </x-ui.card>

        {{-- Add a student directly (staff) --}}
        @if ($canEnroll && ($enrollableStudents ?? collect())->isNotEmpty())
            @php
                // Set only after a refusal for THIS course, so the override never appears
                // before the reason for it has been shown.
                $block = session('prerequisite_block');
                $block = ($block && (int) $block['course_id'] === $course->id) ? $block : null;
            @endphp

            <div x-data="{ open: {{ $block ? 'true' : 'false' }} }">
                <x-ui.button variant="ghost" size="sm" @click="open = ! open" x-bind:aria-expanded="open.toString()">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Add a student
                </x-ui.button>
                <div x-show="open" x-collapse x-cloak class="mt-2">
                    <x-ui.card>
                        <form method="POST" action="{{ route('enrollment.admin.store') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="course_id" value="{{ $course->id }}">

                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[18rem] flex-1">
                                    <label for="add-student" class="mb-1 block text-sm font-medium text-ink">Student</label>
                                    <select id="add-student" name="user_id" required
                                            class="w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                        <option value="">Choose a student…</option>
                                        @foreach ($enrollableStudents as $student)
                                            <option value="{{ $student->id }}" @selected(old('user_id', $block['user_id'] ?? null) == $student->id)>
                                                {{ $student->name }} — {{ $student->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @unless ($block)
                                    <x-ui.button type="submit">Enrol student</x-ui.button>
                                @endunless
                            </div>

                            @if ($block)
                                {{-- The exception path. Staff must be able to admit a transfer
                                     student or someone with prior credit — but it leaves a
                                     trace, and the trace is worthless without a reason. --}}
                                <div x-data="{ override: false }" class="rounded-xl border border-gold/40 bg-gold/5 p-4">
                                    <p class="text-sm font-medium text-ink">{{ $block['student'] }} does not meet the prerequisite</p>
                                    <p class="mt-1 text-sm leading-relaxed text-ink/70">{{ $block['message'] }}</p>

                                    <label class="mt-3 flex items-start gap-2 text-sm text-ink">
                                        <input type="hidden" name="override_prerequisites" value="0">
                                        <input type="checkbox" name="override_prerequisites" value="1" x-model="override"
                                               class="mt-0.5 rounded border-line text-crimson focus:ring-crimson">
                                        <span>Enrol anyway <span class="text-ink/60">(records an override against this enrolment)</span></span>
                                    </label>

                                    <div x-show="override" x-collapse x-cloak class="mt-3">
                                        <label for="override-reason" class="mb-1 block text-sm font-medium text-ink">
                                            Reason <span class="text-crimson">*</span>
                                        </label>
                                        <textarea id="override-reason" name="override_reason" rows="2" maxlength="500"
                                                  placeholder="e.g. Transfer student — Part I credit verified from Lagos campus."
                                                  class="w-full rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">{{ old('override_reason') }}</textarea>
                                        @error('override_reason')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="mt-4">
                                        <x-ui.button type="submit" x-text="override ? 'Enrol anyway' : 'Enrol student'">Enrol student</x-ui.button>
                                    </div>
                                </div>
                            @endif
                        </form>
                    </x-ui.card>
                </div>
            </div>
        @endif

        {{-- Status tabs --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="params.status = ''; filter()"
                    :class="(params.status ?? '') === '' ? 'bg-crimson text-white' : 'bg-card border border-line text-ink/70 hover:text-ink'"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium focus-ring transition-colors">
                All <span class="opacity-70">{{ $course->enrollments()->count() }}</span>
            </button>
            @foreach ($statuses as $s)
                @php $count = (int) ($counts[$s->value] ?? 0); @endphp
                <button type="button" @click="params.status = @js($s->value); filter()"
                        :class="(params.status ?? '') === @js($s->value) ? 'bg-crimson text-white' : 'bg-card border border-line text-ink/70 hover:text-ink'"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium focus-ring transition-colors">
                    {{ $s->label() }} <span class="opacity-70">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('courses.roster', $course) }}" @submit.prevent="filter()">
            <label for="search" class="sr-only">Search by name or email</label>
            <div class="relative max-w-md">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                    <x-ui.icon name="search" class="h-5 w-5" />
                </span>
                <x-ui.input id="search" name="search" type="search" value="{{ $search }}"
                            placeholder="Search name or email…" class="pl-10"
                            x-model="params.search" @input.debounce.350ms="filter()"
                            aria-controls="roster-results" />
            </div>
        </form>

        {{-- Results region (swapped in place by dataTable); skeleton overlay while fetching --}}
        <div class="relative">
            <div x-show="loading" x-cloak class="absolute inset-0 z-10">
                <x-ui.skeleton-table :rows="6" :cols="5" />
            </div>
            <div id="roster-results" x-ref="results"
                 @click="onNav($event)" @submit="onAction($event)"
                 :class="loading && 'pointer-events-none opacity-0'"
                 :aria-busy="loading.toString()">
                @include('courses.roster._table')
            </div>
        </div>
    </div>
</x-app-layout>
