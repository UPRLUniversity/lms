@props(['url'])
@php
    // Use the real brand logo if the asset exists, otherwise a serif wordmark.
    $logoPath = public_path(config('brand.logos.white'));
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (is_file($logoPath))
<img src="{{ asset(config('brand.logos.white')) }}" class="logo" alt="{{ config('brand.short') }}">
@else
{{ config('brand.short') }}
@endif
</a>
</td>
</tr>
