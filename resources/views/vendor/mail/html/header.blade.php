@props(['url'])
@php
    // Resolved through BrandAssets, so a logo uploaded in Settings reaches e-mail
    // with no change here. Embedded as a data URI rather than linked: a mail client
    // reading offline, or blocking remote images by default (most do), would
    // otherwise render the header empty. Falls back to a serif wordmark.
    $logo = brand_assets()->dataUri('white');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo)
<img src="{{ $logo }}" class="logo" alt="{{ config('brand.short') }}">
@else
{{ config('brand.short') }}
@endif
</a>
</td>
</tr>
