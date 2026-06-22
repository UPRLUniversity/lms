/**
 * attemptRunner — the student taking engine. One question per screen, a progress map for
 * jumping, flag-for-review, immediate network-resilient autosave, and a server-authoritative
 * countdown that auto-submits at zero.
 *
 * The timer is cosmetic: `remaining` is seeded from the server's expires_at on every load,
 * so a refresh re-anchors it and it can't be extended client-side. Autosaves queue and retry
 * on failure; a pending save is always flushed before submit.
 */
function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function attemptRunner(config = {}) {
    return {
        items: config.items || [],
        answerUrl: config.answerUrl,
        submitUrl: config.submitUrl,
        resultUrl: config.resultUrl,

        index: 0,
        responses: {},
        flags: {},
        remaining: config.remainingSeconds, // null when untimed
        timed: config.remainingSeconds !== null && config.remainingSeconds !== undefined,
        submitting: false,
        dirty: new Set(),
        savingQid: null,

        init() {
            // Seed responses + flags from the server's saved state.
            this.items.forEach((it) => {
                this.responses[it.question_id] = it.response ?? this.blankResponse(it);
                this.flags[it.question_id] = !!it.flagged;
            });

            if (this.timed) {
                this._tick = setInterval(() => {
                    this.remaining = Math.max(0, this.remaining - 1);
                    if (this.remaining <= 0) {
                        clearInterval(this._tick);
                        this.submit(true);
                    }
                }, 1000);
            }

            // Best-effort flush of any pending save when leaving the page.
            window.addEventListener('beforeunload', () => this.flush());
        },

        blankResponse(it) {
            if (it.type === 'mcq_multi') return [];
            if (it.type === 'matching') return {};
            if (it.type === 'scenario') return {};
            return '';
        },

        // --- Navigation -----------------------------------------------------
        get current() {
            return this.items[this.index];
        },
        get total() {
            return this.items.length;
        },
        go(i) {
            if (i >= 0 && i < this.total) this.index = i;
        },
        next() {
            this.go(this.index + 1);
        },
        prev() {
            this.go(this.index - 1);
        },

        // --- Answers --------------------------------------------------------
        setSingle(qid, optionId) {
            this.responses[qid] = optionId;
            this.save(qid);
        },
        toggleMulti(qid, optionId) {
            const arr = Array.isArray(this.responses[qid]) ? [...this.responses[qid]] : [];
            const i = arr.indexOf(optionId);
            if (i === -1) arr.push(optionId);
            else arr.splice(i, 1);
            this.responses[qid] = arr;
            this.save(qid);
        },
        isChosen(qid, optionId) {
            const r = this.responses[qid];
            return Array.isArray(r) ? r.includes(optionId) : r === optionId;
        },
        setMatch(qid, leftId, token) {
            this.responses[qid] = { ...(this.responses[qid] || {}), [leftId]: token };
            this.save(qid);
        },
        setScenario(qid, subId, value) {
            this.responses[qid] = { ...(this.responses[qid] || {}), [subId]: value };
            this.save(qid);
        },
        scenarioSingle(qid, subId, optionId) {
            this.setScenario(qid, subId, optionId);
        },
        scenarioMulti(qid, subId, optionId) {
            const cur = (this.responses[qid] || {})[subId];
            const arr = Array.isArray(cur) ? [...cur] : [];
            const i = arr.indexOf(optionId);
            if (i === -1) arr.push(optionId);
            else arr.splice(i, 1);
            this.setScenario(qid, subId, arr);
        },
        text(qid, value) {
            this.responses[qid] = value;
            this.save(qid);
        },

        toggleFlag(qid) {
            this.flags[qid] = !this.flags[qid];
            this.save(qid);
        },

        // --- Progress map ---------------------------------------------------
        statusOf(i) {
            const it = this.items[i];
            const qid = it.question_id;
            if (this.flags[qid]) return 'flagged';
            return this.answered(qid) ? 'answered' : 'skipped';
        },
        answered(qid) {
            const r = this.responses[qid];
            if (r == null) return false;
            if (Array.isArray(r)) return r.length > 0;
            if (typeof r === 'object') return Object.keys(r).length > 0;
            return String(r).trim() !== '';
        },
        get answeredCount() {
            return this.items.filter((it) => this.answered(it.question_id)).length;
        },

        // --- Autosave (resilient) ------------------------------------------
        save(qid) {
            this.dirty.add(qid);
            clearTimeout(this._saveTimer);
            this._saveTimer = setTimeout(() => this.flush(), 400);
        },
        async flush() {
            if (this.savingQid) return; // one in-flight at a time; the loop drains dirty
            const qid = this.dirty.values().next().value;
            if (qid === undefined) return;
            this.dirty.delete(qid);
            this.savingQid = qid;
            try {
                const res = await fetch(this.answerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question_id: qid, response: this.responses[qid], flagged: this.flags[qid] }),
                });
                if (!res.ok) throw new Error('save failed');
            } catch (e) {
                // Network blip — requeue and back off, so a flaky connection never loses an answer.
                this.dirty.add(qid);
                setTimeout(() => this.flush(), 2000);
            } finally {
                this.savingQid = null;
                if (this.dirty.size) this.flush();
            }
        },

        // --- Timer display --------------------------------------------------
        get clock() {
            if (!this.timed) return null;
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },
        get clockUrgent() {
            return this.timed && this.remaining <= 60;
        },

        // --- Submit ---------------------------------------------------------
        async submit(auto = false) {
            if (this.submitting) return;
            if (!auto) {
                const ok = await window.uprlConfirm({
                    title: 'Submit this attempt?',
                    text: `You've answered ${this.answeredCount} of ${this.total}. You can't change answers after submitting.`,
                    confirmText: 'Submit',
                });
                if (!ok) return;
            }
            this.submitting = true;
            await this.drain();
            try {
                const res = await fetch(this.submitUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                window.location = data.result_url || this.resultUrl;
            } catch (e) {
                this.submitting = false;
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not submit. Check your connection and try again.', type: 'error' } }));
            }
        },

        // Flush every pending save before submitting.
        async drain() {
            let guard = 0;
            while ((this.dirty.size || this.savingQid) && guard++ < 50) {
                await this.flush();
                await new Promise((r) => setTimeout(r, 100));
            }
        },
    };
}
