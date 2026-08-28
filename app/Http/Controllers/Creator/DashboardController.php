<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        [$from, $to] = $this->range($request);

        $overview = $this->analytics->overview($store, $from, $to);
        $wallet = $request->user()->walletOrCreate();

        return Inertia::render('Creator/Dashboard', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'stats' => $overview['current'],
            'change' => $overview['change'],
            'series' => $overview['series'],
            'topProducts' => $this->analytics->topProducts($store, $from, $to),
            'balance' => [
                'pending' => (float) $wallet->pending_balance,
                'available' => (float) $wallet->available_balance,
                'held' => (float) $wallet->held_balance,
                'reserve' => (float) $wallet->reserve_balance,
                'negative' => (float) $wallet->negative_balance,
                'withdrawn' => (float) $wallet->withdrawn_balance,
            ],
            'recentOrders' => Order::where('store_id', $store->id)
                ->with('items')
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Order $o) => [
                    'number' => $o->number,
                    'customer_name' => $o->customer_name,
                    'grand_total' => (float) $o->grand_total,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'items_count' => $o->items->count(),
                    'created_at' => $o->created_at->diffForHumans(),
                ]),
            'checklist' => $this->checklist($store),
            'store' => [
                'username' => $store->username,
                'name' => $store->name,
                'is_published' => (bool) $store->is_published,
                'public_url' => $store->publicUrl(),
                'products_count' => $store->products()->count(),
                'blocks_count' => $store->blocks()->count(),
            ],
        ]);
    }

    /** Drives the onboarding checklist card; each item links to the real screen. */
    private function checklist($store): array
    {
        return [
            [
                'key' => 'profile',
                'label' => 'Lengkapi profil toko',
                'done' => filled($store->bio) && filled($store->avatar_path),
                'href' => route('creator.settings'),
            ],
            [
                'key' => 'product',
                'label' => 'Tambah produk pertama',
                'done' => $store->products()->exists(),
                'href' => route('creator.products.create'),
            ],
            [
                'key' => 'blocks',
                'label' => 'Atur tampilan toko',
                'done' => $store->blocks()->count() >= 3,
                'href' => route('creator.builder'),
            ],
            [
                'key' => 'payout',
                'label' => 'Daftarkan rekening pencairan',
                'done' => $store->owner->payoutMethods()->exists(),
                'href' => route('creator.withdrawals.index'),
            ],
            [
                'key' => 'publish',
                'label' => 'Publikasikan toko',
                'done' => (bool) $store->is_published,
                'href' => route('creator.builder'),
            ],
        ];
    }

    private function range(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))
            : now();

        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))
            : $to->copy()->subDays(29);

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
