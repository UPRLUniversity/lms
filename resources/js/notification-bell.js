/**
 * The topbar bell: polls /notifications/recent every 60s for the unread count +
 * latest few, so the badge stays live without a websocket (a future upgrade — see
 * docs/decisions.md). Fetches immediately on open too, so the dropdown never shows
 * stale data from the last poll.
 */
export default function notificationBell({ recentUrl, markAllUrl }) {
    return {
        open: false,
        loading: true,
        unreadCount: 0,
        notifications: [],

        init() {
            this.fetchRecent();
            setInterval(() => this.fetchRecent(), 60000);
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.fetchRecent();
            }
        },

        fetchRecent() {
            fetch(recentUrl, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((data) => {
                    this.unreadCount = data.unread_count;
                    this.notifications = data.notifications;
                    this.loading = false;
                })
                .catch(() => {
                    this.loading = false;
                });
        },

        markAllRead() {
            this.notifications = this.notifications.map((n) => ({ ...n, read: true }));
            this.unreadCount = 0;

            fetch(markAllUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            }).catch(() => {});
        },
    };
}
