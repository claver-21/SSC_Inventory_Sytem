@extends('layouts.app')

@section('page_title', 'Notifications')
@section('page_subtitle', 'System alerts for borrowing approvals, overdue items, damaged returns, and low stock')

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('notifications.index') }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ $filter !== 'unread' ? 'bg-maroon-900 text-white shadow-xs' : 'border border-gray-200 bg-white text-gray-700' }}">
                All Notifications
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="rounded-xl px-4 py-2 text-xs font-semibold {{ $filter === 'unread' ? 'bg-maroon-900 text-white shadow-xs' : 'border border-gray-200 bg-white text-gray-700' }}">
                Unread ({{ $unreadCount }})
            </a>
        </div>

        @if ($unreadCount > 0 || $notifications->total() > 0)
            <div class="flex flex-wrap items-center gap-2">
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-maroon-200 bg-maroon-50 px-3.5 py-2 text-xs font-semibold text-maroon-900 hover:bg-maroon-100">
                            Mark All as Read
                        </button>
                    </form>
                @endif

                @if ($notifications->total() > 0)
                    <form method="POST" action="{{ route('notifications.destroy-all') }}" onsubmit="return confirm('Delete all notifications?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3.5 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100">
                            <x-icon name="trash" class="w-3.5 h-3.5" />
                            Clear All
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-empty-state title="No notifications" message="You do not have any notifications in this view." icon="bell" />
    @else
        <div class="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            @foreach ($notifications as $notification)
                <div class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center {{ $notification->isRead() ? 'bg-white' : 'bg-maroon-50/40' }}">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            @if (!$notification->isRead())
                                <span class="h-2 w-2 shrink-0 rounded-full bg-maroon-700"></span>
                            @endif
                            <p class="text-sm font-bold text-charcoal">{{ $notification->title }}</p>
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 uppercase">
                                {{ str_replace('_', ' ', $notification->type) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-600">{{ $notification->message }}</p>
                        <p class="text-[11px] text-gray-400">{{ $notification->created_at?->format('M d, Y h:i A') }} ({{ $notification->created_at?->diffForHumans() }})</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($notification->action_url)
                            <a href="{{ $notification->action_url }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                Open Record
                            </a>
                        @endif
                        @if (!$notification->isRead())
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-maroon-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-800">
                                    Mark Read
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('notifications.destroy', $notification) }}" onsubmit="return confirm('Delete this notification?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100">
                                <x-icon name="trash" class="w-3.5 h-3.5" />
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
