<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('notifications/index', [
            'items' => $request->user()->notifications()->paginate(25)->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $target = (string) ($record->data['url'] ?? '');
        $sameHost = parse_url($target, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST)
            || parse_url($target, PHP_URL_HOST) === $request->getHost();

        return redirect($sameHost ? $target : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        $this->toast('সব নোটিফিকেশন পড়া হয়েছে বলে চিহ্নিত করা হলো।');

        return back();
    }
}
