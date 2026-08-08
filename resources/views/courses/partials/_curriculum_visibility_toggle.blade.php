@php
    /** @var string $type */
    /** @var mixed $model */
    /** @var bool $isHidden */

    $label = $isHidden
        ? 'Show this to students again'
        : 'Hide this from students, keeping their work';
@endphp

{{--
    Hide/restore — the safe alternative to deleting something students have worked on.
    One button, one endpoint, identical for all four curriculum types (Section 16).
--}}
<button type="button"
        data-action="toggle-visibility"
        data-item-type="{{ $type }}"
        data-item-id="{{ $model->id }}"
        data-hidden="{{ $isHidden ? '1' : '0' }}"
        class="rounded-lg p-1.5 focus-ring {{ $isHidden ? 'text-gold-ink hover:text-ink' : 'text-ink/65 hover:text-ink/70' }}"
        aria-label="{{ $label }}"
        title="{{ $label }}">
    <x-ui.icon :name="$isHidden ? 'eye' : 'eye-off'" class="h-4 w-4" />
</button>
