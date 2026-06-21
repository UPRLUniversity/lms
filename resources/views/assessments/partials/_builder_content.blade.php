@php use App\Enums\QuestionDifficulty; @endphp

{{-- FIXED MODE --}}
<div x-show="mode === 'fixed'" class="grid gap-6 lg:grid-cols-2">
    {{-- Bank --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h3 class="font-display font-semibold text-ink">Question bank</h3>
            <a href="{{ route('questions.index', $course) }}" class="text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">Manage bank</a>
        </div>
        <input type="search" x-model="bankFilter.search" placeholder="Search the bank…"
               class="mb-3 block w-full rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
        <div class="max-h-[28rem] space-y-2 overflow-y-auto pr-1">
            <template x-for="q in filteredBank" :key="q.id">
                <button type="button" @click="toggle(q.id)"
                        class="flex w-full items-start gap-3 rounded-xl border p-3 text-left transition focus-ring"
                        :class="isSelected(q.id) ? 'border-crimson/40 bg-crimson/5' : 'border-line bg-card hover:border-ink/20'">
                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                          :class="isSelected(q.id) ? 'border-crimson bg-crimson text-white' : 'border-line text-transparent'">
                        <x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="3" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-ink" x-text="q.prompt"></span>
                        <span class="mt-0.5 block text-xs text-ink/45" x-text="`${q.type} · ${q.points} pts${q.category ? ' · ' + q.category : ''}`"></span>
                    </span>
                </button>
            </template>
            <p x-show="filteredBank.length === 0" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink/40">
                No questions. <a href="{{ route('questions.create', $course) }}" class="text-crimson">Add some to the bank.</a>
            </p>
        </div>
    </div>

    {{-- Selected --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <h3 class="font-display font-semibold text-ink">On this assessment</h3>
            <span class="text-sm text-ink/60"><span x-text="selected.length"></span> questions · <span class="font-medium text-ink" x-text="totalPoints"></span> pts</span>
        </div>
        <div x-ref="selectedList" class="space-y-2">
            <template x-for="s in selected" :key="s.id">
                <div :data-id="s.id" class="flex items-center gap-2 rounded-xl border border-line bg-card p-3">
                    <button type="button" data-drag class="cursor-grab rounded p-1 text-ink/30 hover:text-ink/60 focus-ring" aria-label="Drag to reorder">
                        <x-ui.icon name="arrows-up-down" class="h-4 w-4" />
                    </button>
                    <span class="min-w-0 flex-1 text-sm text-ink" x-text="bankById(s.id).prompt"></span>
                    <input type="number" min="0" step="0.5" x-model="s.points_override" @change="saveFixed()"
                           :placeholder="bankById(s.id).points"
                           class="w-16 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson" aria-label="Points override">
                    <button type="button" @click="remove(s.id)" class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </div>
        <p x-show="selected.length === 0" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink/40">
            Pick questions from the bank to add them here.
        </p>
        <p class="mt-2 text-xs text-ink/40" x-show="saving">Saving…</p>
    </div>
</div>

{{-- POOLED MODE --}}
<div x-show="mode === 'pooled'" x-cloak>
    <div class="mb-3 flex items-center justify-between">
        <div>
            <h3 class="font-display font-semibold text-ink">Pool rules</h3>
            <p class="text-sm text-ink/60">Each attempt draws a fresh random set obeying these rules.</p>
        </div>
        <x-ui.button type="button" size="sm" @click="addRule()"><x-ui.icon name="plus" class="h-4 w-4" /> Add rule</x-ui.button>
    </div>

    <div class="space-y-3">
        <template x-for="rule in rules" :key="rule.id">
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-card p-3">
                <select x-model="rule.category_id" @change="updateRule(rule)"
                        class="rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    @foreach ($bankCategories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select x-model="rule.difficulty" @change="updateRule(rule)"
                        class="rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">Any difficulty</option>
                    @foreach (QuestionDifficulty::cases() as $d)
                        <option value="{{ $d->value }}">{{ $d->label() }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <input type="number" min="1" x-model="rule.count" @change="updateRule(rule)"
                           class="w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson" aria-label="Count">
                    <span class="text-sm text-ink/50">questions</span>
                </div>
                <span class="text-xs" :class="ruleShort(rule) ? 'text-crimson' : 'text-ink/45'">
                    <span x-text="rule.available"></span> available
                </span>
                <button type="button" @click="deleteRule(rule)" class="ml-auto rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove rule">
                    <x-ui.icon name="trash" class="h-4 w-4" />
                </button>
            </div>
        </template>
        <p x-show="rules.length === 0" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink/40">
            No rules yet. Add one to define the pool.
        </p>
    </div>

    @if ($bankCategories->isEmpty())
        <p class="mt-3 text-sm text-crimson">Create question categories first — pooled selection draws from categories.</p>
    @endif
</div>
