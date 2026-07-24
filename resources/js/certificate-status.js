/**
 * Polls the certificate status endpoint while the queued PDF render is in flight
 * (rendered_at is null). Used on the completion screen and the My Certificates card —
 * both start from whatever `ready` the server already knew when the page loaded, so a
 * certificate that's already rendered never polls at all.
 */
export default function certificateStatus({ ready, statusUrl }) {
    return {
        ready,

        init() {
            if (!this.ready) {
                this.poll();
            }
        },

        poll() {
            const tick = () => {
                fetch(statusUrl, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.ready) {
                            this.ready = true;
                        } else {
                            setTimeout(tick, 3000);
                        }
                    })
                    .catch(() => setTimeout(tick, 5000));
            };

            setTimeout(tick, 3000);
        },
    };
}
