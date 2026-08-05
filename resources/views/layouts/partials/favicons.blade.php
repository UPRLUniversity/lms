{{--
    Favicons / touch icons. Resolved through BrandAssets, so an icon uploaded in
    Settings → Branding replaces the packaged one everywhere with no code change.
    Each link is omitted entirely when neither an upload nor a packaged file exists,
    rather than emitting a URL that 404s on every page load.
--}}
@php
    $favicon = brand_assets()->faviconUrl();
    $faviconPng = brand_assets()->faviconPngUrl();
    $appleTouch = brand_assets()->appleTouchUrl();
@endphp

@if ($favicon)
    <link rel="icon" href="{{ $favicon }}" sizes="any">
@endif
@if ($faviconPng)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $faviconPng }}">
@endif
@if ($appleTouch)
    <link rel="apple-touch-icon" href="{{ $appleTouch }}">
@endif
