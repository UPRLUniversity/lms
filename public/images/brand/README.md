# Brand assets — `public/images/brand/`

The official UPRL logo files live here. The app references them through
`config/brand.php` and the `<x-brand.logo>` component, which renders the file if
it exists and falls back to an inline "UPRL" monogram otherwise. **No code change
is needed when you swap these files** — just match the filenames below.

Logos are **background-aware**: pick the variant for the surface it sits on.

| Filename               | Variant            | Use on                                          |
| ---------------------- | ------------------ | ----------------------------------------------- |
| `uprl-logo-color.png`  | `color` (default)  | Light/cream surfaces (login form, sidebar)      |
| `uprl-logo-white.png`  | `white`            | Crimson / dark surfaces (auth brand panel)      |
| `uprl-mark.png`        | `mark`             | Compact spaces (collapsed sidebar rail)         |

Favicons wired into every layout `<head>`:

| Filename               | Use                                                   |
| ---------------------- | ----------------------------------------------------- |
| `favicon.ico`          | Classic browser-tab favicon                           |
| `favicon-32.png`       | 32×32 PNG favicon (modern browsers)                   |
| `apple-touch-icon.png` | 180×180 iOS home-screen icon                          |

## Guidance

- **Format & transparency:** PNG with a transparent background (current assets
  are transparent). SVG also works — update the extensions in `config/brand.php`.
- **Sizing:** logos are constrained by height in the views (don't upscale);
  the source PNGs are ~849×731 (lockups) and 512×512 (mark) for crisp retina.
- **Colour:** the brand crimson is `#C8102E`. The decorative sunburst motif in
  the UI is generated in code (`<x-brand.sunburst>`), separate from these files.
