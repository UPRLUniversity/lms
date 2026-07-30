/**
 * The course builder's client behaviour: a drag-and-drop curriculum outline, an
 * AJAX slide-over lesson editor (with live YouTube/Vimeo embed preview), inline
 * module rename, and the settings tab's dirty-state guard + dynamic objective rows.
 *
 * Structure is persisted per-action: every add/rename/delete/reorder posts straight
 * to the server and the outline partial is re-fetched, so there is no "unsaved
 * curriculum" to lose. The settings FORM is the one explicit-save surface and warns
 * before navigating away while dirty. (See docs/decisions.md.)
 */

const SORTABLE_OPTS = {
    animation: 150,
    ghostClass: 'opacity-40',
    chosenClass: 'ring-2',
    ringColor: undefined,
};

/* ------------------------------------------------------------------ helpers */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function toast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
}

/** Client-side YouTube/Vimeo → embed URL (mirrors VideoEmbedService for the preview). */
export function videoEmbed(url) {
    if (!url) return null;
    const yt = url.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?[^ ]*v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/);
    if (yt) return `https://www.youtube-nocookie.com/embed/${yt[1]}`;
    const vm = url.match(/vimeo\.com\/(?:video\/|channels\/[^/]+\/|groups\/[^/]+\/videos\/)?(\d{6,})/)
        || url.match(/player\.vimeo\.com\/video\/(\d{6,})/);
    if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
    return null;
}

/* -------------------------------------------------------------- main builder */

export function courseBuilder(config = {}) {
    return {
        base: config.base ?? '',
        canManage: config.canManage ?? false,
        tab: 'settings',

        // Curriculum
        newModuleTitle: '',
        sortables: [],
        collapsed: new Set(),

        // Where the next created item should land: the bucket clicked and the 0-based
        // slot within it. index === null means "append", which is what the toolbar
        // buttons and the per-module "Add lesson" use.
        insert: { moduleId: null, index: null },
        newAssessment: { placement: 'standalone', moduleId: '' },
        newAssignment: { moduleId: '' },

        // Lesson editor
        editorOpen: false,
        saving: false,
        fileLabel: '',
        lesson: {
            id: null, module_id: null, title: '', type: 'text',
            video_source: 'embed', video_url: '', external_url: '',
            duration_minutes: '', is_free_preview: false, file: null,
        },
        lessonBody: '',
        errors: {},

        init() {
            // Open straight to the curriculum if the URL asks for it.
            if (window.location.hash === '#curriculum') this.tab = 'curriculum';

            if (this.canManage) {
                this.$nextTick(() => this.initSortables());

                const region = document.getElementById('curriculum-region');
                if (region) {
                    region.addEventListener('focusout', (e) => this.onModuleRename(e));
                    region.addEventListener('keydown', (e) => {
                        if (e.target.matches('[data-action="rename-module"]') && e.key === 'Enter') {
                            e.preventDefault();
                            e.target.blur();
                        }
                    });
                }
            }
        },

        videoEmbed,

        blankLesson() {
            return {
                id: null, module_id: null, title: '', type: 'text',
                video_source: 'embed', video_url: '', external_url: '',
                duration_minutes: '', is_free_preview: false, file: null,
            };
        },

        /* ----- URL builders (match routes/web.php) ----- */
        urlCurriculum() { return `${this.base}/curriculum`; },
        urlReorder() { return `${this.base}/curriculum/reorder`; },
        urlModules() { return `${this.base}/modules`; },
        urlModule(id) { return `${this.base}/modules/${id}`; },
        urlLessonsIn(moduleId) { return `${this.base}/modules/${moduleId}/lessons`; },
        urlLesson(id) { return `${this.base}/lessons/${id}`; },

        /* ----- delegated clicks in the outline ----- */
        onCurriculumClick(event) {
            const el = event.target.closest('[data-action]');
            const action = el?.dataset.action;

            // Any click that isn't inside an open insert menu closes it.
            if (action !== 'insert-here' && !event.target.closest('[data-insert-menu]')) {
                this.closeInsertMenus();
            }

            if (!el) return;

            if (action === 'toggle-module') return this.toggleModule(el);
            if (action === 'add-lesson') {
                this.insert = { moduleId: Number(el.dataset.moduleId), index: null };
                return this.openLessonEditor(Number(el.dataset.moduleId));
            }
            if (action === 'edit-lesson') return this.openLessonEditor(null, Number(el.dataset.lessonId));
            if (action === 'delete-lesson') return this.deleteLesson(Number(el.dataset.lessonId));
            if (action === 'delete-module') return this.deleteModule(Number(el.dataset.moduleId));

            if (action === 'insert-here') return this.toggleInsertMenu(el);
            if (action === 'insert-lesson') return this.insertItem(el, 'lesson');
            if (action === 'insert-assessment') return this.insertItem(el, 'assessment');
            if (action === 'insert-assignment') return this.insertItem(el, 'assignment');
        },

        /* ----- delegated keyboard in the outline ----- */
        onCurriculumKeydown(event) {
            if (event.key === 'Escape') return this.closeInsertMenus();

            // Alt+↑/↓ on a row's handle is the keyboard equivalent of a drag — the whole
            // reorder feature is unusable without it, so it hits the same endpoint.
            if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return;

            const handle = event.target.closest('[data-drag-item]');
            if (!handle) return;

            event.preventDefault();
            this.moveItem(handle, event.key === 'ArrowUp' ? -1 : 1);
        },

        /* ----- insert-here affordances ----- */
        closeInsertMenus() {
            this.$refs.outline.querySelectorAll('[data-insert-menu]').forEach((menu) => {
                menu.setAttribute('hidden', '');
                menu.closest('[data-insert-slot]')
                    ?.querySelector('[data-action="insert-here"]')
                    ?.setAttribute('aria-expanded', 'false');
            });
        },

        toggleInsertMenu(button) {
            const menu = button.closest('[data-insert-slot]')?.querySelector('[data-insert-menu]');
            if (!menu) return;
            const wasOpen = !menu.hasAttribute('hidden');
            this.closeInsertMenus();
            if (wasOpen) return;
            menu.removeAttribute('hidden');
            button.setAttribute('aria-expanded', 'true');
            menu.querySelector('button')?.focus();
        },

        /**
         * Resolve the clicked slot to a (bucket, index) pair and open the right creator.
         * The index is counted from the DOM rather than baked into the slot, so it stays
         * correct after a drag has moved rows around it.
         */
        insertItem(el, type) {
            const slot = el.closest('[data-insert-slot]');
            const list = slot?.closest('[data-item-list]');
            if (!list) return;

            const moduleId = list.dataset.moduleId === '' ? null : Number(list.dataset.moduleId);
            const index = [...list.children]
                .slice(0, [...list.children].indexOf(slot))
                .filter((child) => child.matches('[data-curriculum-item]')).length;

            this.insert = { moduleId, index };
            this.closeInsertMenus();

            if (type === 'lesson') {
                // A lesson needs a module; the course-level bucket can't hold one.
                if (moduleId === null) {
                    this.insert = { moduleId: null, index: null };
                    toast('Lessons live inside a module — add one to a module instead.', 'error');
                    return;
                }
                return this.openLessonEditor(moduleId);
            }

            if (type === 'assessment') {
                this.newAssessment = {
                    placement: moduleId === null ? 'standalone' : 'post_module',
                    moduleId: moduleId === null ? '' : String(moduleId),
                };
                return this.$dispatch('open-modal', 'add-assessment');
            }

            this.newAssignment = { moduleId: moduleId === null ? '' : String(moduleId) };
            this.$dispatch('open-modal', 'add-assignment');
        },

        /** The toolbar buttons create at the end, with the pickers shown. */
        openAssessmentModal() {
            this.insert = { moduleId: null, index: null };
            this.newAssessment = { placement: 'standalone', moduleId: '' };
            this.$dispatch('open-modal', 'add-assessment');
        },

        openAssignmentModal() {
            this.insert = { moduleId: null, index: null };
            this.newAssignment = { moduleId: '' };
            this.$dispatch('open-modal', 'add-assignment');
        },

        /* ----- keyboard reordering ----- */
        moveItem(handle, direction) {
            const row = handle.closest('[data-curriculum-item]');
            const list = row?.closest('[data-item-list]');
            if (!list) return;

            const rows = [...list.querySelectorAll('[data-curriculum-item]')];
            const from = rows.indexOf(row);
            const to = from + direction;

            if (to >= 0 && to < rows.length) {
                direction < 0 ? rows[to].before(row) : rows[to].after(row);
                handle.focus();
                this.persistOrder();
                return this.announce(row, list, to + 1, rows.length);
            }

            // Off the end of this bucket — step into the neighbouring one, so a keyboard
            // user can move an item between modules exactly as a drag can.
            const target = this.adjacentList(list, direction, row.dataset.itemType);
            if (!target) {
                return this.say(`Already at the ${direction < 0 ? 'start' : 'end'} of the curriculum.`);
            }

            direction < 0 ? target.append(row) : target.prepend(row);
            handle.focus();
            this.persistOrder();

            const count = target.querySelectorAll('[data-curriculum-item]').length;
            this.announce(row, target, direction < 0 ? count : 1, count);
        },

        /** The next/previous bucket that may hold $type, in document order. */
        adjacentList(list, direction, type) {
            const lists = [...this.$refs.outline.querySelectorAll('[data-item-list]')]
                .filter((el) => !(type === 'lesson' && el.dataset.moduleId === ''));

            return lists[lists.indexOf(list) + direction] ?? null;
        },

        bucketName(list) {
            if (list.dataset.moduleId === '') return 'Course level';
            return list.closest('[data-module]')
                ?.querySelector('[data-action="rename-module"]')?.textContent.trim() ?? 'this module';
        },

        announce(row, list, position, total) {
            const title = row.querySelector('.font-medium')?.textContent.trim() ?? 'Item';
            this.say(`Moved ${title} to position ${position} of ${total} in ${this.bucketName(list)}.`);
        },

        say(message) {
            if (this.$refs.live) this.$refs.live.textContent = message;
        },

        toggleModule(button) {
            const module = button.closest('[data-module]');
            const body = module?.querySelector('[data-module-body]');
            const chevron = module?.querySelector('[data-chevron]');
            if (!body) return;
            const isHidden = body.hasAttribute('hidden');
            body.toggleAttribute('hidden', !isHidden);
            chevron?.classList.toggle('rotate-90', isHidden);
            const id = module.dataset.moduleId;
            isHidden ? this.collapsed.delete(id) : this.collapsed.add(id);
        },

        /* ----- modules ----- */
        async addModule() {
            const title = this.newModuleTitle.trim();
            if (!title) return;
            const data = new FormData();
            data.append('title', title);
            const ok = await this.send(this.urlModules(), 'POST', data);
            if (ok) {
                this.newModuleTitle = '';
                await this.refresh();
            }
        },

        async onModuleRename(event) {
            const el = event.target;
            if (!el.matches?.('[data-action="rename-module"]')) return;
            const title = el.textContent.trim();
            if (!title) { await this.refresh(); return; }
            const data = new FormData();
            data.append('title', title);
            data.append('_method', 'PATCH');
            await this.send(this.urlModule(el.dataset.moduleId), 'POST', data, false);
        },

        async deleteModule(id) {
            const ok = await window.uprlConfirm({
                title: 'Delete this module?',
                text: 'Its lessons will be removed too.',
                confirmText: 'Delete', danger: true,
            });
            if (!ok) return;
            const data = new FormData();
            data.append('_method', 'DELETE');
            if (await this.send(this.urlModule(id), 'POST', data)) await this.refresh();
        },

        /* ----- lessons ----- */
        async openLessonEditor(moduleId, lessonId = null) {
            this.errors = {};
            this.fileLabel = '';

            if (lessonId) {
                const res = await fetch(this.urlLesson(lessonId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                if (!res.ok) { toast('Could not load that lesson.', 'error'); return; }
                const data = await res.json();
                this.lesson = {
                    id: data.id, module_id: data.module_id, title: data.title, type: data.type,
                    video_source: data.video_source, video_url: data.video_url ?? '',
                    external_url: data.external_url ?? '',
                    duration_minutes: data.duration_minutes ?? '',
                    is_free_preview: !!data.is_free_preview, file: data.file,
                };
                this.lessonBody = data.content_text ?? '';
            } else {
                this.lesson = { ...this.blankLesson(), module_id: moduleId };
                this.lessonBody = '';
            }

            this.editorOpen = true;
            // Sync the rich editor with the lesson body (TinyMCE is already mounted).
            this.$nextTick(() => {
                window.tinymce?.get('lesson_content_text')?.setContent(this.lessonBody ?? '');
            });
        },

        closeEditor() {
            this.editorOpen = false;
        },

        async saveLesson(event) {
            this.errors = {};
            this.saving = true;

            // Flush TinyMCE into its <textarea> so FormData picks it up.
            window.tinymce?.get('lesson_content_text')?.save();

            const form = event.target;
            const data = new FormData(form);

            let url, method;
            if (this.lesson.id) {
                url = this.urlLesson(this.lesson.id);
                method = 'POST'; // route is POST
            } else {
                url = this.urlLessonsIn(this.lesson.module_id);
                method = 'POST';
                // Created from an "insert here" slot: land it in that exact slot.
                if (this.insert.index !== null) data.append('insert_at', this.insert.index);
            }

            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: data,
                });

                if (res.status === 422) {
                    const body = await res.json();
                    this.errors = this.flattenErrors(body.errors ?? {});
                    return;
                }
                if (!res.ok) throw new Error('Request failed');

                const body = await res.json().catch(() => ({}));
                this.editorOpen = false;
                await this.refresh();
                toast(body.message ?? 'Lesson saved.');
            } catch (e) {
                toast('Could not save the lesson. Please try again.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async deleteLesson(id) {
            const ok = await window.uprlConfirm({ title: 'Delete this lesson?', confirmText: 'Delete', danger: true });
            if (!ok) return;
            const data = new FormData();
            data.append('_method', 'DELETE');
            if (await this.send(this.urlLesson(id), 'POST', data)) await this.refresh();
        },

        flattenErrors(errors) {
            const flat = {};
            for (const [key, messages] of Object.entries(errors)) {
                flat[key === 'file' || key === 'video_url' || key === 'external_url' || key === 'title' ? key : '_'] =
                    Array.isArray(messages) ? messages[0] : messages;
            }
            return flat;
        },

        /* ----- shared request + refresh + reorder ----- */
        async send(url, method, body, withToast = true) {
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body,
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json().catch(() => ({}));
                if (withToast && data.message) toast(data.message);
                return true;
            } catch (e) {
                toast('Something went wrong. Please try again.', 'error');
                return false;
            }
        },

        async refresh() {
            try {
                const res = await fetch(this.urlCurriculum(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error();
                this.$refs.outline.innerHTML = await res.text();
                if (this.canManage) {
                    this.$nextTick(() => {
                        this.initSortables();
                        // Re-apply collapsed modules after a re-render.
                        this.collapsed.forEach((id) => {
                            const module = this.$refs.outline.querySelector(`[data-module][data-module-id="${id}"]`);
                            const body = module?.querySelector('[data-module-body]');
                            const chevron = module?.querySelector('[data-chevron]');
                            body?.setAttribute('hidden', '');
                            chevron?.classList.remove('rotate-90');
                        });
                    });
                }
            } catch (e) {
                toast('Could not refresh the outline.', 'error');
            }
        },

        async initSortables() {
            const { default: Sortable } = await import('sortablejs');
            this.sortables.forEach((s) => s.destroy());
            this.sortables = [];

            const moduleList = this.$refs.outline.querySelector('[data-module-list]');
            if (moduleList) {
                this.sortables.push(new Sortable(moduleList, {
                    ...SORTABLE_OPTS,
                    handle: '[data-drag-module]',
                    onEnd: () => this.persistOrder(),
                }));
            }

            // One list per bucket, all in the same group: lessons, quizzes and assignments
            // are equal siblings that drag between modules and to/from course level.
            this.$refs.outline.querySelectorAll('[data-item-list]').forEach((list) => {
                this.sortables.push(new Sortable(list, {
                    ...SORTABLE_OPTS,
                    group: {
                        name: 'curriculum',
                        // A lesson has nowhere to live outside a module.
                        put: (to, from, dragged) =>
                            !(to.el.dataset.moduleId === '' && dragged.dataset.itemType === 'lesson'),
                    },
                    draggable: '[data-curriculum-item]',
                    handle: '[data-drag-item]',
                    // Touch: a scroll gesture must not start a drag.
                    delay: 150,
                    delayOnTouchOnly: true,
                    onEnd: () => this.persistOrder({ refresh: true }),
                }));
            });
        },

        /**
         * Post the whole outline: module order, and each bucket's merged item order.
         * A pointer drop re-fetches the partial afterwards so the insert slots and any
         * re-derived labels settle; a keyboard move doesn't, because re-rendering would
         * throw away the focus the user is mid-move with.
         */
        persistOrder({ refresh = false } = {}) {
            const order = [...this.$refs.outline.querySelectorAll('[data-item-list]')].map((list) => ({
                module_id: list.dataset.moduleId === '' ? null : Number(list.dataset.moduleId),
                items: [...list.querySelectorAll('[data-curriculum-item]')].map((el) => ({
                    type: el.dataset.itemType,
                    id: Number(el.dataset.itemId),
                })),
            }));

            // Send as JSON so the nested item objects survive.
            return fetch(this.urlReorder(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ order }),
            })
                .then((res) => {
                    if (!res.ok) throw new Error();
                    toast('Order saved.');
                    if (refresh) return this.refresh();
                })
                .catch(() => toast('Could not save the new order.', 'error'));
        },
    };
}

/* ------------------------------------------------- settings tab (dirty guard) */

export function courseSettings() {
    return {
        dirty: false,
        coverPreview: null,
        coverName: '',

        init() {
            const handler = (e) => {
                if (!this.dirty) return;
                e.preventDefault();
                e.returnValue = '';
            };
            window.addEventListener('beforeunload', handler);
        },

        previewCover(event) {
            const file = event.target.files?.[0];
            this.dirty = true;
            if (!file) { this.coverPreview = null; this.coverName = ''; return; }
            this.coverName = file.name;
            const reader = new FileReader();
            reader.onload = (e) => (this.coverPreview = e.target.result);
            reader.readAsDataURL(file);
        },
    };
}

/* --------------------------------------------- programme placement rows */

/**
 * The "which qualifications does this course count toward" repeater on the settings
 * tab. A course may sit in several programme parts at once (the NIPR curriculum lists
 * five papers under two programmes), each with its own credit load and status.
 *
 * Exactly one row is primary — it decides which programme's per-paper fee the course
 * inherits, so a paper listed under both CPR and the Professional Variant has one
 * unambiguous price. The radio enforces "one" in the UI; Course::syncProgrammePlacements
 * enforces it again server-side, because a crafted POST can send whatever it likes.
 *
 * @param {Array} programmes [{ id, code, name, parts: [{ id, name }] }]
 * @param {Array} initial    [{ programme_id, programme_part_id, credit_load, requirement, is_primary }]
 */
export function programmePlacements(programmes = [], initial = []) {
    const newKey = () => Date.now() + '-' + Math.random();

    return {
        programmes,
        rows: initial.map((row) => ({
            key: newKey(),
            programme_id: String(row.programme_id ?? ''),
            programme_part_id: String(row.programme_part_id ?? ''),
            credit_load: row.credit_load ?? '',
            requirement: row.requirement ?? '',
            primary: !!row.is_primary,
        })),

        add() {
            const first = this.programmes[0];
            this.rows.push({
                key: newKey(),
                programme_id: first ? String(first.id) : '',
                programme_part_id: '',
                credit_load: '',
                requirement: 'compulsory',
                // The first row a course ever gets is its primary by definition.
                primary: this.rows.length === 0,
            });
        },

        remove(index) {
            const wasPrimary = this.rows[index]?.primary;
            this.rows.splice(index, 1);
            // Never leave the course with placements but no primary.
            if (wasPrimary && this.rows.length) this.rows[0].primary = true;
        },

        /** Radios are mutually exclusive within the group, mirrored into row state. */
        setPrimary(index) {
            this.rows.forEach((row, i) => (row.primary = i === index));
        },

        /** Parts of the programme a given row has selected. */
        partsFor(row) {
            return this.programmes.find((p) => String(p.id) === String(row.programme_id))?.parts ?? [];
        },

        /** Changing the programme invalidates the part chosen under the old one. */
        onProgrammeChange(row) {
            row.programme_part_id = '';
        },
    };
}

/* ------------------------------------------------ dynamic objective rows */

export function objectiveRows(initial = []) {
    return {
        rows: (initial.length ? initial : ['']).map((value, i) => ({ key: i + '-' + Math.random(), value })),

        add() {
            this.rows.push({ key: Date.now() + '-' + Math.random(), value: '' });
        },
        remove(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) this.add();
        },
    };
}
