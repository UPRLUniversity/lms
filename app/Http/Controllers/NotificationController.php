<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Notifications\UprlNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The in-app bell: a polled "recent" feed for the topbar dropdown, the full
 * filterable history page, and mark-read/mark-all-read. Every action is scoped to
 * the signed-in user's own notifications — there is no cross-user access here, so a
 * plain ownership check (rather than a full Policy) gates each one.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Polled by the bell dropdown (~60s) — the latest few plus the unread count.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(8)->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'tile' => NotificationType::toneClasses($this->toneFor($n)),
                'url' => route('notifications.open', $n),
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark read (idempotent) and redirect to the notification's target — the single
     * click-through entry point, so it works identically from the dropdown or the
     * full history page, with or without JS.
     */
    public function open(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($this->owns($request, $notification), 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'All notifications marked as read.']);
        }

        return back()->with('status', 'All notifications marked as read.');
    }

    private function owns(Request $request, DatabaseNotification $notification): bool
    {
        $user = $request->user();

        return $notification->notifiable_type === $user::class && $notification->notifiable_id === $user->id;
    }

    /**
     * The semantic tone for a stored notification, resolved from its originating
     * class (the DB `type`), or gold for anything unrecognised.
     */
    private function toneFor(DatabaseNotification $notification): string
    {
        $class = $notification->type;

        if (is_string($class) && is_subclass_of($class, UprlNotification::class)) {
            return $class::type()->tone();
        }

        return 'gold';
    }
}
