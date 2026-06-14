import Swal from 'sweetalert2';

/**
 * Branded SweetAlert2 confirmation dialog for the UPRL LMS. The single place
 * confirmations are styled — reuse this everywhere instead of native confirm().
 * Buttons mirror <x-ui.button> (crimson primary / outlined secondary).
 */
const baseButton =
    'inline-flex items-center justify-center gap-2 font-medium rounded-xl border transition-colors focus-ring px-5 py-2.5 text-sm';

export function uprlConfirm({
    title = 'Are you sure?',
    text = '',
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    danger = true,
    icon = 'warning',
} = {}) {
    return Swal.fire({
        title,
        text,
        icon,
        iconColor: danger ? '#C8102E' : '#0F6B3E',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        focusCancel: true,
        buttonsStyling: false,
        customClass: {
            popup: 'rounded-2xl border border-line shadow-xl',
            title: 'font-display text-ink',
            htmlContainer: 'text-ink/70',
            actions: 'gap-2',
            confirmButton:
                baseButton +
                (danger
                    ? ' bg-crimson border-transparent text-white hover:bg-crimson-dark'
                    : ' bg-green border-transparent text-white hover:opacity-90'),
            cancelButton: baseButton + ' bg-card border-line text-ink hover:bg-surface',
        },
    }).then((result) => result.isConfirmed);
}

// Available to inline handlers and the dataTable component alike.
window.uprlConfirm = uprlConfirm;

export default uprlConfirm;
