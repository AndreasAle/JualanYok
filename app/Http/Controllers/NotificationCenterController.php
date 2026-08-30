<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notifications,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();
        $area = $this->area($request);

        $query = $user->notifications()
            ->whereNull('archived_at')
            ->when(array_key_exists($category, $this->preferences->categories()), fn ($q) => $q->where('data->category', $category))
            ->when($status === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($status === 'action', fn ($q) => $q->where('data->action_required', true)->whereNull('resolved_at'))
            ->latest();

        $items = $query->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification) => $this->notifications->present($notification));

        $active = $user->notifications()->whereNull('archived_at')->get();

        return Inertia::render('Notifications/Index', [
            'items' => $items,
            'area' => $area,
            'filters' => ['category' => $category, 'status' => $status],
            'stats' => [
                'all' => $active->count(),
                'unread' => $active->whereNull('read_at')->count(),
                'action' => $active->filter(fn (DatabaseNotification $notification) => (bool) ($notification->data['action_required'] ?? false)
                    && $notification->resolved_at === null
                )->count(),
            ],
            'categories' => collect($this->preferences->categories())->map(fn ($definition, $key) => [
                'value' => $key,
                'label' => $definition['label'],
            ])->values(),
            'preferences' => $this->preferences->options($user),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $this->owned($request, $notification);
        $item->markAsRead();

        return redirect()->to($this->safeUrl((string) ($item->data['url'] ?? ''), $request));
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->owned($request, $notification)->markAsRead();

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->whereNull('archived_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi sudah dibaca.');
    }

    public function resolve(Request $request, string $notification): RedirectResponse
    {
        $this->owned($request, $notification)->forceFill([
            'read_at' => now(),
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', 'Notifikasi ditandai selesai.');
    }

    public function archive(Request $request, string $notification): RedirectResponse
    {
        $this->owned($request, $notification)->forceFill([
            'read_at' => now(),
            'archived_at' => now(),
        ])->save();

        return back()->with('success', 'Notifikasi diarsipkan.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $categories = array_keys($this->preferences->categories());
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.category' => ['required', Rule::in($categories)],
            'preferences.*.email_frequency' => ['required', Rule::in(['immediate', 'daily', 'off'])],
        ]);

        $values = collect($data['preferences'])->mapWithKeys(fn ($preference) => [
            $preference['category'] => $preference['email_frequency'],
        ])->all();

        $this->preferences->update($request->user(), $values);

        return back()->with('success', 'Preferensi notifikasi disimpan.');
    }

    private function owned(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    private function safeUrl(string $url, Request $request): string
    {
        if ($url === '') {
            return route('notifications.index');
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host && hash_equals(strtolower($request->getHost()), strtolower((string) $host))
            ? $url
            : route('notifications.index');
    }

    private function area(Request $request): string
    {
        $requested = $request->string('area')->toString();
        $user = $request->user();

        return match (true) {
            $requested === 'admin' && $user->isAdmin() => 'admin',
            $requested === 'affiliate' && $user->is_affiliate => 'affiliate',
            $requested === 'member' && ! $user->is_creator => 'member',
            default => $user->is_creator ? 'creator' : ($user->isAdmin() ? 'admin' : 'member'),
        };
    }
}
