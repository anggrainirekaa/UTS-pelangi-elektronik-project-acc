<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $request->user()->unreadNotifications() : $request->user()->notifications();

        return view('notifications.index', [
            'notifications' => $query->latest()->paginate(15)->withQueryString(),
            'filter' => $filter,
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** Tandai dibaca lalu arahkan ke halaman terkait. Hanya notifikasi milik pengguna sendiri. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Hanya tujuan internal (path atau host aplikasi) yang diikuti.
        if ($url && (str_starts_with($url, '/') || str_starts_with($url, url('/')))) {
            return redirect($url);
        }

        return redirect()->route('notifikasi.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
