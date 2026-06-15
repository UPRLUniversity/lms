/**
 * The learning player's client behaviour, as a single Alpine component:
 *   - "Complete & Continue" via a lightweight async POST (resilient to double-clicks),
 *     with a tasteful module/course completion micro-moment before advancing
 *   - un-marking a lesson
 *   - uploaded-video resume + periodic position persistence
 *   - keyboard navigation (←/→) with focus safety
 *
 * Progress writes degrade gracefully: the Complete button lives in a real <form>, so
 * with JS off it posts normally and the server redirects to the next lesson.
 */
export default function learnPlayer(config) {
    return {
        percent: config.percent ?? 0,
        done: Object.assign({}, config.done || {}),
        currentId: config.currentId,
        completing: false,
        celebrating: false,
        celebration: { title: '', message: '' },
        drawer: false,
        collapsed: JSON.parse(localStorage.getItem('uprl:learn-focus') ?? 'false'),

        init() {
            this.$watch('collapsed', (v) => localStorage.setItem('uprl:learn-focus', JSON.stringify(v)));
            this.setupVideo();
        },

        isDone(id) {
            return !!this.done[id];
        },

        /** Mark the current lesson complete, then celebrate / advance. */
        async complete() {
            if (this.completing || this.isDone(this.currentId)) return;
            this.completing = true;

            try {
                const data = await this.post(config.routes.complete);
                this.done[this.currentId] = true;
                this.percent = data.percent;

                if (data.course_completed) {
                    this.celebrate(
                        { title: 'Course complete!', message: 'You finished every lesson. Beautifully done.' },
                        () => (window.location = data.congratulations_url),
                    );
                    return;
                }

                if (data.module_completed) {
                    this.celebrate(
                        { title: 'Module complete', message: `You finished “${data.module_title}”.` },
                        () => this.advance(data.next_url),
                    );
                    return;
                }

                this.advance(data.next_url);
            } catch (e) {
                this.completing = false;
                this.toast('Could not save your progress — check your connection and try again.', 'error');
            }
        },

        advance(nextUrl) {
            if (nextUrl) {
                window.location = nextUrl;
            } else {
                this.completing = false;
                this.toast('Lesson complete.', 'success');
            }
        },

        async markIncomplete() {
            try {
                const data = await this.post(config.routes.incomplete);
                this.done[this.currentId] = false;
                this.percent = data.percent;
                this.toast('Marked as incomplete.', 'info');
            } catch (e) {
                this.toast('Could not update the lesson — please try again.', 'error');
            }
        },

        /**
         * Show the completion micro-moment, then run `then`. Respects reduced-motion
         * by skipping the overlay (a quick toast instead) — never confetti spam.
         */
        celebrate(detail, then) {
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduced) {
                this.toast(`${detail.title} — ${detail.message}`, 'success');
                setTimeout(then, 250);
                return;
            }

            this.celebration = detail;
            this.celebrating = true;
            setTimeout(then, 1500);
        },

        onKey(e) {
            const t = e.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) {
                return;
            }
            if (e.key === 'ArrowRight' && config.routes.next) {
                window.location = config.routes.next;
            } else if (e.key === 'ArrowLeft' && config.routes.previous) {
                window.location = config.routes.previous;
            }
        },

        /* ---- Uploaded video: resume + periodic position persistence ---------- */

        setupVideo() {
            if (!config.isUploadedVideo) return;

            const video = document.getElementById('lesson-video');
            if (!video) return;

            // Resume from the last saved position (within ~5s accuracy).
            if (config.resumePosition > 0) {
                video.addEventListener(
                    'loadedmetadata',
                    () => {
                        try {
                            if (config.resumePosition < video.duration) {
                                video.currentTime = config.resumePosition;
                            }
                        } catch (_) {}
                    },
                    { once: true },
                );
            }

            // Persist position at most every ~10s of playback, and on pause/leave.
            let lastSaved = 0;
            video.addEventListener('timeupdate', () => {
                const now = Math.floor(video.currentTime);
                if (now - lastSaved >= 10) {
                    lastSaved = now;
                    this.ping(now);
                }
            });
            video.addEventListener('pause', () => this.ping(Math.floor(video.currentTime)));
            window.addEventListener('beforeunload', () => this.ping(Math.floor(video.currentTime), true));
        },

        ping(position, keepalive = false) {
            const body = JSON.stringify({ position_seconds: position, seconds_spent: position });
            this.post(config.routes.position, body, keepalive).catch(() => {});
        },

        /* ---- helpers --------------------------------------------------------- */

        async post(url, body = null, keepalive = false) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: body ?? '{}',
                keepalive,
            });
            if (!res.ok) throw new Error(`Request failed: ${res.status}`);
            return res.status === 204 ? {} : res.json();
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },
    };
}
