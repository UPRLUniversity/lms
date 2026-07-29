/**
 * Branded Chart.js bootstrapping.
 *
 * A page opts in by rendering the <x-ui.chart> component, which emits a
 * <canvas data-chart> carrying its type + data as JSON. We lazy-load Chart.js
 * (kept out of the main bundle, mirroring the TinyMCE pattern) only when at least
 * one chart is present, pull the palette straight from the UPRL CSS custom
 * properties so brand colour lives in ONE place, and disable animation when the
 * viewer prefers reduced motion.
 */

/**
 * Read a --uprl-* custom property off :root and return a CSS colour Chart.js can parse.
 * The brand tokens are stored as bare "R G B" channel triplets (so Tailwind's
 * `rgb(var(--token) / <alpha>)` opacity modifiers work), which Chart.js can't read
 * directly — so a triplet is wrapped as `rgb(R G B)`. Falls back to a hex when the var
 * is absent.
 */
function token(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    if (!value) {
        return fallback;
    }

    return /^\d+\s+\d+\s+\d+$/.test(value) ? `rgb(${value})` : value;
}

/** A translucent variant of a brand token — for chart fills/gridlines. */
function alpha(name, a) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return /^\d+\s+\d+\s+\d+$/.test(value) ? `rgb(${value} / ${a})` : value;
}

/** The brand palette, resolved once per render. */
function palette() {
    return {
        crimson: token('--uprl-crimson', '#C8102E'),
        crimsonDark: token('--uprl-crimson-dark', '#9E0B22'),
        green: token('--uprl-green', '#0F6B3E'),
        gold: token('--uprl-gold', '#C9A227'),
        ink: token('--uprl-ink', '#1C1917'),
        border: token('--uprl-border', '#E7E5E4'),
    };
}

/**
 * Translate a named brand tone to a concrete colour. Covers both the chart-level tones
 * ("crimson", "green", …) and the app's semantic tone names that grade bands are stored
 * as ("success", "neutral", …), so a band's colour renders as intended rather than
 * falling through to Chart.js's default. Unknown values (e.g. a raw hex) pass through.
 */
function resolveColor(value, p) {
    return {
        crimson: p.crimson,
        'crimson-dark': p.crimsonDark,
        green: p.green,
        success: p.green,
        gold: p.gold,
        ink: p.ink,
        neutral: alpha('--uprl-ink', 0.4),
    }[value] ?? value;
}

/**
 * Apply brand defaults + resolve any named tones inside a dataset. Mutates a
 * shallow copy so the server-provided config stays declarative.
 */
function brandConfig(raw, Chart, p) {
    Chart.defaults.font.family =
        "Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";
    Chart.defaults.color = p.ink;
    Chart.defaults.borderColor = p.border;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const gridColor = alpha('--uprl-ink', 0.06);
    const tickColor = alpha('--uprl-ink', 0.55);

    const config = JSON.parse(JSON.stringify(raw));
    config.options = config.options || {};
    config.options.responsive = true;
    config.options.maintainAspectRatio = false;
    if (reduceMotion) {
        config.options.animation = false;
    }

    // Quiet, branded cartesian axes (bar/line) — light horizontal guides, no heavy
    // frame, no vertical grid. Per-chart options still win via the merge order.
    if (config.type === 'bar' || config.type === 'line') {
        config.options.scales = config.options.scales || {};
        for (const axisId of ['x', 'y']) {
            const axis = (config.options.scales[axisId] = config.options.scales[axisId] || {});
            axis.grid = {
                color: gridColor,
                drawTicks: false,
                display: axisId === 'y',
                ...(axis.grid || {}),
            };
            axis.border = { display: false, ...(axis.border || {}) };
            axis.ticks = { color: tickColor, padding: 8, ...(axis.ticks || {}) };
        }
    }

    (config.data?.datasets || []).forEach((ds) => {
        if (config.type === 'bar') {
            ds.categoryPercentage = ds.categoryPercentage ?? 0.7;
            ds.barPercentage = ds.barPercentage ?? 0.85;
        }
        if (typeof ds.backgroundColor === 'string') {
            ds.backgroundColor = resolveColor(ds.backgroundColor, p);
        } else if (Array.isArray(ds.backgroundColor)) {
            ds.backgroundColor = ds.backgroundColor.map((c) => resolveColor(c, p));
        }
        if (typeof ds.borderColor === 'string') {
            ds.borderColor = resolveColor(ds.borderColor, p);
        } else if (Array.isArray(ds.borderColor)) {
            ds.borderColor = ds.borderColor.map((c) => resolveColor(c, p));
        }
    });

    return config;
}

export default async function initCharts() {
    const canvases = document.querySelectorAll('canvas[data-chart]:not([data-chart-ready])');
    if (canvases.length === 0) {
        return;
    }

    const { default: Chart } = await import('chart.js/auto');
    const p = palette();

    canvases.forEach((canvas) => {
        let raw;
        try {
            raw = JSON.parse(canvas.dataset.chart);
        } catch {
            return;
        }
        canvas.setAttribute('data-chart-ready', 'true');
        new Chart(canvas, brandConfig(raw, Chart, p));
    });
}
