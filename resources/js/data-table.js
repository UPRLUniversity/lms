/**
 * dataTable — a reusable Alpine component that turns a server-rendered table into
 * a live one: search, role filter, column sorting, pagination and row actions all
 * fetch a partial and swap it in place, so the surrounding page never reloads.
 *
 * The server returns the table partial for AJAX requests (X-Requested-With) and
 * exposes sort/pagination as real <a data-nav href> links + row actions as
 * <form data-ajax> — so everything still works with JavaScript disabled.
 */
export default function dataTable(endpoint) {
    return {
        endpoint,
        loading: false,
        // Two-way bound to the search box + role <select>.
        params: { search: '', role: '' },
        // Sort/page live on the URL the server renders; we track the live URL so
        // row actions can re-fetch the exact current view.
        currentUrl: window.location.href,

        init() {
            const url = new URL(window.location.href);
            this.params.search = url.searchParams.get('search') ?? '';
            this.params.role = url.searchParams.get('role') ?? '';
            this.currentUrl = window.location.href;
        },

        get isFiltered() {
            return this.params.search !== '' || this.params.role !== '';
        },

        /** Apply the search/role controls, resetting to page 1 but keeping sort. */
        filter() {
            const url = new URL(this.currentUrl, window.location.origin);
            this.setOrDelete(url, 'search', this.params.search.trim());
            this.setOrDelete(url, 'role', this.params.role);
            url.searchParams.delete('page');
            this.go(url.toString());
        },

        clearFilters() {
            this.params.search = '';
            this.params.role = '';
            this.filter();
        },

        /** Intercept sort/pagination link clicks inside the results region. */
        onNav(event) {
            const link = event.target.closest('a[data-nav]');
            if (!link) return;
            // Disabled pagination links (current page / gaps) have no href.
            const href = link.getAttribute('href');
            if (!href || href === '#') {
                event.preventDefault();
                return;
            }
            event.preventDefault();
            this.go(href);
        },

        /** Intercept row-action form submits (deactivate / reactivate / …). */
        async onAction(event) {
            const form = event.target.closest('form[data-ajax]');
            if (!form) return;
            event.preventDefault();

            // Optional confirmation (e.g. revoking an invitation) — branded dialog.
            const confirmMessage = form.getAttribute('data-confirm');
            if (confirmMessage) {
                const confirmed = await window.uprlConfirm({
                    title: confirmMessage,
                    confirmText: form.getAttribute('data-confirm-action') ?? 'Yes, continue',
                    danger: true,
                });
                if (!confirmed) return;
            }

            this.loading = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', // Laravel reads the _method field for PATCH/DELETE
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: new FormData(form),
                });

                if (!response.ok) throw new Error('Request failed');

                const data = await response.json().catch(() => ({}));
                if (data.message) this.flash(data.message);

                // Re-fetch the exact current view so filters/sort/page are preserved.
                await this.go(this.currentUrl, false);
            } catch (e) {
                this.flash('Something went wrong. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        /** Fetch a URL's table partial and swap it into the results region. */
        async go(url, pushHistory = true) {
            const absolute = new URL(url, window.location.origin);
            this.loading = true;
            try {
                const response = await fetch(absolute.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('Request failed');

                this.$refs.results.innerHTML = await response.text();
                this.currentUrl = absolute.toString();

                if (pushHistory) {
                    // Keep the address bar shareable, without the partial flag.
                    const display = new URL(absolute.toString());
                    display.searchParams.delete('partial');
                    window.history.replaceState({}, '', display.toString());
                }
            } catch (e) {
                this.flash('Could not load results. Please try again.', 'error');
            } finally {
                this.loading = false;
            }
        },

        setOrDelete(url, key, value) {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        },

        flash(message, type = 'success') {
            // Defer to the global top-right toast stack for one consistent style.
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },
    };
}
