@php
    use App\Enums\CourseRequirement;
    use App\Services\Commerce\PricingService;
    use App\Support\Money;

    /** @var \App\Models\Programme $programme */
    $pricing = app(PricingService::class);
    $parts = $programme->parts;
    $courseTotal = $parts->sum(fn ($part) => $part->courses->count());

    $fees = array_filter([
        'Registration' => (float) $programme->registration_fee,
        'Administration' => (float) $programme->administration_fee,
        'Per paper' => (float) $programme->per_paper_fee,
    ], fn (float $amount) => $amount > 0);
@endphp

<x-public-layout :title="$programme->name"
                 :description="$programme->tagline ?: 'The '.$programme->name.' at the '.config('brand.university').'.'">

    {{-- ───────────── Hero ───────────── --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-20 -top-24 h-96 w-96 text-white/10" />

        <div class="relative mx-auto max-w-7xl px-6 py-12 lg:px-8 lg:py-16">
            <nav class="text-sm" aria-label="Breadcrumb">
                <a href="{{ route('programmes.index') }}"
                   class="inline-flex items-center gap-1.5 rounded text-white/75 transition hover:text-white focus-ring-inverse">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> All qualifications
                </a>
            </nav>

            <span class="mt-6 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
                {{ $programme->code }}
            </span>

            <h1 class="mt-4 max-w-3xl font-display text-4xl font-bold leading-[1.1] text-white sm:text-5xl">
                {{ $programme->name }}
            </h1>

            @if ($programme->tagline)
                <p class="mt-4 max-w-2xl text-lg text-white/85">{{ $programme->tagline }}</p>
            @endif

            <dl class="mt-8 flex flex-wrap gap-x-8 gap-y-4 text-white/85">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-white/60">Parts</dt>
                    <dd class="font-display text-2xl font-bold text-white">{{ $parts->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-white/60">Papers on offer</dt>
                    <dd class="font-display text-2xl font-bold text-white">{{ $courseTotal }}</dd>
                </div>
                @if ((float) $programme->per_paper_fee > 0)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-white/60">Per paper</dt>
                        <dd class="font-display text-2xl font-bold text-white">{{ Money::format($programme->per_paper_fee) }}</dd>
                    </div>
                @endif
            </dl>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('catalogue.index', ['programme' => $programme->slug]) }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring-inverse">
                    <x-ui.icon name="search" class="h-5 w-5" /> Browse these courses
                </a>
                @guest
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-white/40 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 focus-ring-inverse">
                        Create an account
                    </a>
                @endguest
            </div>
        </div>

        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-3">

            {{-- ───────────── Curriculum ───────────── --}}
            <div class="lg:col-span-2">
                @if ($programme->description)
                    <x-ui.prose :html="$programme->description" />
                @endif

                <h2 class="mt-10 font-display text-2xl font-bold text-ink">Curriculum</h2>
                <p class="mt-2 text-ink/70">
                    Papers are grouped by the part you sit them in. Credit loads and status are
                    those published for this programme.
                </p>

                @if ($parts->isEmpty())
                    <div class="mt-6">
                        <x-ui.empty-state
                            icon="layers"
                            title="The curriculum is being published"
                            description="Parts and papers for this programme will appear here shortly." />
                    </div>
                @endif

                <div class="mt-8 space-y-8">
                    @foreach ($parts as $part)
                        @php
                            // Credit sums come from the SAME rows rendered below (the service
                            // loaded only catalogue-visible courses), so the total on screen
                            // always reconciles with the list on screen.
                            $courses = $part->courses;
                            $counted = $part->creditsCounted($courses);
                            $listed = $part->creditsListed($courses);
                            $target = $part->credit_target;

                            // Built as one string rather than assembled out of spans: the
                            // sentence has to read as a sentence, and a part with no stated
                            // target must not imply a mismatch it cannot know about.
                            $creditLine = $target !== null
                                ? "{$counted} of {$target} credits"
                                : $listed.' '.Str::plural('credit', $listed).' listed';
                        @endphp

                        @php
                            // Only ever populated for a signed-in student on a sequential
                            // programme; a guest sees the prospectus exactly as before.
                            $state = ($partStates ?? collect())->get($part->id);
                        @endphp

                        <section id="{{ $part->slug }}" class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm scroll-mt-24"
                                 aria-labelledby="part-{{ $part->id }}">
                            <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-line bg-surface/60 px-5 py-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 id="part-{{ $part->id }}" class="font-display text-lg font-semibold text-ink">
                                            {{ $part->name }}
                                        </h3>
                                        @if ($state)
                                            @if ($state['cleared'])
                                                <x-ui.badge variant="success">Completed</x-ui.badge>
                                            @elseif ($state['unlocked'])
                                                <x-ui.badge variant="gold">In progress</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="neutral">Locked</x-ui.badge>
                                            @endif
                                        @endif
                                    </div>
                                    @if ($part->description)
                                        <p class="mt-0.5 text-sm text-ink/65">{{ $part->description }}</p>
                                    @endif
                                </div>

                                <p class="text-sm">
                                    <span class="font-semibold text-ink">{{ $creditLine }}</span>
                                    @if ($target !== null && $listed > $counted)
                                        <span class="block text-xs font-normal text-ink/50">{{ $listed }} listed, including electives</span>
                                    @endif
                                </p>
                            </header>

                            @if ($state && ! $state['cleared'])
                                {{-- Your own standing in this part: both bars, always both
                                     shown, so "on track" can never mean one of them. --}}
                                <div class="border-b border-line bg-surface/30 px-5 py-3">
                                    @php
                                        $done = $part->courses->filter(fn ($c) =>
                                            ($c->pivot->requirement instanceof CourseRequirement
                                                ? $c->pivot->requirement
                                                : CourseRequirement::tryFrom((string) $c->pivot->requirement)) === CourseRequirement::Compulsory
                                        )->count() - $state['outstanding']->count();
                                        $totalCompulsory = $done + $state['outstanding']->count();
                                        $bar = $state['creditBar'];
                                    @endphp

                                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm">
                                        @if ($totalCompulsory > 0)
                                            <span class="{{ $state['outstanding']->isEmpty() ? 'text-success' : 'text-ink/70' }}">
                                                {{ $done }} of {{ $totalCompulsory }} compulsory {{ Str::plural('paper', $totalCompulsory) }} passed
                                            </span>
                                        @endif
                                        @if ($bar !== null)
                                            <span class="{{ $state['creditsEarned'] >= $bar ? 'text-success' : 'text-ink/70' }}">
                                                {{ $state['creditsEarned'] }} of {{ $bar }} credits earned
                                            </span>
                                        @endif
                                    </div>

                                    @if ($bar !== null)
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink/10">
                                            <div class="h-full rounded-full bg-crimson transition-all"
                                                 style="width: {{ min(100, (int) round($state['creditsEarned'] / max(1, $bar) * 100)) }}%"></div>
                                        </div>
                                    @endif

                                    @unless ($state['unlocked'])
                                        <p class="mt-2 text-xs text-ink/55">
                                            Finish the earlier parts of this programme to open these papers.
                                        </p>
                                    @endunless
                                </div>
                            @endif

                            @if ($courses->isEmpty())
                                <p class="px-5 py-8 text-center text-sm text-ink/55">
                                    No papers are published for this part yet.
                                </p>
                            @else
                                {{-- Column headings only from sm: below that each row is a
                                     stacked card, which reads far better at 375px than a
                                     four-column table squeezed sideways. --}}
                                <div class="hidden items-center gap-4 border-b border-line px-5 py-2 text-xs font-medium uppercase tracking-wide text-ink/45 sm:flex">
                                    <span class="w-20 shrink-0">Code</span>
                                    <span class="min-w-0 flex-1">Paper</span>
                                    <span class="w-28 shrink-0">Status</span>
                                    <span class="w-14 shrink-0 text-right">Credits</span>
                                    <span class="w-24 shrink-0 text-right">Fee</span>
                                </div>

                                <ul class="divide-y divide-line">
                                    @foreach ($courses as $course)
                                        @php
                                            $requirement = $course->pivot->requirement instanceof CourseRequirement
                                                ? $course->pivot->requirement
                                                : CourseRequirement::tryFrom((string) $course->pivot->requirement);
                                            $price = $pricing->priceFor($course);
                                        @endphp
                                        {{-- Below sm this is a stacked card, so every fixed
                                             column width is sm:-prefixed — an unprefixed
                                             w-14 would wrap "Credits · 2" onto two lines at
                                             375px. Above sm the widths line up with the
                                             column headings above. --}}
                                        <li class="flex flex-col gap-1.5 px-5 py-4 transition hover:bg-surface/50 sm:flex-row sm:items-center sm:gap-4">
                                            <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-crimson sm:w-20">
                                                {{ $course->code }}
                                            </span>

                                            <a href="{{ route('catalogue.show', $course) }}"
                                               class="min-w-0 flex-1 rounded font-medium text-ink hover:text-crimson focus-ring">
                                                {{ $course->title }}
                                            </a>

                                            {{-- One metadata line on mobile; three columns from sm. --}}
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 sm:contents">
                                                <span class="shrink-0 sm:w-28">
                                                    @if ($requirement)
                                                        <x-ui.badge :variant="$requirement->badge()">{{ $requirement->shortLabel() }}</x-ui.badge>
                                                    @endif
                                                </span>

                                                <span class="shrink-0 whitespace-nowrap text-sm text-ink/70 sm:w-14 sm:text-right">
                                                    @if ($course->pivot->credit_load)
                                                        {{ $course->pivot->credit_load }}<span class="sm:hidden"> credits</span>
                                                    @else
                                                        <span aria-hidden="true" class="text-ink/30">—</span>
                                                    @endif
                                                </span>

                                                <span class="shrink-0 whitespace-nowrap text-sm font-semibold text-ink sm:w-24 sm:text-right">
                                                    {{ Money::formatOrFree($price) }}
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="border-t border-line bg-surface/40 px-5 py-3 text-right">
                                    <a href="{{ route('catalogue.index', ['programme' => $programme->slug, 'part' => $part->slug]) }}"
                                       class="inline-flex items-center gap-1.5 rounded text-sm font-medium text-crimson hover:text-crimson-dark focus-ring">
                                        Open {{ $part->name }} in the catalogue
                                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                                    </a>
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>

            {{-- ───────────── Fees & next steps ───────────── --}}
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <h2 class="font-display text-lg font-semibold text-ink">What it costs</h2>

                    @if ($fees === [])
                        <p class="mt-3 text-sm text-ink/70">
                            There is no fee for this programme — its courses are open to every
                            {{ config('brand.short') }} learner.
                        </p>
                    @else
                        <dl class="mt-4 space-y-2.5 text-sm">
                            @foreach ($fees as $label => $amount)
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-ink/65">{{ $label }}</dt>
                                    <dd class="font-semibold text-ink">{{ Money::format($amount) }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <p class="mt-4 rounded-xl bg-surface px-3 py-2.5 text-xs leading-relaxed text-ink/65">
                            Registration and administration are <strong class="font-semibold text-ink">one-off</strong>
                            charges, added to your first purchase in this programme and never again.
                            After that you pay per paper.
                        </p>
                    @endif

                    <div class="mt-5 space-y-2">
                        <x-ui.button class="w-full" :href="route('catalogue.index', ['programme' => $programme->slug])">
                            Browse these courses
                        </x-ui.button>
                        <x-ui.button variant="secondary" class="w-full" :href="route('cart.index')">
                            <x-ui.icon name="shopping-cart" class="h-5 w-5" /> View your cart
                        </x-ui.button>
                    </div>

                    <p class="mt-3 text-center text-xs text-ink/50">
                        Prices in {{ Money::currency() }}. Add papers to your cart now and sign in when you check out.
                    </p>
                </div>

                <div class="mt-5 rounded-2xl border border-line bg-surface/60 p-5">
                    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-ink">
                        <x-ui.icon name="certificate" class="h-5 w-5 text-gold-ink" />
                        On completion
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-ink/70">
                        Finish a paper and your certificate is issued with a serial and QR code that any
                        employer can check at
                        <a href="{{ route('verify.index') }}" class="rounded font-medium text-crimson hover:underline focus-ring">our verification portal</a>.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-public-layout>
