import './bootstrap';
import './confirm'; // exposes window.uprlConfirm (branded SweetAlert2)

import Alpine from 'alpinejs';
import dataTable from './data-table';

window.Alpine = Alpine;

// Reusable live-table component (search/sort/filter/paginate without reloads).
Alpine.data('dataTable', dataTable);

Alpine.start();

// Lazy-load TinyMCE only on pages that actually use a rich editor, keeping it out
// of the main bundle.
if (document.querySelector('[data-rich-editor]')) {
    import('./rich-editor');
}
