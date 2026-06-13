import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Lazy-load TinyMCE only on pages that actually use a rich editor, keeping it out
// of the main bundle.
if (document.querySelector('[data-rich-editor]')) {
    import('./rich-editor');
}
