<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';
        $items = $user->notificationsFeed()->with('visit')->latest('id')
            ->when($tab === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->limit(100)->get()
            ->groupBy(fn ($n) => $n->created_at->format('Y-m-d'));

        return view('notifications.index', ['groups' => $items, 'tab' => $tab, 'unread' => $user->unreadCount(), 'prefs' => $user->prefs ?? []]);
    }

    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return $notification->visit_id ? redirect()->route('visits.show', $notification->visit_id) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notificationsFeed()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'in:notify_dashboard,notify_email,notify_sound'],
            'value' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        $user->update(['prefs' => array_merge(User::DEFAULT_PREFS, $user->prefs ?? [], [$data['key'] => (bool) $data['value']])]);

        return response()->json(['ok' => true, 'key' => $data['key'], 'value' => (bool) $data['value']]);
    }

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadCount(),
            'latest_id' => (int) $user->notificationsFeed()->where('type', 'visit_new')->max('id'),
            'sound' => $user->pref('notify_sound'),
        ]);
    }
}
