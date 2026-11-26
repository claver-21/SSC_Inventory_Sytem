<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'all');

        $query = AppNotification::query()
            ->where('user_id', $request->user()->id);

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->latest()->paginate(20)->withQueryString(),
            'unreadCount' => AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'filter' => $filter,
        ]);
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $unreadCount = AppNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $items = AppNotification::where('user_id', $userId)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'severity' => $n->severity,
                'action_url' => $n->action_url,
                'read_at' => $n->read_at?->toIso8601String(),
                'time_ago' => $n->created_at?->diffForHumans() ?? 'Just now',
            ]);

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification): RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return back()->with('success', 'Notification deleted.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'All notifications cleared.');
    }
}
