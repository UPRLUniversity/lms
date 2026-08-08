{{--
    Live preview — both the with-grade and without-grade variants, rendered from the
    exact same blade view CertificateRenderer uses for the real PDF (embedded via an
    iframe so its full-page styles never collide with the admin shell's CSS). The
    iframe is a fixed-size A4-landscape canvas (1122×794, ~96dpi) scaled down with a
    CSS transform to fit the column width — so it reads as a crisp, contained card at
    any viewport instead of spilling into a horizontal scrollbar.
--}}
<x-app-layout :title="'Preview · '.$template->name">
    <div class="mx-auto max-w-6xl space-y-8">
        <div>
            <a href="{{ route('admin.certificate-templates.edit', $template) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-ink focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to {{ $template->name }}
            </a>
            <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Live preview</h2>
            <p class="mt-1 text-ink/70">Sample data — "Ada Lovelace" completing a demo course. This is exactly what the PDF renders.</p>
        </div>

        <div>
            <h3 class="mb-2 font-display font-semibold text-ink">With grade{{ $template->showGrade() ? '' : ' (toggle "show_grade" on to use this variant)' }}</h3>
            <div
                x-data="{
                    scale: 1,
                    fit() { this.scale = Math.min(1, this.$el.offsetWidth / 1122); },
                    init() { this.fit(); new ResizeObserver(() => this.fit()).observe(this.$el); },
                }"
                class="overflow-hidden rounded-xl border border-line bg-white shadow-sm"
                :style="{ height: (794 * scale) + 'px' }"
            >
                <iframe srcdoc="{{ $withGradeHtml }}" width="1122" height="794" class="origin-top-left border-0"
                        :style="{ transform: 'scale(' + scale + ')' }"
                        title="Certificate preview — with grade"></iframe>
            </div>
        </div>

        <div>
            <h3 class="mb-2 font-display font-semibold text-ink">Without grade</h3>
            <div
                x-data="{
                    scale: 1,
                    fit() { this.scale = Math.min(1, this.$el.offsetWidth / 1122); },
                    init() { this.fit(); new ResizeObserver(() => this.fit()).observe(this.$el); },
                }"
                class="overflow-hidden rounded-xl border border-line bg-white shadow-sm"
                :style="{ height: (794 * scale) + 'px' }"
            >
                <iframe srcdoc="{{ $withoutGradeHtml }}" width="1122" height="794" class="origin-top-left border-0"
                        :style="{ transform: 'scale(' + scale + ')' }"
                        title="Certificate preview — without grade"></iframe>
            </div>
        </div>
    </div>
</x-app-layout>
