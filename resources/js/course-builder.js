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
            if (!el) return;
            const action = el.dataset.action;

            if (action === 'toggle-module') return this.toggleModule(el);
            if (action === 'add-lesson') return this.openLessonEditor(Number(el.dataset.moduleId));
            if (action === 'edit-lesson') return this.openLessonEditor(null, Number(el.dataset.lessonId));
            if (action === 'delete-lesson') return this.deleteLesson(Number(el.dataset.lessonId));
            if (action === 'delete-module') return this.deleteModule(Number(el.dataset.moduleId));
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

            this.$refs.outline.querySelectorAll('[data-lesson-list]').forEach((list) => {
                this.sortables.push(new Sortable(list, {
                    ...SORTABLE_OPTS,
                    group: 'lessons',
                    handle: '[data-drag-lesson]',
                    onEnd: () => this.persistOrder(),
                }));
            });
        },

        persistOrder() {
            const order = [...this.$refs.outline.querySelectorAll('[data-module]')].map((module) => ({
                module_id: Number(module.dataset.moduleId),
                lessons: [...module.querySelectorAll('[data-lesson]')].map((l) => Number(l.dataset.lessonId)),
            }));

            const data = new FormData();
            data.append('order', JSON.stringify(order));
            // Send as JSON so nested arrays survive.
            fetch(this.urlReorder(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ order }),
            })
                .then((res) => { if (!res.ok) throw new Error(); toast('Order saved.'); })
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
