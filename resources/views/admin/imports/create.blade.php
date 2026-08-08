@php
    /** @var \App\Support\Import\ImportDefinition $definition */
    /** @var \Illuminate\Database\Eloquent\Model|null $scope */

    $params = array_filter(['import' => $definition->key(), 'scopeId' => $scopeId]);
@endphp

<x-app-layout :title="$definition->title()">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">{{ $definition->title() }}</h2>
            <p class="mt-1 text-ink/70">{{ $definition->intro() }}</p>
            @if ($scope)
                <p class="mt-2 text-sm text-ink/65">
                    For <span class="font-medium text-ink/80">{{ $scope->title ?? $scope->name }}</span>
                </p>
            @endif
        </div>

        @error('file')
            <div class="rounded-xl border border-crimson/30 bg-crimson/5 px-4 py-3 text-sm text-crimson">{{ $message }}</div>
        @enderror

        <x-ui.card>
            <ol class="mb-5 space-y-2 text-sm text-ink/70">
                <li class="flex gap-2"><span class="font-semibold text-crimson">1.</span> Download the template — it comes with worked examples you can overtype.</li>
                <li class="flex gap-2"><span class="font-semibold text-crimson">2.</span> Upload it. Every row is checked and shown to you before anything is saved.</li>
                <li class="flex gap-2"><span class="font-semibold text-crimson">3.</span> Confirm. Only the rows marked ready are imported; the rest are listed so you can fix them.</li>
            </ol>

            <div class="mb-5">
                <x-ui.button variant="secondary" size="sm" :href="route('admin.imports.template', $params)">
                    <x-ui.icon name="document-text" class="h-4 w-4" /> Download template
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('admin.imports.preview', $params) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="file" class="mb-1 block text-sm font-medium text-ink">Spreadsheet</label>
                    <input id="file" name="file" type="file" accept=".csv,.xlsx,.xls,text/csv" required
                           aria-describedby="file-hint"
                           class="block w-full rounded-xl border border-line bg-card text-sm text-ink shadow-sm file:mr-4 file:border-0 file:bg-crimson file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-white hover:file:bg-crimson-dark focus:border-crimson focus:ring-crimson">
                    <p id="file-hint" class="mt-1.5 text-xs text-ink/65">
                        .csv or .xlsx, up to 8 MB and {{ number_format(\App\Support\Import\SpreadsheetReader::MAX_ROWS) }} rows.
                        Columns are matched by heading, so their order doesn't matter.
                    </p>
                </div>

                <x-ui.button type="submit">
                    <x-ui.icon name="eye" class="h-5 w-5" /> Check the file
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- What the file needs. Rendered from the definition's own column list, so it
             can never drift out of step with what the reader actually accepts. --}}
        <x-ui.card :padding="false">
            <div class="border-b border-line px-5 py-3">
                <h3 class="font-display font-semibold text-ink">What the file needs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/65">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Column</th>
                            <th scope="col" class="px-5 py-3 font-medium">Required</th>
                            <th scope="col" class="px-5 py-3 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($definition->columns() as $column)
                            <tr>
                                <td class="px-5 py-3 align-top">
                                    <code class="rounded bg-ink/5 px-1.5 py-0.5 text-xs text-ink">{{ $column->key }}</code>
                                    <span class="mt-1 block text-xs text-ink/65">{{ $column->label }}</span>
                                </td>
                                <td class="px-5 py-3 align-top">
                                    @if ($column->required)
                                        <x-ui.badge variant="crimson">Required</x-ui.badge>
                                    @else
                                        <span class="text-xs text-ink/65">Optional</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 align-top text-ink/70">{{ $column->hint ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
