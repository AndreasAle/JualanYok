<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateDashboardController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user->walletOrCreate();

        return Inertia::render('Affiliate/Dashboard', [
            'summary' => $this->affiliates->summaryFor($user->id),
            'wallet' => [
                'pending' => (float) $wallet->pending_balance,
                'available' => (float) $wallet->available_balance,
                'held' => (float) $wallet->held_balance,
            ],
            'links' => $user->affiliateLinks()
                ->with(['program.store:id,name,username', 'product:id,name,thumbnail_path'])
                ->orderByDesc('conversions')
                ->limit(8)
                ->get()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'code' => $l->code,
                    'store' => $l->program->store->name,
                    'product' => $l->product?->name,
                    'campaign' => $l->campaign,
                    'clicks' => $l->clicks,
                    'conversions' => $l->conversions,
                    'revenue' => (float) $l->revenue,
                    'conversion_rate' => $l->conversionRate(),
                    'url' => $l->shareUrl(),
                ]),
            'recentCommissions' => Commission::where('user_id', $user->id)
                ->with('order:id,number,customer_name', 'store:id,name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Commission $c) => [
                    'id' => $c->id,
                    'order_number' => $c->order->number,
                    'store' => $c->store->name,
                    'amount' => (float) $c->amount,
                    'status' => $c->status->value,
                    'status_label' => $c->status->label(),
                    'available_at' => $c->available_at?->toDateString(),
                    'created_at' => $c->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function commissions(Request $request): Response
    {
        $commissions = Commission::where('user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->with('order:id,number', 'store:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Commission $c) => [
                'id' => $c->id,
                'order_number' => $c->order->number,
                'store' => $c->store->name,
                'base_amount' => (float) $c->base_amount,
                'amount' => (float) $c->amount,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'available_at' => $c->available_at?->toDateString(),
                'created_at' => $c->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Affiliate/Commissions', [
            'commissions' => $commissions,
            'filters' => $request->only('status'),
            'summary' => $this->affiliates->summaryFor($request->user()->id),
        ]);
    }
}
