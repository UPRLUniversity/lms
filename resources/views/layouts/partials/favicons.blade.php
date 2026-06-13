{{-- Favicons / touch icons, sourced from config/brand.php so swapping files needs no code change. --}}
<link rel="icon" href="{{ asset(config('brand.icons.favicon')) }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset(config('brand.icons.favicon_png')) }}">
<link rel="apple-touch-icon" href="{{ asset(config('brand.icons.apple_touch')) }}">
