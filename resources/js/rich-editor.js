// The single TinyMCE setup for the whole app. Self-hosted OSS (GPL) build, bundled
// through Vite — no cloud API key. Lazy-loaded by app.js only on pages that contain
// a [data-rich-editor] element (see resources/js/app.js).

import tinymce from 'tinymce';

// Expose the instance so the course builder can sync lesson content into the
// editor (setContent on open) and flush it back (save) before an AJAX submit.
window.tinymce = tinymce;

// Core: model, theme, icons.
import 'tinymce/models/dom';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';

// Plugins for the academic toolbar.
import 'tinymce/plugins/advlist';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/link';
import 'tinymce/plugins/image';
import 'tinymce/plugins/table';
import 'tinymce/plugins/code';
import 'tinymce/plugins/autoresize';

// Self-hosted skin + content CSS, bundled as strings (no HTTP fetch, no skin URL).
import skinCss from 'tinymce/skins/ui/oxide/skin.min.css?inline';
import contentUiCss from 'tinymce/skins/ui/oxide/content.min.css?inline';
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';

const TOOLBAR = {
    full: 'undo redo | blocks | bold italic underline | bullist numlist | blockquote link image table | code',
    basic: 'undo redo | bold italic underline | bullist numlist | blockquote link',
};

const PLUGINS = {
    full: 'advlist lists link image table code autoresize',
    basic: 'advlist lists link autoresize',
};

// Mirror the mews/purifier allow-lists (config/purifier.php) so what the editor
// produces matches what survives sanitization on save.
const VALID_ELEMENTS = {
    full: 'p,br,hr,h2,h3,h4,strong/b,em/i,u,s,ul,ol,li,blockquote,pre,code,a[href|title|target|rel],img[src|alt|width|height],table,thead,tbody,tr,th,td',
    basic: 'p,br,strong/b,em/i,u,ul,ol,li,blockquote,code,a[href|title]',
};

function injectSkin() {
    if (document.getElementById('tinymce-skin-css')) return;
    const style = document.createElement('style');
    style.id = 'tinymce-skin-css';
    style.textContent = skinCss;
    document.head.appendChild(style);
}

function imagesUploadHandler(uploadUrl, csrf) {
    return (blobInfo) =>
        new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('file', blobInfo.blob(), blobInfo.filename());

            fetch(uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: data,
            })
                .then(async (res) => {
                    if (!res.ok) {
                        reject({ message: `Image upload failed (${res.status})`, remove: true });
                        return;
                    }
                    const json = await res.json();
                    json.location
                        ? resolve(json.location)
                        : reject({ message: 'Invalid upload response', remove: true });
                })
                .catch(() => reject({ message: 'Image upload error', remove: true }));
        });
}

function initEditor(el) {
    const profile = el.dataset.profile === 'basic' ? 'basic' : 'full';
    injectSkin();

    tinymce.init({
        target: el,
        license_key: 'gpl',
        skin: false,
        content_css: false,
        content_style: `${contentUiCss}\n${contentCss}\nbody{font-family:Inter,system-ui,sans-serif;max-width:72ch;}`,
        menubar: false,
        statusbar: false,
        branding: false,
        promotion: false,
        height: parseInt(el.dataset.height || '320', 10),
        plugins: PLUGINS[profile],
        toolbar: TOOLBAR[profile],
        block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
        valid_elements: VALID_ELEMENTS[profile],
        placeholder: el.getAttribute('placeholder') || '',
        automatic_uploads: true,
        images_upload_handler:
            profile === 'full' ? imagesUploadHandler(el.dataset.uploadUrl, el.dataset.csrf) : undefined,
        // Keep the underlying <textarea> in sync so normal form submits work,
        // and give the editing iframe an accessible title from its field label.
        setup: (editor) => {
            editor.on('change keyup undo redo', () => editor.save());
            editor.on('init', () => {
                const labelEl = document.querySelector(`label[for="${el.id}"]`);
                const title = (labelEl?.textContent || 'Rich text editor').trim();
                editor.iframeElement?.setAttribute('title', title);
            });
        },
    });
}

export function initRichEditors(root = document) {
    root.querySelectorAll('[data-rich-editor]').forEach(initEditor);
}

initRichEditors();
