<x-app-layout :title="'Insights — '.$course->title">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to {{ $course->title }}
            </a>
            <h2 class="mt-2 font-display text-2xl font-semibold text-ink">Knowledge gain</h2>
            <p class="mt-1 text-sm text-ink/60">Average lift from pre- to post-module assessments, across students who attempted both.</p>
        </div>

        @if ($modules->isEmpty())
            <x-ui.empty-state icon="sparkles" title="No gain data yet"
                description="Attach a pre- and a post-module assessment to a module, and once students attempt both you'll see the class average here." />
        @else
            <div class="space-y-4">
                @foreach ($modules as $row)
                    @php $gain = $row['gain']; @endphp
                    <x-ui.card>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-display font-semibold text-ink">{{ $row['module']->title }}</h3>
                            <span class="text-xs text-ink/50">{{ $gain['students'] }} {{ \Illuminate\Support\Str::plural('student', $gain['students']) }}</span>
                        </div>
                        <div class="mt-4 flex items-center justify-center gap-5 text-center">
                            <div>
                                <p class="text-xs text-ink/50">Pre</p>
                                <p class="font-display text-2xl font-semibold text-ink">{{ $gain['pre'] }}%</p>
                            </div>
                            <x-ui.icon name="arrow-right" class="h-5 w-5 text-ink/30" />
                            <div>
                                <p class="text-xs text-ink/50">Post</p>
                                <p class="font-display text-2xl font-semibold text-ink">{{ $gain['post'] }}%</p>
                            </div>
                            <div class="ml-2 rounded-xl px-4 py-2 {{ $gain['gain'] >= 0 ? 'bg-success/15' : 'bg-crimson/10' }}">
                                <p class="text-xs {{ $gain['gain'] >= 0 ? 'text-success/80' : 'text-crimson/80' }}">Avg gain</p>
                                <p class="font-display text-2xl font-semibold {{ $gain['gain'] >= 0 ? 'text-success' : 'text-crimson' }}">{{ $gain['gain'] >= 0 ? '+' : '' }}{{ $gain['gain'] }}</p>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
