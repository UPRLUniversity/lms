@php
    use App\Enums\EnrollmentMode;
    use App\Enums\EnrollmentStatus;

    /** @var \App\Models\Course $course */
    /** @var \App\Models\Enrollment|null $enrollment */
    $mode = $course->enrollmentMode();
    $seatsTaken = $course->seatsTaken();
    $full = $course->isFull();
    $windowFuture = $course->enrollmentOpensInFuture();
    $windowClosed = $course->enrollmentHasClosed();
    $status = $enrollment?->status;
@endphp

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
    <p class="mt-2 text-center text-xs text-ink/50">{{ $mode->label() }}</p>

@elseif (! auth()->check())
    {{-- Guest --}}
    <x-ui.button class="w-full" :href="route('register')">Create an account to enrol</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/50">
        Already a member? <a href="{{ route('login') }}" class="text-crimson hover:underline focus-ring rounded">Log in</a>
    </p>

@elseif ($status === EnrollmentStatus::Active || $status === EnrollmentStatus::Completed)
    {{-- Already enrolled --}}
    <div class="mb-3 flex items-center justify-center gap-2 rounded-xl bg-success/10 px-4 py-2.5 text-sm font-medium text-success">
        <x-ui.icon name="check" class="h-4 w-4" stroke-width="2.5" />
        {{ $status === EnrollmentStatus::Completed ? 'You completed this course' : "You're enrolled" }}
    </div>
    <x-ui.button class="w-full" :href="route('learn.resume', $course)">
        {{ $status === EnrollmentStatus::Completed ? 'Revisit the course' : 'Continue learning' }}
    </x-ui.button>

@elseif ($status === EnrollmentStatus::Pending)
    {{-- Awaiting approval --}}
    <div class="flex items-center justify-center gap-2 rounded-xl bg-gold/15 px-4 py-3 text-sm font-medium text-gold">
        <x-ui.icon name="clock" class="h-4 w-4" /> Awaiting approval
    </div>
    <p class="mt-2 text-center text-xs text-ink/60">We'll email you once a staff member reviews your request.</p>

@elseif ($status === EnrollmentStatus::Waitlisted)
    {{-- On the waitlist --}}
    <div class="flex items-center justify-center gap-2 rounded-xl bg-ink/5 px-4 py-3 text-sm font-medium text-ink">
        <x-ui.icon name="users" class="h-4 w-4" /> You're #{{ $enrollment->waitlistPosition() }} on the waitlist
    </div>
    <form method="POST" action="{{ route('enrollment.withdraw', $enrollment) }}" class="mt-3"
          x-data
          @submit.prevent="if (await window.uprlConfirm({ title: 'Leave the waitlist?', confirmText: 'Yes, leave' })) $el.submit()">
        @csrf @method('DELETE')
        <button type="submit" class="w-full text-center text-xs text-ink/50 hover:text-crimson focus-ring rounded py-1">
            Leave the waitlist
        </button>
    </form>

@elseif ($mode === EnrollmentMode::InviteOnly)
    {{-- Invite-only: no self-enrol --}}
    <div class="flex items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-3 text-sm font-medium text-ink/70">
        <x-ui.icon name="shield" class="h-4 w-4" /> Enrolment by invitation
    </div>
    <p class="mt-2 text-center text-xs text-ink/60">An administrator adds students to this course directly.</p>

@elseif ($windowFuture)
    {{-- Window not open yet --}}
    <x-ui.button class="w-full" disabled>Enrolment opens {{ $course->enrollment_opens_at->isoFormat('D MMM') }}</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/60">Check back soon to claim your place.</p>

@elseif ($windowClosed)
    {{-- Window closed --}}
    <x-ui.button class="w-full" disabled>Enrolment closed</x-ui.button>
    <p class="mt-2 text-center text-xs text-ink/60">Enrolment for this course has ended.</p>

@elseif ($full)
    {{-- Full → waitlist --}}
    <form method="POST" action="{{ route('enrollment.store', $course) }}">
        @csrf
        <x-ui.button type="submit" variant="secondary" class="w-full">
            <x-ui.icon name="users" class="h-5 w-5" /> Join the waitlist
        </x-ui.button>
    </form>
    <p class="mt-2 text-center text-xs text-ink/60">This course is full — join the waitlist and we'll promote you automatically when a place frees up.</p>

@else
    {{-- Open self-enrol --}}
    <form method="POST" action="{{ route('enrollment.store', $course) }}">
        @csrf
        <x-ui.button type="submit" class="w-full">
            {{ $mode === EnrollmentMode::Approval ? 'Request enrolment' : 'Enrol — start learning' }}
        </x-ui.button>
    </form>
    <p class="mt-2 text-center text-xs text-ink/60">
        {{ $mode === EnrollmentMode::Approval
            ? 'A staff member will review your request.'
            : "Free for ".config('brand.short')." learners." }}
    </p>
@endif
