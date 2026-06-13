<x-app-layout title="Dashboard">
    <div class="mx-auto max-w-7xl space-y-8">
        {{-- Greeting --}}
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">
                Welcome back, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') ?: auth()->user()->name }}
            </h2>
            <p class="mt-1 text-ink/70">Here's an overview of your learning. Let's keep the momentum going.</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.stat label="Courses in progress" value="0" icon="book" tone="crimson" />
            <x-ui.stat label="Completed" value="0" icon="check" tone="success" />
            <x-ui.stat label="Certificates earned" value="0" icon="certificate" tone="gold" />
        </div>

        {{-- Empty state --}}
        <x-ui.card :padding="false">
            <x-slot name="header">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-display text-lg font-semibold text-ink">Continue learning</h3>
                    <x-ui.badge variant="neutral">No courses yet</x-ui.badge>
                </div>
            </x-slot>

            <div class="p-5">
                <x-ui.empty-state
                    icon="graduation"
                    title="Your learning journey starts here"
                    description="Once you enrol in a course, your progress and next lessons will appear here.">
                    <x-slot name="action">
                        <x-ui.button variant="ghost" href="#" aria-disabled="true">
                            Browse courses
                        </x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
