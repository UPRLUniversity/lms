<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Styleguide · {{ config('brand.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">
        @php
            $tokens = [
                ['Crimson', 'bg-crimson', '#C8102E'],
                ['Crimson dark', 'bg-crimson-dark', '#9E0B22'],
                ['Ink', 'bg-ink', '#1C1917'],
                ['Surface', 'bg-surface border border-line', '#FAF9F6'],
                ['Card', 'bg-card border border-line', '#FFFFFF'],
                ['Success', 'bg-success', '#0F6B3E'],
                ['Gold', 'bg-gold', '#C9A227'],
                ['Border', 'bg-line', '#E7E5E4'],
            ];
        @endphp

        <header class="border-b border-line bg-card">
            <div class="mx-auto max-w-5xl px-6 py-8">
                <x-brand.logo variant="color" class="h-12 w-auto" />
                <h1 class="mt-4 font-display text-3xl font-semibold text-ink">Design system</h1>
                <p class="mt-1 text-ink/70">The living reference for UPRL LMS brand tokens and UI components. Dev-only.</p>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-16 px-6 py-12">

            {{-- Logo variants --}}
            <section aria-labelledby="sg-logo">
                <h2 id="sg-logo" class="font-display text-xl font-semibold text-ink">Logo variants</h2>
                <p class="mt-1 text-sm text-ink/70">Background-aware. Use <code>color</code> on light surfaces, <code>white</code> on crimson/dark, <code>mark</code> when space is tight.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-line bg-surface p-6">
                        <x-brand.logo variant="color" class="h-16 w-auto" />
                        <span class="text-xs text-ink/70">color · on light</span>
                    </div>
                    <div class="flex flex-col items-center gap-3 rounded-xl bg-gradient-to-br from-crimson to-crimson-dark p-6">
                        <x-brand.logo variant="white" class="h-16 w-auto" />
                        <span class="text-xs text-white/80">white · on crimson</span>
                    </div>
                    <div class="flex flex-col items-center gap-3 rounded-xl border border-line bg-surface p-6">
                        <x-brand.logo variant="mark" class="h-16 w-16" />
                        <span class="text-xs text-ink/70">mark · compact</span>
                    </div>
                </div>
            </section>

            {{-- Colour tokens --}}
            <section aria-labelledby="sg-tokens">
                <h2 id="sg-tokens" class="font-display text-xl font-semibold text-ink">Colour tokens</h2>
                <p class="mt-1 text-sm text-ink/70">Defined once in <code>resources/css/app.css</code>. Never hard-code hex in views.</p>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($tokens as [$name, $class, $hex])
                        <div>
                            <div class="h-16 w-full rounded-xl {{ $class }}"></div>
                            <p class="mt-2 text-sm font-medium text-ink">{{ $name }}</p>
                            <p class="text-xs text-ink/70">{{ $hex }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Typography --}}
            <section aria-labelledby="sg-type">
                <h2 id="sg-type" class="font-display text-xl font-semibold text-ink">Typography</h2>
                <div class="mt-5 space-y-3">
                    <p class="font-display text-4xl font-semibold text-ink">Fraunces display heading</p>
                    <p class="text-lg text-ink">Inter is used for all UI and body copy, with generous line-height for comfortable reading.</p>
                    <p class="text-sm text-ink/70">Muted secondary text sits at 70% ink (AA-compliant on the surface tone).</p>
                </div>
            </section>

            {{-- Buttons --}}
            <section aria-labelledby="sg-buttons">
                <h2 id="sg-buttons" class="font-display text-xl font-semibold text-ink">Buttons</h2>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                    <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                    <x-ui.button variant="primary" href="#">As link</x-ui.button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-ui.button size="sm">Small</x-ui.button>
                    <x-ui.button size="md">Medium</x-ui.button>
                    <x-ui.button size="lg">Large</x-ui.button>
                    <x-ui.button size="md"><x-ui.icon name="check" class="h-4 w-4" /> With icon</x-ui.button>
                </div>
            </section>

            {{-- Badges --}}
            <section aria-labelledby="sg-badges">
                <h2 id="sg-badges" class="font-display text-xl font-semibold text-ink">Badges</h2>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <x-ui.badge variant="neutral">Draft</x-ui.badge>
                    <x-ui.badge variant="crimson">Active</x-ui.badge>
                    <x-ui.badge variant="success">Published</x-ui.badge>
                    <x-ui.badge variant="gold">Certificate</x-ui.badge>
                </div>
            </section>

            {{-- Cards --}}
            <section aria-labelledby="sg-cards">
                <h2 id="sg-cards" class="font-display text-xl font-semibold text-ink">Cards</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.card>
                        <h3 class="font-display text-lg font-semibold text-ink">Simple card</h3>
                        <p class="mt-1 text-sm text-ink/70">Cards use one radius (rounded-xl), one border and one shadow.</p>
                    </x-ui.card>

                    <x-ui.card :padding="false">
                        <x-slot name="header">
                            <h3 class="font-display text-base font-semibold text-ink">With header & footer</h3>
                        </x-slot>
                        <div class="px-5 py-5">
                            <p class="text-sm text-ink/70">Header and footer slots are optional.</p>
                        </div>
                        <x-slot name="footer">
                            <x-ui.button size="sm">Footer action</x-ui.button>
                        </x-slot>
                    </x-ui.card>
                </div>
            </section>

            {{-- Stats --}}
            <section aria-labelledby="sg-stats">
                <h2 id="sg-stats" class="font-display text-xl font-semibold text-ink">Stat tiles</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <x-ui.stat label="Active courses" value="12" icon="book" tone="crimson" />
                    <x-ui.stat label="Completed" value="48" icon="check" tone="success" />
                    <x-ui.stat label="Certificates" value="7" icon="certificate" tone="gold" />
                </div>
            </section>

            {{-- Form fields --}}
            <section aria-labelledby="sg-fields">
                <h2 id="sg-fields" class="font-display text-xl font-semibold text-ink">Form fields</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="sg_name" label="Full name" placeholder="Ada Lovelace" required />
                    <x-ui.field name="sg_email" label="Email" type="email" hint="We'll never share it." placeholder="you@uprl.edu.ng" />

                    {{-- Password field with show/hide toggle, in an error state --}}
                    <div class="space-y-1.5">
                        <label for="sg_err" class="block text-sm font-medium text-ink">Password</label>
                        <x-ui.password id="sg_err" name="sg_password" :invalid="true" aria-describedby="sg_err-error" />
                        <p id="sg_err-error" class="text-sm text-crimson">Password must be at least 8 characters.</p>
                    </div>

                    {{-- Custom control in slot --}}
                    <x-ui.field name="sg_role" label="Role">
                        <select id="sg_role" name="sg_role" class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option>Student</option>
                            <option>Instructor</option>
                            <option>Admin</option>
                        </select>
                    </x-ui.field>
                </div>
            </section>

            {{-- Modal --}}
            <section aria-labelledby="sg-modal">
                <h2 id="sg-modal" class="font-display text-xl font-semibold text-ink">Modal</h2>
                <div class="mt-5">
                    <x-ui.button x-data="" x-on:click="$dispatch('open-modal', 'sg-demo')">Open modal</x-ui.button>

                    <x-ui.modal name="sg-demo" title="Confirm enrolment" maxWidth="md">
                        <p class="text-sm text-ink/70">This is an accessible dialog with a focus trap, labelled title and Escape-to-close.</p>
                        <x-slot name="footer">
                            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'sg-demo')">Cancel</x-ui.button>
                            <x-ui.button x-on:click="$dispatch('close-modal', 'sg-demo')">Confirm</x-ui.button>
                        </x-slot>
                    </x-ui.modal>
                </div>
            </section>

            {{-- Empty state --}}
            <section aria-labelledby="sg-empty">
                <h2 id="sg-empty" class="font-display text-xl font-semibold text-ink">Empty state</h2>
                <div class="mt-5">
                    <x-ui.empty-state
                        icon="book"
                        title="No courses yet"
                        description="When courses are added they will appear here, ready to explore.">
                        <x-slot name="action">
                            <x-ui.button>Create your first course</x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                </div>
            </section>

            {{-- Icons --}}
            <section aria-labelledby="sg-icons">
                <h2 id="sg-icons" class="font-display text-xl font-semibold text-ink">Icons</h2>
                <div class="mt-5 flex flex-wrap gap-4 text-ink/70">
                    @foreach (['home','book','graduation','users','chart','cog','bell','user','check','certificate','inbox','logout'] as $icon)
                        <span class="flex flex-col items-center gap-1">
                            <x-ui.icon :name="$icon" class="h-6 w-6" />
                            <span class="text-[10px] text-ink/40">{{ $icon }}</span>
                        </span>
                    @endforeach
                </div>
            </section>
        </main>
    </body>
</html>
