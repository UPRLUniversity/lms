@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Support\Collection $announcements */
    $canPost = request()->user()->can('manageAnnouncements', $course);
@endphp

<div class="grid gap-6 lg:grid-cols-5">
    @if ($canPost)
        <div class="lg:order-2 lg:col-span-2">
            <div class="sticky top-20 rounded-2xl border border-line bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-crimson/10 text-crimson">
                        <x-ui.icon name="megaphone" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3 class="font-display font-semibold leading-tight text-ink">Post an announcement</h3>
                        <p class="text-xs text-ink/65">Enrolled students are notified in-app and by email.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('announcements.store', $course) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-ui.field name="title" label="Title" required :value="old('title')" placeholder="e.g. Reading week — no live session" />
                    <x-ui.rich-editor name="body" label="Message" :value="old('body')" height="200"
                                      placeholder="Share the update your students need to know…" required />
                    <x-ui.button type="submit" class="w-full justify-center">
                        <x-ui.icon name="megaphone" class="h-4 w-4" /> Post &amp; notify students
                    </x-ui.button>
                </form>
            </div>
        </div>
    @endif

    <div class="{{ $canPost ? 'lg:order-1 lg:col-span-3' : 'lg:col-span-5' }} space-y-4">
        @forelse ($announcements as $announcement)
            <article class="relative overflow-hidden rounded-2xl border border-line bg-card p-5 shadow-sm">
                <span class="absolute inset-y-0 left-0 w-1 bg-crimson/70" aria-hidden="true"></span>

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 class="font-display font-semibold leading-snug text-ink">{{ $announcement->title }}</h4>
                        <div class="mt-1.5 flex items-center gap-2">
                            <x-ui.avatar :user="$announcement->author" size="xs" />
                            <span class="text-xs text-ink/65">
                                {{ $announcement->author?->name ?? 'Instructor' }} · {{ $announcement->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                    @if ($canPost)
                        <form method="POST" action="{{ route('announcements.destroy', [$course, $announcement]) }}"
                              x-data
                              @submit.prevent="if (await window.uprlConfirm({ title: 'Remove this announcement?', text: 'Students keep any notification already sent.', confirmText: 'Yes, remove' })) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg p-1.5 text-ink/65 hover:bg-crimson/10 hover:text-crimson focus-ring" aria-label="Remove announcement">
                                <x-ui.icon name="trash" class="h-4 w-4" />
                            </button>
                        </form>
                    @endif
                </div>

                <x-ui.prose class="mt-3 text-sm" :html="$announcement->body" />
            </article>
        @empty
            <x-ui.empty-state icon="megaphone" title="No announcements yet"
                description="Post your first update — every enrolled student will be notified straight away." />
        @endforelse
    </div>
</div>
