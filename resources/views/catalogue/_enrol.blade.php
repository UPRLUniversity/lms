@php
    use App\Enums\EnrollmentMode;
    use App\Enums\EnrollmentStatus;
    use App\Support\Money;

    /** @var \App\Models\Course $course */
    /** @var \App\Models\Enrollment|null $enrollment */
    $mode = $course->enrollmentMode();
    $seatsTaken = $course->seatsTaken();
    $full = $course->isFull();
    $windowFuture = $course->enrollmentOpensInFuture();
    $windowClosed = $course->enrollmentHasClosed();
    $status = $enrollment?->status;

    // Pricing state, resolved in CatalogueController. A paid course is bought, not
    // enrolled on — the paywall itself lives in EnrollmentService::selfEnroll, and
    // everything below is presentation over that single rule.
    $isPaid = ($price ?? 0) > 0;
    $hasPurchased = $hasPurchased ?? false;
    $inCart = $inCart ?? false;
    $enrolled = $status === EnrollmentStatus::Active || $status === EnrollmentStatus::Completed;

    // Progression. Only ever locks someone who does NOT already have access — an existing
    // enrolment is never re-evaluated, so a student mid-course keeps their "Continue
    // learning" button whatever a programme's rule was changed to afterwards.
    $verdict = $verdict ?? null;
    $locked = $verdict?->isBlocked() && ! $enrolled && ! $hasPurchased;
@endphp

{{-- Price header (paid courses only) --}}
@if ($isPaid && ! $enrolled)
    <div class="mb-5 border-b border-line pb-5">
        <p class="font-display text-3xl font-bold text-ink">{{ Money::format($price) }}</p>
        <p class="mt-0.5 text-xs text-ink/65">
            One-off payment · lifetime access
            @if ($programme = $course->primaryProgramme())
                <span class="mt-1 block">Part of {{ $programme->name }}</span>
            @endif
        </p>
    </div>
@endif

{{-- Capacity meter (only when the course caps places) --}}
@if ($course->hasCapacityLimit())
    <x-courses.capacity-meter
        :taken="$seatsTaken"
        :capacity="$course->capacity"
        :waitlist="$course->enrollments()->where('status', EnrollmentStatus::Waitlisted->value)->count()"
        class="mb-5 border-b border-line pb-5" />
@endif

@if ($canManageCourse)
    {{-- Staff viewing their own course --}}
    <x-ui.button class="w-full" :href="route('courses.roster', $course)">
        <x-ui.icon name="users" class="h-5 w-5" /> Manage roster
    </x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/65">
        {{ $mode->label() }}@if ($isPaid) · {{ Money::format($price) }}@endif
    </p>

@elseif ($enrolled)
    {{-- Already has access, however they got it --}}
    <div class="mb-3 flex items-center justify-center gap-2 rounded-xl bg-success/10 px-4 py-2.5 text-sm font-medium text-success">
        <x-ui.icon name="check" class="h-4 w-4" stroke-width="2.5" />
        {{ $status === EnrollmentStatus::Completed ? 'You completed this course' : "You're enrolled" }}
    </div>
    <x-ui.button class="w-full" :href="route('learn.resume', $course)">
        {{ $status === EnrollmentStatus::Completed ? 'Revisit the course' : 'Continue learning' }}
    </x-ui.button>

@elseif ($locked)
    {{-- Locked, not hidden. A student must be able to see what they are working toward,
         and exactly what is left — so the reason names the part and links to it. --}}
    <div class="rounded-xl border border-line bg-surface px-4 py-4 text-center">
        <p class="font-display text-base font-semibold text-ink">
            {{ $verdict->headline() }}
        </p>
        <p class="mt-1.5 text-sm leading-relaxed text-ink/70">{{ $verdict->message() }}</p>

        @if ($verdict->creditsRequired)
            @php $pct = min(100, (int) round($verdict->creditsEarned / max(1, $verdict->creditsRequired) * 100)); @endphp
            <div class="mt-3">
                <div class="h-1.5 overflow-hidden rounded-full bg-ink/10">
                    <div class="h-full rounded-full bg-crimson transition-all" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-ink/70">
                    {{ $verdict->creditsEarned }} of {{ $verdict->creditsRequired }} credits earned
                </p>
            </div>
        @endif

        @if ($verdict->blockingPart?->programme)
            <x-ui.button variant="secondary" class="mt-4 w-full"
                         :href="route('programmes.show', $verdict->blockingPart->programme).'#'.$verdict->blockingPart->slug">
                See {{ $verdict->blockingPart->name }}
            </x-ui.button>
        @endif
    </div>

@elseif ($isPaid && $hasPurchased)
    {{-- Paid for, but the enrolment did not land (a rare fulfilment failure). Say so
         plainly rather than offering to sell it again. --}}
    <div class="rounded-xl bg-gold/10 px-4 py-3 text-center text-sm font-medium text-gold-ink">
        You have paid for this course
    </div>
    <p class="mt-2 text-center text-xs text-ink/65">
        Your access is being set up. <a href="{{ route('orders.index') }}" class="text-crimson hover:underline focus-ring rounded">See your orders</a>.
    </p>

@elseif ($isPaid)
    {{-- The buy path. Open to guests: they fill a cart, then sign in at checkout. --}}
    @if ($inCart)
        <x-ui.button class="w-full" :href="route('cart.index')">
            <x-ui.icon name="shopping-cart" class="h-5 w-5" /> In your cart — view cart
        </x-ui.button>
    @else
        <form method="POST" action="{{ route('cart.store', $course) }}">
            @csrf
            <x-ui.button type="submit" class="w-full">
                <x-ui.icon name="shopping-cart" class="h-5 w-5" /> Add to cart
            </x-ui.button>
        </form>
    @endif

    {{-- Buy now = add + go straight to checkout, so a single-course purchase is two
         clicks rather than four. CartController honours `then=checkout`. --}}
    <form method="POST" action="{{ route('cart.store', $course) }}" class="mt-2">
        @csrf
        <input type="hidden" name="then" value="checkout">
        <x-ui.button type="submit" variant="secondary" class="w-full">Buy now</x-ui.button>
    </form>

    <p class="mt-3 text-center text-xs text-ink/65">
        @guest
            You can add this to your cart now and sign in when you check out.
        @else
            Secure checkout · {{ Money::currency() }}
        @endguest
    </p>

@elseif (! auth()->check())
    {{-- Free course, signed out --}}
    <x-ui.button class="w-full" :href="route('register')">Create an account to enrol</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/65">
        Already a member? <a href="{{ route('login') }}" class="text-crimson hover:underline focus-ring rounded">Log in</a>
    </p>

@elseif ($status === EnrollmentStatus::Pending)
    {{-- Awaiting approval --}}
    <div class="flex items-center justify-center gap-2 rounded-xl bg-gold/10 px-4 py-3 text-sm font-medium text-gold-ink">
        <x-ui.icon name="clock" class="h-4 w-4" /> Awaiting approval
    </div>
    <p class="mt-2 text-center text-xs text-ink/65">We'll email you once a staff member reviews your request.</p>

@elseif ($status === EnrollmentStatus::Waitlisted)
    {{-- On the waitlist --}}
    <div class="flex items-center justify-center gap-2 rounded-xl bg-ink/5 px-4 py-3 text-sm font-medium text-ink">
        <x-ui.icon name="users" class="h-4 w-4" /> You're #{{ $enrollment->waitlistPosition() }} on the waitlist
    </div>
    <form method="POST" action="{{ route('enrollment.withdraw', $enrollment) }}" class="mt-3"
          x-data
          @submit.prevent="if (await window.uprlConfirm({ title: 'Leave the waitlist?', confirmText: 'Yes, leave' })) $el.submit()">
        @csrf @method('DELETE')
        <button type="submit" class="w-full text-center text-xs text-ink/65 hover:text-crimson focus-ring rounded py-1">
            Leave the waitlist
        </button>
    </form>

@elseif ($mode === EnrollmentMode::InviteOnly)
    {{-- Invite-only: no self-enrol --}}
    <div class="flex items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-3 text-sm font-medium text-ink/70">
        <x-ui.icon name="shield" class="h-4 w-4" /> Enrolment by invitation
    </div>
    <p class="mt-2 text-center text-xs text-ink/65">An administrator adds students to this course directly.</p>

@elseif ($windowFuture)
    {{-- Window not open yet --}}
    <x-ui.button class="w-full" disabled>Enrolment opens {{ $course->enrollment_opens_at->isoFormat('D MMM') }}</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/65">Check back soon to claim your place.</p>

@elseif ($windowClosed)
    {{-- Window closed --}}
    <x-ui.button class="w-full" disabled>Enrolment closed</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/65">Enrolment for this course has ended.</p>

@elseif ($full)
    {{-- Full → waitlist --}}
    <form method="POST" action="{{ route('enrollment.store', $course) }}">
        @csrf
        <x-ui.button type="submit" variant="secondary" class="w-full">
            <x-ui.icon name="users" class="h-5 w-5" /> Join the waitlist
        </x-ui.button>
    </form>
    <p class="mt-2 text-center text-xs text-ink/65">This course is full — join the waitlist and we'll promote you automatically when a place frees up.</p>

@else
    {{-- Open self-enrol, free --}}
    <form method="POST" action="{{ route('enrollment.store', $course) }}">
        @csrf
        <x-ui.button type="submit" class="w-full">
            {{ $mode === EnrollmentMode::Approval ? 'Request enrolment' : 'Enrol — start learning' }}
        </x-ui.button>
    </form>
    <p class="mt-2 text-center text-xs text-ink/65">
        {{ $mode === EnrollmentMode::Approval
            ? 'A staff member will review your request.'
            : 'Free for '.config('brand.short').' learners.' }}
    </p>
@endif
