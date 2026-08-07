/**
 * Recipient type-ahead for the message composer.
 *
 * Only used when the viewer's reachable list is too large to render inline — in practice
 * an admin, who may message the whole directory. Everyone else keeps the native <select>
 * of their coursemates, which is smaller, faster and accessible for free.
 *
 * The endpoint applies the same reachability rule as sending does, so anything this
 * offers is genuinely messageable; the picker never has to second-guess the server.
 */
export function contactPicker({ searchUrl, multiple = false, selected = [] }) {
    return {
        searchUrl,
        multiple,
        term: '',
        results: [],
        chosen: selected,
        open: false,
        loading: false,
        activeIndex: -1,
        sequence: 0,
        timer: null,

        init() {
            this.search();
        },

        onInput() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 200);
        },

        async search() {
            this.loading = true;

            // Responses can land out of order; only the newest query may paint.
            const mine = ++this.sequence;

            try {
                const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.term)}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) throw new Error('Search failed');

                const json = await res.json();
                if (mine !== this.sequence) return;

                this.results = json.results ?? [];
                this.activeIndex = this.results.length ? 0 : -1;
            } catch {
                if (mine !== this.sequence) return;
                this.results = [];
                this.activeIndex = -1;
            } finally {
                if (mine === this.sequence) this.loading = false;
            }
        },

        isChosen(person) {
            return this.chosen.some((c) => c.id === person.id);
        },

        choose(person) {
            if (!person) return;

            if (this.multiple) {
                if (this.isChosen(person)) {
                    this.remove(person);

                    return;
                }
                this.chosen.push(person);
                this.term = '';
                this.search();
            } else {
                this.chosen = [person];
                this.term = '';
                this.open = false;
            }
        },

        remove(person) {
            this.chosen = this.chosen.filter((c) => c.id !== person.id);
        },

        clear() {
            this.chosen = [];
            this.term = '';
            this.open = true;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        /** Single-select: the id the form posts. Empty when nothing is chosen. */
        get recipientId() {
            return this.multiple ? '' : (this.chosen[0]?.id ?? '');
        },

        get label() {
            return this.chosen[0]?.name ?? '';
        },

        // ── Keyboard: a combobox has to be operable without a mouse ──────────
        onKeydown(event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.open = true;
                this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);
            } else if (event.key === 'Enter' && this.open && this.activeIndex >= 0) {
                event.preventDefault();
                this.choose(this.results[this.activeIndex]);
            } else if (event.key === 'Escape') {
                this.open = false;
            }
        },
    };
}
