@php
    $config = [
        'items' => $items,
        'answerUrl' => route('attempts.answer', $attempt),
        'submitUrl' => route('attempts.submit', $attempt),
        'resultUrl' => route('attempts.result', $attempt),
        'remainingSeconds' => $remainingSeconds,
    ];
@endphp

<x-learn-layout :title="$assessment->title">
    <div class="min-h-screen" x-data="attemptRunner(@js($config))" x-init="init()">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-line bg-card/95 backdrop-blur">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate font-display text-sm font-semibold text-ink">{{ $assessment->title }}</p>
                    <p class="text-xs text-ink/50"><span x-text="answeredCount"></span> of <span x-text="total"></span> answered</p>
                </div>
                <div class="flex items-center gap-3">
                    <template x-if="timed">
                        <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold tabular-nums"
                              :class="clockUrgent ? 'bg-crimson/10 text-crimson' : 'bg-surface text-ink/70'">
                            <x-ui.icon name="clock" class="h-4 w-4" /> <span x-text="clock"></span>
                        </span>
                    </template>
                    <x-ui.button size="sm" @click="submit()" x-bind:disabled="submitting">Submit</x-ui.button>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-4 py-6">
            {{-- Progress map --}}
            <div class="mb-5 flex flex-wrap gap-1.5" role="group" aria-label="Question navigator">
                <template x-for="(it, i) in items" :key="it.question_id">
                    <button type="button" @click="go(i)"
                            class="relative h-9 w-9 rounded-lg text-sm font-medium transition focus-ring"
                            :class="{
                                'ring-2 ring-crimson ring-offset-1': index === i,
                                'bg-success/15 text-success': statusOf(i) === 'answered',
                                'bg-gold/20 text-gold-ink': statusOf(i) === 'flagged',
                                'bg-ink/5 text-ink/50': statusOf(i) === 'skipped',
                            }"
                            :aria-label="`Question ${i + 1}, ${statusOf(i)}`"
                            x-text="i + 1"></button>
                </template>
            </div>

            {{-- Questions (one shown at a time) --}}
            <template x-for="(item, i) in items" :key="item.question_id">
                <div x-show="index === i" id="lesson-content">
                    <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink/45">Question <span x-text="i + 1"></span> of <span x-text="total"></span></p>
                            <button type="button" @click="toggleFlag(item.question_id)"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium focus-ring"
                                    :class="flags[item.question_id] ? 'bg-gold/20 text-gold-ink' : 'text-ink/45 hover:bg-surface'">
                                <x-ui.icon name="flag" class="h-3.5 w-3.5" /> <span x-text="flags[item.question_id] ? 'Flagged' : 'Flag'"></span>
                            </button>
                        </div>

                        <div class="uprl-prose mt-3 max-w-none text-ink" x-html="item.prompt"></div>
                        <template x-if="item.image_url">
                            <img :src="item.image_url" alt="" class="mt-3 max-h-72 rounded-xl border border-line">
                        </template>

                        {{-- Controls by type --}}
                        <div class="mt-5">
                            {{-- Option types (mcq single/multi, true/false) --}}
                            <template x-if="item.options">
                                <div class="space-y-2">
                                    <template x-for="opt in item.options" :key="opt.id">
                                        <button type="button"
                                                @click="item.multiple ? toggleMulti(item.question_id, opt.id) : setSingle(item.question_id, opt.id)"
                                                class="flex w-full items-center gap-3 rounded-xl border p-3.5 text-left transition focus-ring"
                                                :class="isChosen(item.question_id, opt.id) ? 'border-crimson bg-crimson/5' : 'border-line hover:border-ink/25'">
                                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center border"
                                                  :class="[item.multiple ? 'rounded-md' : 'rounded-full', isChosen(item.question_id, opt.id) ? 'border-crimson bg-crimson text-white' : 'border-ink/30 text-transparent']">
                                                <x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="3" />
                                            </span>
                                            <span class="text-sm text-ink" x-text="opt.text"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- Fill blank --}}
                            <template x-if="item.type === 'fill_blank'">
                                <input type="text" x-model="responses[item.question_id]" @input.debounce.500ms="save(item.question_id)"
                                       placeholder="Type your answer…"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            </template>

                            {{-- Essay --}}
                            <template x-if="item.type === 'essay'">
                                <textarea x-model="responses[item.question_id]" @input.debounce.700ms="save(item.question_id)" rows="8"
                                          placeholder="Write your answer…"
                                          class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"></textarea>
                            </template>

                            {{-- Matching --}}
                            <template x-if="item.type === 'matching'">
                                <div class="space-y-2">
                                    <template x-for="left in item.lefts" :key="left.id">
                                        <div class="flex items-center gap-3 rounded-xl border border-line p-3">
                                            <span class="min-w-0 flex-1 text-sm text-ink" x-text="left.text"></span>
                                            <x-ui.icon name="arrows-right-left" class="h-4 w-4 shrink-0 text-ink/30" />
                                            <select @change="setMatch(item.question_id, left.id, $event.target.value)"
                                                    class="rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                <option value="">Choose…</option>
                                                <template x-for="right in item.rights" :key="right.token">
                                                    <option :value="right.token" :selected="(responses[item.question_id] || {})[left.id] === right.token" x-text="right.text"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Scenario --}}
                            <template x-if="item.type === 'scenario'">
                                <div class="space-y-5">
                                    <template x-for="(sub, si) in item.subs" :key="sub.id">
                                        <div class="rounded-xl border border-line bg-surface/40 p-4">
                                            <div class="uprl-prose max-w-none text-sm text-ink" x-html="`<strong>Part ${si + 1}.</strong> ` + sub.prompt"></div>
                                            <div class="mt-3">
                                                <template x-if="sub.options">
                                                    <div class="space-y-2">
                                                        <template x-for="opt in sub.options" :key="opt.id">
                                                            <button type="button"
                                                                    @click="sub.multiple ? scenarioMulti(item.question_id, sub.id, opt.id) : scenarioSingle(item.question_id, sub.id, opt.id)"
                                                                    class="flex w-full items-center gap-3 rounded-lg border p-2.5 text-left text-sm focus-ring"
                                                                    :class="(() => { const v = (responses[item.question_id]||{})[sub.id]; return Array.isArray(v) ? v.includes(opt.id) : v === opt.id; })() ? 'border-crimson bg-crimson/5' : 'border-line hover:border-ink/25'">
                                                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border border-ink/30"></span>
                                                                <span x-text="opt.text"></span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="sub.type === 'fill_blank'">
                                                    <input type="text" :value="(responses[item.question_id]||{})[sub.id] || ''"
                                                           @input.debounce.500ms="setScenario(item.question_id, sub.id, $event.target.value)"
                                                           placeholder="Answer…" class="block w-full rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                </template>
                                                <template x-if="sub.type === 'essay'">
                                                    <textarea rows="4" :value="(responses[item.question_id]||{})[sub.id] || ''"
                                                              @input.debounce.700ms="setScenario(item.question_id, sub.id, $event.target.value)"
                                                              placeholder="Answer…" class="block w-full rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson"></textarea>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Nav --}}
                    <div class="mt-5 flex items-center justify-between">
                        <x-ui.button variant="secondary" @click="prev()" x-bind:disabled="index === 0">
                            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Previous
                        </x-ui.button>
                        <template x-if="index < total - 1">
                            <x-ui.button @click="next()">Next <x-ui.icon name="arrow-right" class="h-4 w-4" /></x-ui.button>
                        </template>
                        <template x-if="index === total - 1">
                            <x-ui.button @click="submit()" x-bind:disabled="submitting">Submit attempt</x-ui.button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-learn-layout>
