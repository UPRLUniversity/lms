// Student assignment hand-in: file picking with client-side hints, an XHR submit so
// large uploads show real progress, and a localStorage draft of the typed answer so a
// closed tab never loses work. Server-side validation remains authoritative — this
// only makes the happy path feel good.

const DRAFT_INTERVAL_MS = 5000;

export function assignmentSubmit({ draftKey, maxFiles = 5, maxKb = 20480 }) {
    return {
        files: [],
        uploading: false,
        progress: 0,
        errors: [],
        draftSavedAt: null,

        init() {
            this.restoreDraft();
            // TinyMCE syncs the textarea on change/keyup; snapshot it periodically.
            this._draftTimer = setInterval(() => this.saveDraft(), DRAFT_INTERVAL_MS);
        },

        destroy() {
            clearInterval(this._draftTimer);
        },

        get textarea() {
            return this.$root.querySelector('textarea[name="body"]');
        },

        /* ---------------------------------------------------------------- files */

        pick(event) {
            this.errors = [];
            const incoming = Array.from(event.target.files || []);

            for (const file of incoming) {
                if (this.files.length >= maxFiles) {
                    this.errors.push(`You can attach up to ${maxFiles} files per submission.`);
                    break;
                }
                if (file.size > maxKb * 1024) {
                    this.errors.push(`“${file.name}” is larger than the ${Math.round(maxKb / 1024)}MB limit.`);
                    continue;
                }
                this.files.push(file);
            }

            event.target.value = ''; // allow re-picking the same file
        },

        removeFile(index) {
            this.files.splice(index, 1);
        },

        fmtSize(bytes) {
            if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },

        /* ---------------------------------------------------------------- draft */

        saveDraft() {
            const body = this.textarea?.value ?? '';
            if (!draftKey) return;
            try {
                if (body.trim() === '') return;
                localStorage.setItem(draftKey, body);
                this.draftSavedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch {
                /* storage full/unavailable — the normal submit still works */
            }
        },

        restoreDraft() {
            if (!draftKey || !this.textarea) return;
            if (this.textarea.value.trim() !== '') return; // server-rendered old() wins
            const draft = localStorage.getItem(draftKey);
            if (draft) {
                this.textarea.value = draft;
                window.tinymce?.get(this.textarea.id)?.setContent(draft);
            }
        },

        clearDraft() {
            if (draftKey) localStorage.removeItem(draftKey);
        },

        /* --------------------------------------------------------------- submit */

        submit() {
            if (this.uploading) return;
            this.errors = [];

            // Flush TinyMCE into the textarea before reading the form.
            window.tinymce?.triggerSave?.();

            const form = this.$root.querySelector('form');
            const data = new FormData(form);
            data.delete('files[]');
            this.files.forEach((file) => data.append('files[]', file));

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.responseType = 'json';

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
            });

            xhr.addEventListener('load', () => {
                this.uploading = false;
                if (xhr.status >= 200 && xhr.status < 300 && xhr.response?.redirect) {
                    this.clearDraft();
                    window.dispatchEvent(new CustomEvent('toast', {
                        detail: { message: xhr.response.message || 'Submitted.', type: 'success' },
                    }));
                    window.location.assign(xhr.response.redirect);
                    return;
                }
                if (xhr.status === 422 && xhr.response?.errors) {
                    this.errors = Object.values(xhr.response.errors).flat();
                } else {
                    this.errors = ['Something went wrong while submitting — please try again.'];
                }
            });

            xhr.addEventListener('error', () => {
                this.uploading = false;
                this.errors = ['Network problem while uploading — check your connection and try again.'];
            });

            this.uploading = true;
            this.progress = 0;
            xhr.send(data);
        },
    };
}
