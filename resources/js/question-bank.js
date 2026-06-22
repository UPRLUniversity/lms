/**
 * questionEditor — the per-type question authoring component used on the full-page bank
 * editor. It manages the dynamic option / accepted-answer / matching-pair / scenario
 * sub-question rows as Alpine state, rendered through real named inputs so a normal form
 * POST serialises the nested `payload[...]` arrays (no JSON, no fetch). The prompt +
 * explanation are TinyMCE fields handled by the shared rich-editor.
 *
 * Each type's section is rendered with x-if (not x-show) so inactive types contribute no
 * inputs to the submit — only the chosen type's payload is sent.
 */
let _uid = 0;
const uid = () => `k${Date.now()}_${_uid++}`;

function blankOption(text = '', correct = false) {
    return { key: uid(), text, is_correct: correct };
}

function newSub(type = 'mcq_single') {
    return {
        key: uid(),
        type,
        prompt: '',
        points: 1,
        options: [blankOption('', true), blankOption()],
        accepted: [''],
        case_insensitive: true,
        tf_answer: true,
    };
}

export function questionEditor(config = {}) {
    const initial = config.question || {};
    const payload = initial.payload || {};

    return {
        type: config.type || initial.type || 'mcq_single',
        points: config.points ?? initial.points ?? 1,
        // Option types
        options: (payload.options && payload.options.length
            ? payload.options.map((o) => ({ key: uid(), text: o.text, is_correct: !!o.is_correct }))
            : [blankOption('', true), blankOption(), blankOption()]),
        // True/false
        tf_answer: payload.options ? !!(payload.options.find((o) => o.id === 'true')?.is_correct ?? true) : true,
        // Fill blank
        accepted: payload.accepted && payload.accepted.length ? [...payload.accepted] : [''],
        case_insensitive: payload.case_insensitive !== undefined ? !!payload.case_insensitive : true,
        // Matching
        pairs: payload.pairs && payload.pairs.length
            ? payload.pairs.map((p) => ({ key: uid(), left: p.left, right: p.right }))
            : [{ key: uid(), left: '', right: '' }, { key: uid(), left: '', right: '' }],
        // Essay
        guidance: payload.guidance || '',
        // Scenario
        subs: payload.sub_questions && payload.sub_questions.length
            ? payload.sub_questions.map((s) => ({
                  key: uid(),
                  type: s.type,
                  prompt: s.prompt || '',
                  points: s.points ?? 1,
                  options: (s.payload?.options || []).map((o) => ({ key: uid(), text: o.text, is_correct: !!o.is_correct })),
                  accepted: s.payload?.accepted?.length ? [...s.payload.accepted] : [''],
                  case_insensitive: s.payload?.case_insensitive !== undefined ? !!s.payload.case_insensitive : true,
                  tf_answer: s.payload?.options ? !!(s.payload.options.find((o) => o.id === 'true')?.is_correct ?? true) : true,
              }))
            : [newSub()],

        // --- Option helpers -------------------------------------------------
        addOption() {
            this.options.push(blankOption());
        },
        removeOption(i) {
            if (this.options.length > 2) this.options.splice(i, 1);
        },
        setSingleCorrect(i) {
            this.options.forEach((o, idx) => (o.is_correct = idx === i));
        },

        // --- Fill-blank helpers --------------------------------------------
        addAccepted() {
            this.accepted.push('');
        },
        removeAccepted(i) {
            if (this.accepted.length > 1) this.accepted.splice(i, 1);
        },

        // --- Matching helpers ----------------------------------------------
        addPair() {
            this.pairs.push({ key: uid(), left: '', right: '' });
        },
        removePair(i) {
            if (this.pairs.length > 2) this.pairs.splice(i, 1);
        },

        // --- Scenario helpers ----------------------------------------------
        addSub() {
            this.subs.push(newSub());
        },
        removeSub(i) {
            if (this.subs.length > 1) this.subs.splice(i, 1);
        },
        changeSubType(sub) {
            if ((sub.type === 'mcq_single' || sub.type === 'mcq_multi') && sub.options.length < 2) {
                sub.options = [blankOption('', true), blankOption()];
            }
        },
        addSubOption(sub) {
            sub.options.push(blankOption());
        },
        removeSubOption(sub, i) {
            if (sub.options.length > 2) sub.options.splice(i, 1);
        },
        setSubSingleCorrect(sub, i) {
            sub.options.forEach((o, idx) => (o.is_correct = idx === i));
        },
        addSubAccepted(sub) {
            sub.accepted.push('');
        },
        removeSubAccepted(sub, i) {
            if (sub.accepted.length > 1) sub.accepted.splice(i, 1);
        },

        get scenarioPoints() {
            return this.subs.reduce((sum, s) => sum + (parseFloat(s.points) || 0), 0);
        },

        // Flush TinyMCE content back into the textareas before the native submit.
        onSubmit() {
            if (window.tinymce) window.tinymce.triggerSave();
            return true;
        },
    };
}
