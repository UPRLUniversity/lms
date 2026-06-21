/**
 * assessmentBuilder — drives the assessment builder's content tab: the fixed-mode question
 * picker (toggle from the bank, reorder, points overrides, running total) and the pooled-mode
 * rule editor (category/difficulty/count with live "available in pool"). Settings save via a
 * normal form POST; content saves over AJAX against the assessment's content endpoints.
 */
import Sortable from 'sortablejs';

async function send(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, data };
}

function toast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
}

export function assessmentBuilder(config = {}) {
    return {
        base: config.base,
        tab: 'content',
        mode: config.mode || 'fixed',
        bank: config.bank || [],
        bankFilter: { search: '', difficulty: '', category: '' },

        // Fixed mode
        selected: (config.selected || []).map((s) => ({ id: s.id, points_override: s.points_override })),
        // Pooled mode
        rules: config.rules || [],

        publishErrors: config.publishErrors || [],
        saving: false,

        init() {
            if (this.$refs.selectedList) {
                Sortable.create(this.$refs.selectedList, {
                    handle: '[data-drag]',
                    animation: 150,
                    onEnd: () => {
                        const ids = [...this.$refs.selectedList.querySelectorAll('[data-id]')].map((el) => +el.dataset.id);
                        this.selected = ids.map((id) => this.selected.find((s) => s.id === id)).filter(Boolean);
                        this.saveFixed();
                    },
                });
            }
        },

        // --- Bank list (fixed) ---------------------------------------------
        get filteredBank() {
            return this.bank.filter((q) => {
                const f = this.bankFilter;
                return (
                    (!f.search || q.prompt.toLowerCase().includes(f.search.toLowerCase())) &&
                    (!f.difficulty || q.difficulty === f.difficulty) &&
                    (!f.category || String(q.category_id) === String(f.category))
                );
            });
        },
        isSelected(id) {
            return this.selected.some((s) => s.id === id);
        },
        bankById(id) {
            return this.bank.find((q) => q.id === id) || {};
        },
        toggle(id) {
            if (this.isSelected(id)) {
                this.selected = this.selected.filter((s) => s.id !== id);
            } else {
                this.selected.push({ id, points_override: null });
            }
            this.saveFixed();
        },
        remove(id) {
            this.selected = this.selected.filter((s) => s.id !== id);
            this.saveFixed();
        },
        get totalPoints() {
            return this.selected.reduce((sum, s) => {
                const pts = s.points_override != null && s.points_override !== '' ? +s.points_override : this.bankById(s.id).points || 0;
                return sum + pts;
            }, 0);
        },
        async saveFixed() {
            this.saving = true;
            const payload = { questions: this.selected.map((s) => ({ id: s.id, points_override: s.points_override === '' ? null : s.points_override })) };
            const { ok, data } = await send(`${this.base}/questions`, 'PUT', payload);
            this.saving = false;
            if (ok) {
                this.publishErrors = data.publish_errors || [];
            } else {
                toast(data.message || 'Could not save questions.', 'error');
            }
        },

        // --- Pool rules (pooled) -------------------------------------------
        async addRule() {
            const first = this.bank[0];
            const { ok, data } = await send(`${this.base}/pool-rules`, 'POST', {
                category_id: config.categories?.[0]?.id,
                difficulty: '',
                count: 5,
            });
            if (ok) {
                this.rules.push({ id: data.rule_id, category_id: config.categories?.[0]?.id, difficulty: '', count: 5, available: data.available });
                this.publishErrors = data.publish_errors || [];
            } else {
                toast(data.message || 'Could not add rule.', 'error');
            }
        },
        async updateRule(rule) {
            const { ok, data } = await send(`${this.base}/pool-rules/${rule.id}`, 'PUT', {
                category_id: rule.category_id,
                difficulty: rule.difficulty || '',
                count: rule.count,
            });
            if (ok) {
                rule.available = data.available;
                this.publishErrors = data.publish_errors || [];
            } else {
                toast(data.message || 'Could not update rule.', 'error');
            }
        },
        async deleteRule(rule) {
            const { ok, data } = await send(`${this.base}/pool-rules/${rule.id}`, 'DELETE');
            if (ok) {
                this.rules = this.rules.filter((r) => r.id !== rule.id);
                this.publishErrors = data.publish_errors || [];
            }
        },
        ruleShort(rule) {
            return rule.available != null && rule.available < rule.count;
        },
    };
}
