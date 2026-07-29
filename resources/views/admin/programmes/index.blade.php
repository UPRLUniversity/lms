@php
    use App\Support\Money;
    use Illuminate\Support\Str;
@endphp

<x-app-layout title="Programmes">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Programmes</h2>
                <p class="mt-1 max-w-2xl text-ink/70">
                    The qualifications a course is examined under, and their parts. A course can sit in
                    more than one programme — this is separate from the faculty that teaches it.
                </p>
            </div>
            @if ($canManage)
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" :href="route('admin.programme-parts.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Part
                    </x-ui.button>
                    <x-ui.button :href="route('admin.programmes.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Programme
                    </x-ui.button>
                </div>
            @endif
        </div>

        @if ($programmes->isEmpty())
            <x-ui.empty-state
                icon="layers"
                title="No programmes yet"
                description="Create a programme such as CPR or DPR, add its parts, then place courses into them from the course builder.">
                @if ($canManage)
                    <x-slot name="action">
                        <x-ui.button :href="route('admin.programmes.create')">
                            <x-ui.icon name="plus" class="h-5 w-5" /> Add a programme
                        </x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach ($programmes as $programme)
                    <x-ui.card :padding="false">
                        {{-- Programme header --}}
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-display text-lg font-semibold text-ink">{{ $programme->name }}</h3>
                                    <x-ui.badge variant="crimson">{{ $programme->code }}</x-ui.badge>
                                    @unless ($programme->is_active)
                                        <x-ui.badge>Inactive</x-ui.badge>
                                    @endunless
                                </div>
                                @if ($programme->tagline)
                                    <p class="mt-0.5 text-sm text-ink/60">{{ $programme->tagline }}</p>
                                @endif

                                {{-- Fee schedule. Stacks on mobile, inline from sm up. --}}
                                <dl class="mt-2 flex flex-col gap-x-4 gap-y-1 text-xs text-ink/60 sm:flex-row sm:flex-wrap">
                                    <div class="flex gap-1.5">
                                        <dt>Registration</dt>
                                        <dd class="font-medium text-ink/80">{{ Money::formatOrFree($programme->registration_fee) }}</dd>
                                    </div>
                                    <div class="flex gap-1.5">
                                        <dt>Administration</dt>
                                        <dd class="font-medium text-ink/80">{{ Money::formatOrFree($programme->administration_fee) }}</dd>
                                    </div>
                                    <div class="flex gap-1.5">
                                        <dt>Per paper</dt>
                                        <dd class="font-medium text-ink/80">{{ Money::formatOrFree($programme->per_paper_fee) }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-1 text-xs text-ink/45">
                                    {{ $programme->parts_count }} {{ Str::plural('part', $programme->parts_count) }}
                                </p>
                            </div>

                            @if ($canManage)
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-ui.button size="sm" variant="ghost" :href="route('admin.programmes.edit', $programme)">
                                        <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('admin.programmes.destroy', $programme) }}"
                                          onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete this programme?', text: 'Its parts are removed too. Courses placed in it must be moved out first.', confirmText: 'Delete', danger: true }).then(ok => ok &amp;&amp; this.submit());">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button size="sm" variant="danger" type="submit">Delete</x-ui.button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        {{-- Parts --}}
                        @if ($programme->parts->isNotEmpty())
                            <ul class="divide-y divide-line border-t border-line">
                                @foreach ($programme->parts as $part)
                                    @php
                                        $counted = $part->creditsCounted($part->courses);
                                        $listed = $part->creditsListed($part->courses);
                                        $reconciles = $part->creditsReconcile($part->courses);
                                        $courseCount = $part->courses->count();
                                    @endphp
                                    <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-5 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium text-ink">{{ $part->name }}</p>
                                            @if ($part->description)
                                                <p class="truncate text-xs text-ink/50">{{ $part->description }}</p>
                                            @endif

                                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink/55">
                                                <span>{{ $courseCount }} {{ Str::plural('course', $courseCount) }}</span>

                                                @if ($part->credit_target !== null)
                                                    <span aria-hidden="true">·</span>
                                                    {{-- Counted credits are compulsory + required elective only, which is
                                                         what the printed prospectus totals. Listed includes electives. --}}
                                                    <span>
                                                        {{ $counted }} of {{ $part->credit_target }} credits
                                                    </span>
                                                    @if ($reconciles === false)
                                                        <x-ui.badge variant="gold" title="Compulsory and required-elective credits do not add up to the stated total.">
                                                            Off by {{ abs($counted - $part->credit_target) }}
                                                        </x-ui.badge>
                                                    @endif
                                                @elseif ($listed > 0)
                                                    <span aria-hidden="true">·</span>
                                                    <span>{{ $listed }} credits listed</span>
                                                @endif

                                                @if ($listed !== $counted && $part->credit_target !== null)
                                                    <span aria-hidden="true">·</span>
                                                    <span class="text-ink/40">{{ $listed }} listed incl. electives</span>
                                                @endif
                                            </p>
                                        </div>

                                        @if ($canManage)
                                            <div class="flex shrink-0 items-center gap-1">
                                                <x-ui.button size="sm" variant="ghost" :href="route('admin.programme-parts.edit', $part)">Edit</x-ui.button>
                                                <form method="POST" action="{{ route('admin.programme-parts.destroy', $part) }}"
                                                      onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete this part?', confirmText: 'Delete', danger: true }).then(ok => ok &amp;&amp; this.submit());">
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
                            <p class="border-t border-line px-5 py-3 text-sm text-ink/40">
                                No parts yet.
                                @if ($canManage)
                                    <a href="{{ route('admin.programme-parts.create', ['programme' => $programme->id]) }}"
                                       class="text-crimson hover:underline focus-ring rounded">Add one</a>.
                                @endif
                            </p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
