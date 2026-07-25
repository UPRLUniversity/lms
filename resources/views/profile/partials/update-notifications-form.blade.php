@php
    use App\Enums\NotificationType;

    /** @var \App\Models\User $user */
    $grouped = collect(NotificationType::cases())->groupBy(fn (NotificationType $t) => $t->category());
@endphp

<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Notifications') }}</h2>
        <p class="mt-1 text-sm text-ink/70">
            {{ __('Choose how you hear about enrolments, grading and course updates. A few critical alerts always reach your bell.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.notifications.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Digest card — the one opt-in that changes timing rather than a single type. --}}
        <label for="email_digest"
               class="flex cursor-pointer items-start gap-3 rounded-2xl border border-gold/30 bg-gold/[0.06] p-4 transition-colors hover:border-gold/50">
            <input type="hidden" name="email_digest" value="0">
            <input id="email_digest" type="checkbox" name="email_digest" value="1"
                   @checked(old('email_digest', $user->wantsEmailDigest()))
                   class="mt-0.5 rounded border-line text-crimson shadow-sm focus:ring-crimson">
            <span>
                <span class="flex items-center gap-2 text-sm font-semibold text-ink">
                    <x-ui.icon name="mail" class="h-4 w-4 text-gold-ink" />
                    {{ __('Bundle non-urgent emails into a daily digest') }}
                </span>
                <span class="mt-1 block text-xs leading-relaxed text-ink/60">
                    {{ __('Grades, certificates and announcements arrive once a day in a single email. Time-sensitive alerts — approvals, waitlist promotions, due-soon reminders — always send straight away.') }}
                </span>
            </span>
        </label>

        {{-- Per-type matrix --}}
        <div class="overflow-hidden rounded-2xl border border-line">
            <div class="grid grid-cols-[1fr,3.5rem,3.5rem] items-center gap-3 border-b border-line bg-surface/70 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-ink/45 sm:grid-cols-[1fr,4.5rem,4.5rem]">
                <span>{{ __('What happens') }}</span>
                <span class="text-center">{{ __('Email') }}</span>
                <span class="text-center">{{ __('Bell') }}</span>
            </div>

            @foreach ($grouped as $category => $types)
                <div class="border-b border-line last:border-0">
                    <p class="bg-surface/30 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink/35">{{ $category }}</p>

                    @foreach ($types as $type)
                        @php
                            $critical = $type->isCritical();
                            $email = old("email.{$type->value}", $user->notifiesByEmail($type));
                            $inApp = old("in_app.{$type->value}", $user->notifiesInApp($type));
                            $tile = NotificationType::toneClasses($type->tone());
                        @endphp
                        <div class="grid grid-cols-[1fr,3.5rem,3.5rem] items-center gap-3 px-4 py-2.5 transition-colors hover:bg-surface/40 sm:grid-cols-[1fr,4.5rem,4.5rem]">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tile }}">
                                    <x-ui.icon :name="$type->icon()" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-ink/85">{{ $type->label() }}</span>
                                    @if ($critical)
                                        <span class="text-[11px] text-ink/40">{{ __('Bell alert always on') }}</span>
                                    @endif
                                </span>
                            </span>
                            <span class="flex justify-center">
                                <input type="checkbox" name="email[{{ $type->value }}]" value="1"
                                       @checked($email)
                                       class="h-4 w-4 rounded border-line text-crimson shadow-sm focus:ring-crimson"
                                       aria-label="{{ __('Email me about :type', ['type' => $type->label()]) }}">
                            </span>
                            <span class="flex justify-center">
                                <input type="checkbox" name="in_app[{{ $type->value }}]" value="1"
                                       @checked($inApp) @disabled($critical)
                                       class="h-4 w-4 rounded border-line text-crimson shadow-sm focus:ring-crimson disabled:cursor-not-allowed disabled:opacity-50"
                                       @if ($critical) title="{{ __('This alert can’t be turned off in the bell') }}" @endif
                                       aria-label="{{ __('Show :type in my notification bell', ['type' => $type->label()]) }}">
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save preferences') }}</x-primary-button>

            @if (session('status') === 'notifications-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-success">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
