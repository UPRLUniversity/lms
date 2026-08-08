@props(['value'])

{{--
    One cell of an audit diff. Renders the recorded value legibly whatever shape it
    was stored in, and — importantly — makes "empty" visibly different from "the
    string 'empty'", so a field being cleared reads as a real change rather than a
    blank cell someone might mistake for a rendering fault.
--}}

@php
    $isBlank = $value === null || $value === '';
@endphp

@if ($isBlank)
    <span class="italic text-ink/65">empty</span>
@elseif (is_bool($value))
    <span>{{ $value ? 'Yes' : 'No' }}</span>
@elseif (is_array($value))
    <code class="block max-w-xs overflow-x-auto whitespace-pre-wrap break-words rounded bg-ink/5 px-1.5 py-1 text-xs">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code>
@else
    {{-- Long rich-text bodies are truncated: the trail records THAT a description
         changed; reading the whole thing is what the record itself is for. --}}
    <span class="break-words">{{ \Illuminate\Support\Str::limit(strip_tags((string) $value), 160) }}</span>
@endif
