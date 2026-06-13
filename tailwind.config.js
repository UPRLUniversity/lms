import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Brand colours map to the CSS custom properties defined once in
 * resources/css/app.css. Using rgb(var(--token) / <alpha-value>) keeps a single
 * source of truth while still supporting opacity modifiers (e.g. bg-crimson/10).
 */
const brand = (token) => `rgb(var(${token}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                crimson: {
                    DEFAULT: brand('--uprl-crimson'),
                    dark: brand('--uprl-crimson-dark'),
                },
                ink: brand('--uprl-ink'),
                surface: brand('--uprl-surface'),
                card: brand('--uprl-card'),
                success: brand('--uprl-green'),
                gold: brand('--uprl-gold'),
                line: brand('--uprl-border'),
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            borderColor: {
                DEFAULT: brand('--uprl-border'),
            },
            ringColor: {
                DEFAULT: brand('--uprl-crimson'),
            },
        },
    },

    plugins: [forms],
};
