<x-app-layout title="Bulk enrolment">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Bulk enrolment</h2>
            <p class="mt-1 text-ink/70">Enrol many students at once from a CSV of <code class="rounded bg-ink/5 px-1.5 py-0.5 text-sm">email,course_code</code>.</p>
        </div>

        @error('file')
            <div class="rounded-xl border border-crimson/30 bg-crimson/5 px-4 py-3 text-sm text-crimson">{{ $message }}</div>
        @enderror

        <x-ui.card>
            <ol class="mb-5 space-y-2 text-sm text-ink/70">
                <li class="flex gap-2"><span class="font-semibold text-crimson">1.</span> Download the template and fill in one <code class="rounded bg-ink/5 px-1 text-xs">email,course_code</code> per row.</li>
                <li class="flex gap-2"><span class="font-semibold text-crimson">2.</span> Upload it — we'll preview every row and flag any problems before anything is imported.</li>
                <li class="flex gap-2"><span class="font-semibold text-crimson">3.</span> Confirm to enrol the valid rows. Imports over 100 rows run in the background.</li>
            </ol>

            <div class="mb-5">
                <x-ui.button variant="secondary" size="sm" :href="route('enrollments.bulk.template')">
                    <x-ui.icon name="document-text" class="h-4 w-4" /> Download CSV template
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('enrollments.bulk.preview') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="file" class="mb-1 block text-sm font-medium text-ink">CSV file</label>
                    <input id="file" name="file" type="file" accept=".csv,text/csv" required
                           class="block w-full rounded-xl border border-line bg-card text-sm text-ink shadow-sm file:mr-4 file:border-0 file:bg-crimson file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-white hover:file:bg-crimson-dark focus:border-crimson focus:ring-crimson">
                    <p class="mt-1.5 text-xs text-ink/50">CSV up to 4 MB. The first row may be a header.</p>
                </div>

                <x-ui.button type="submit">
                    <x-ui.icon name="eye" class="h-5 w-5" /> Preview import
                </x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
