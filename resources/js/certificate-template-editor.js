function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Mirrors the server's Signatures policy (config/media.php) so the two commonest
 * rejections — a JPG scan, or a file over the ceiling — are named before a pointless
 * round trip. The server still re-validates; this only buys a faster, clearer refusal.
 */
const ACCEPTED_TYPES = ['image/png', 'image/webp'];
const MAX_KB = 1024;

function localRejection(file) {
    if (!ACCEPTED_TYPES.includes(file.type)) {
        return 'Signature images must be a PNG or WebP file. A JPG scan will not be accepted — re-save it as a PNG.';
    }

    if (file.size > MAX_KB * 1024) {
        const actual = Math.ceil(file.size / 1024);

        return `That image is ${actual}KB — signature images must be under ${MAX_KB}KB. Try resizing it to about 600px wide.`;
    }

    return null;
}

/**
 * Pull the human-readable reason out of a failed response. Laravel returns 422 with
 * {message, errors} for a validation failure — which is exactly the useful case, and
 * exactly what the old generic catch was discarding.
 */
async function failureMessage(response) {
    if (response.status === 419) {
        return 'Your session expired. Reload the page and try again.';
    }

    if (response.status === 403) {
        return 'You do not have permission to upload signature images.';
    }

    if (response.status === 413) {
        return `That file is too large to upload — signature images must be under ${MAX_KB}KB.`;
    }

    try {
        const json = await response.json();
        const firstError = Object.values(json?.errors ?? {})[0]?.[0];

        if (firstError || json?.message) {
            return firstError ?? json.message;
        }
    } catch {
        // Not JSON (a raw 500 page, say) — fall through to the generic wording.
    }

    return 'Could not upload the signature image. Please try again.';
}

function errorToast(message) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message } }));
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
            const input = event.target;
            const file = input.files?.[0];
            if (!file) return;

            // Reject locally first, and clear the input so re-picking the SAME corrected
            // file still fires a change event.
            const rejection = localRejection(file);
            if (rejection) {
                input.value = '';
                errorToast(rejection);

                return;
            }

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

                if (!res.ok) {
                    input.value = '';
                    errorToast(await failureMessage(res));

                    return;
                }

                const json = await res.json();
                target.signatureMediaId = json.id;
                target.previewUrl = json.url;
            } catch {
                // Network/transport failure — the request never got an answer.
                input.value = '';
                errorToast('Could not reach the server. Check your connection and try again.');
            } finally {
                this.uploading[slot] = false;
            }
        },
    };
}
