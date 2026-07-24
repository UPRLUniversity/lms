function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * The certificate template edit form: two optional signatory blocks, each with an
 * immediate-upload signature image (POSTs to signature-upload the moment a file is
 * chosen, storing the returned Media id in a hidden field — the form itself never
 * carries file input state, only the resulting id, mirroring the editor-upload
 * pattern used elsewhere in the app).
 */
export function certificateTemplateEditor({ uploadUrl, signatoryOne, signatoryTwo }) {
    return {
        uploadUrl,
        one: signatoryOne,
        two: signatoryTwo,
        hasTwo: !!(signatoryTwo && (signatoryTwo.name || signatoryTwo.signatureMediaId)),
        uploading: { one: false, two: false },

        addSecondSignatory() {
            this.hasTwo = true;
        },

        removeSecondSignatory() {
            this.hasTwo = false;
            this.two = { name: '', title: '', signatureMediaId: null, previewUrl: null };
        },

        clearSignature(slot) {
            const target = slot === 'one' ? this.one : this.two;
            target.signatureMediaId = null;
            target.previewUrl = null;
        },

        async uploadSignature(slot, event) {
            const file = event.target.files?.[0];
            if (!file) return;

            const target = slot === 'one' ? this.one : this.two;
            this.uploading[slot] = true;

            try {
                const data = new FormData();
                data.append('file', file);

                const res = await fetch(this.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: data,
                });

                if (!res.ok) throw new Error('Upload failed');

                const json = await res.json();
                target.signatureMediaId = json.id;
                target.previewUrl = json.url;
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Could not upload the signature image.' } }));
            } finally {
                this.uploading[slot] = false;
            }
        },
    };
}
