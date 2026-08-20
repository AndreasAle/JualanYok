<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $since = now()->subDays(29)->startOfDay();

        $revenue = Order::paid()
            ->where('paid_at', '>=', $since)
            ->selectRaw('COUNT(*) orders, COALESCE(SUM(grand_total),0) gross, COALESCE(SUM(platform_fee),0) fees')
            ->first();

        $series = Order::paid()
            ->where('paid_at', '>=', $since)
            ->selectRaw('DATE(paid_at) as date, COUNT(*) orders, COALESCE(SUM(grand_total),0) gross, COALESCE(SUM(platform_fee),0) fees')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'orders' => (int) $r->orders,
                'gross' => Money::round((float) $r->gross),
                'fees' => Money::round((float) $r->fees),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'creators' => User::where('is_creator', true)->count(),
                'stores_live' => Store::live()->count(),
                'orders_30d' => (int) $revenue->orders,
                'gross_30d' => Money::round((float) $revenue->gross),
                'platform_revenue_30d' => Money::round((float) $revenue->fees),
                'payable' => Money::round((float) Wallet::sum('available_balance') + (float) Wallet::sum('pending_balance')),
                'held' => Money::round((float) Wallet::sum('held_balance')),
            ],
            'series' => $series,
            'queues' => [
                'withdrawals_open' => Withdrawal::whereIn('status', ['REQUESTED', 'UNDER_REVIEW'])->count(),
                'refunds_open' => Refund::where('status', 'REQUESTED')->count(),
                'commissions_pending' => Commission::where('status', 'PENDING')->count(),
            ],
            'recentWithdrawals' => Withdrawal::with('user:id,name,username')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Withdrawal $w) => [
                    'number' => $w->number,
                    'user' => $w->user->name,
                    'amount' => (float) $w->amount,
                    'status' => $w->status->value,
                    'status_label' => $w->status->label(),
                    'created_at' => $w->created_at->diffForHumans(),
                ]),
        ]);
    }
}
