# Brand assets — `public/images/brand/`

Drop the official UPRL logo files here. The app references them through
`config/brand.php` and the `<x-brand.logo>` component, which renders the file if
it exists and falls back to an inline "UPRL" monogram otherwise. **No code change
is needed when you add or swap these files** — just match the filenames below.

| Filename         | Used for                                              | Notes                                              |
| ---------------- | ----------------------------------------------------- | -------------------------------------------------- |
| `logo.svg`       | Primary lockup on light backgrounds (sidebar, guest)  | Dark text version. SVG strongly preferred.         |
| `logo-mark.svg`  | Standalone crest / sunburst mark (compact spaces)     | Square-ish; used when only the symbol fits.        |
| `logo-white.svg` | Reversed lockup on crimson / dark backgrounds         | White/knockout version for the guest brand panel.  |

## Guidance

- **Format:** SVG preferred (crisp at every size). PNG at 2× also works — if you
  use PNG, update the extensions in `config/brand.php`.
- **Colour:** the brand crimson is `#C8102E`. The decorative sunburst motif in
  the UI is generated in code (`resources/views/layouts/partials/sunburst.blade.php`),
  so the logo files only need the crest + wordmark.
- **Transparency:** export with a transparent background.
- After adding files, hard-refresh (assets are served from `public/` directly;
  no build step required for images).
