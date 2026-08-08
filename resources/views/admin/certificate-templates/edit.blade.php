@php
    use App\Enums\CertificateLayout;
    use App\Models\Media;

    /** @var \App\Models\CertificateTemplate $template */
    $isNew = ! $template->exists;

    $sigOne = old('signatory_one', $template->signatoryOne() ?? ['name' => '', 'title' => '', 'signature_media_id' => null]);
    $sigTwo = old('signatory_two', $template->signatoryTwo() ?? ['name' => '', 'title' => '', 'signature_media_id' => null]);

    $sigOneUrl = ($sigOne['signature_media_id'] ?? null) ? Media::find($sigOne['signature_media_id'])?->url : null;
    $sigTwoUrl = ($sigTwo['signature_media_id'] ?? null) ? Media::find($sigTwo['signature_media_id'])?->url : null;
@endphp

<x-app-layout :title="$isNew ? 'New certificate template' : $template->name">
    <div class="mx-auto max-w-4xl space-y-6"
         x-data="certificateTemplateEditor({
            uploadUrl: '{{ route('admin.certificate-templates.signature-upload') }}',
            signatoryOne: { name: @js($sigOne['name'] ?? ''), title: @js($sigOne['title'] ?? ''), signatureMediaId: {{ $sigOne['signature_media_id'] ?? 'null' }}, previewUrl: @js($sigOneUrl) },
            signatoryTwo: { name: @js($sigTwo['name'] ?? ''), title: @js($sigTwo['title'] ?? ''), signatureMediaId: {{ $sigTwo['signature_media_id'] ?? 'null' }}, previewUrl: @js($sigTwoUrl) },
         })">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('admin.certificate-templates.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Certificate templates
                </a>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $isNew ? 'New certificate template' : $template->name }}</h2>
            </div>
            @unless ($isNew)
                <x-ui.button variant="secondary" :href="route('admin.certificate-templates.preview', $template)" target="_blank" rel="noopener">
                    <x-ui.icon name="eye" class="h-4 w-4" /> Live preview
                </x-ui.button>
            @endunless
        </div>

        <form method="POST" action="{{ $isNew ? route('admin.certificate-templates.store') : route('admin.certificate-templates.update', $template) }}" class="space-y-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <x-ui.card>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="name" label="Template name" required autocomplete="off" :value="old('name', $template->name)" />

                    <x-ui.field name="layout" label="Layout" required>
                        <select id="layout" name="layout" class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach (CertificateLayout::cases() as $layout)
                                <option value="{{ $layout->value }}" @selected(old('layout', $template->layout?->value ?? 'classic') === $layout->value)>
                                    {{ $layout->label() }} — {{ $layout->description() }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="accent_color" label="Accent colour override" hint="Hex, e.g. #C8102E. Leave blank to use the layout's default.">
                        <input id="accent_color" name="accent_color" type="text" maxlength="7" placeholder="#C9A227" autocomplete="off"
                               value="{{ old('accent_color', $template->config['accent_color'] ?? '') }}"
                               class="block w-full rounded-xl border-line bg-card font-mono text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>

                    <div class="flex items-end pb-1.5">
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="hidden" name="show_grade" value="0">
                            <input type="checkbox" name="show_grade" value="1" class="rounded border-line text-crimson focus:ring-crimson"
                                   @checked(old('show_grade', $template->showGrade()))>
                            Print the achieved grade on the certificate
                        </label>
                    </div>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" class="rounded border-line text-crimson focus:ring-crimson"
                           @checked(old('is_default', $template->is_default))>
                    Make this the system default
                </label>
            </x-ui.card>

            {{-- Signatories --}}
            <x-ui.card>
                <h3 class="font-display font-semibold text-ink">Signatory 1</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-[1fr_1fr_auto]">
                    <x-ui.field name="signatory_one[name]" label="Name" id="signatory_one_name">
                        <input id="signatory_one_name" name="signatory_one[name]" x-model="one.name" autocomplete="off"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>
                    <x-ui.field name="signatory_one[title]" label="Title" id="signatory_one_title">
                        <input id="signatory_one_title" name="signatory_one[title]" x-model="one.title" autocomplete="off"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>
                    <div>
                        <span class="block text-sm font-medium text-ink" id="signatory_one_signature_label">Signature image</span>
                        <div class="mt-1.5 flex items-center gap-2">
                            <template x-if="one.previewUrl">
                                <img :src="one.previewUrl" alt="" class="h-10 w-auto rounded border border-line bg-white p-1">
                            </template>
                            {{-- sr-only, not hidden: a display:none input cannot be tabbed to, which made
                                 this the one control on the page a keyboard could not reach. --}}
                            <label class="cursor-pointer rounded-lg border border-dashed border-line px-3 py-2 text-xs font-medium text-ink/65 hover:border-crimson/40 hover:text-crimson focus-within:ring-2 focus-within:ring-crimson focus-within:ring-offset-2">
                                <span x-text="uploading.one ? 'Uploading…' : 'Upload'"></span>
                                <input type="file" accept="image/png,image/webp" class="sr-only"
                                       aria-labelledby="signatory_one_signature_label"
                                       aria-describedby="signature_requirements"
                                       @change="uploadSignature('one', $event)">
                            </label>
                            <button type="button" x-show="one.previewUrl" @click="clearSignature('one')"
                                    class="rounded-lg p-1.5 text-ink/65 hover:text-crimson focus-ring" aria-label="Remove signature image">
                                <x-ui.icon name="x" class="h-4 w-4" />
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs text-ink/65" id="signature_requirements">PNG or WebP, under 1MB. A transparent PNG about 600px wide sits best on the certificate.</p>
                    </div>
                </div>
                <input type="hidden" name="signatory_one[signature_media_id]" :value="one.signatureMediaId">
            </x-ui.card>

            <x-ui.card>
                <div class="flex items-center justify-between">
                    <h3 class="font-display font-semibold text-ink">Signatory 2 <span class="font-normal text-ink/65">(optional)</span></h3>
                    <button type="button" x-show="!hasTwo" @click="addSecondSignatory()"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-line px-3 py-1.5 text-sm font-medium text-ink/65 hover:border-crimson/40 hover:text-crimson focus-ring">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Add
                    </button>
                    <button type="button" x-show="hasTwo" @click="removeSecondSignatory()"
                            class="inline-flex items-center gap-1.5 rounded-lg p-1.5 text-ink/65 hover:text-crimson focus-ring" aria-label="Remove second signatory">
                        <x-ui.icon name="trash" class="h-4 w-4" />
                    </button>
                </div>
                <div class="mt-3 grid gap-4 sm:grid-cols-[1fr_1fr_auto]" x-show="hasTwo">
                    <x-ui.field name="signatory_two[name]" label="Name" id="signatory_two_name">
                        <input id="signatory_two_name" name="signatory_two[name]" x-model="two.name" autocomplete="off"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>
                    <x-ui.field name="signatory_two[title]" label="Title" id="signatory_two_title">
                        <input id="signatory_two_title" name="signatory_two[title]" x-model="two.title" autocomplete="off"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>
                    <div>
                        <span class="block text-sm font-medium text-ink" id="signatory_two_signature_label">Signature image</span>
                        <div class="mt-1.5 flex items-center gap-2">
                            <template x-if="two.previewUrl">
                                <img :src="two.previewUrl" alt="" class="h-10 w-auto rounded border border-line bg-white p-1">
                            </template>
                            <label class="cursor-pointer rounded-lg border border-dashed border-line px-3 py-2 text-xs font-medium text-ink/65 hover:border-crimson/40 hover:text-crimson focus-within:ring-2 focus-within:ring-crimson focus-within:ring-offset-2">
                                <span x-text="uploading.two ? 'Uploading…' : 'Upload'"></span>
                                <input type="file" accept="image/png,image/webp" class="sr-only"
                                       aria-labelledby="signatory_two_signature_label"
                                       aria-describedby="signature_requirements_two"
                                       @change="uploadSignature('two', $event)">
                            </label>
                            <button type="button" x-show="two.previewUrl" @click="clearSignature('two')"
                                    class="rounded-lg p-1.5 text-ink/65 hover:text-crimson focus-ring" aria-label="Remove signature image">
                                <x-ui.icon name="x" class="h-4 w-4" />
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs text-ink/65" id="signature_requirements_two">PNG or WebP, under 1MB. A transparent PNG about 600px wide sits best on the certificate.</p>
                    </div>
                </div>
                <input type="hidden" name="signatory_two[signature_media_id]" :value="hasTwo ? two.signatureMediaId : ''">
            </x-ui.card>

            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <x-ui.button type="button" variant="ghost" :href="route('admin.certificate-templates.index')">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $isNew ? 'Create template' : 'Save template' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
