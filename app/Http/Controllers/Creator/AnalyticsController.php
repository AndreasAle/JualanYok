<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Services\AnalyticsService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : $to->copy()->subDays(29);

        $overview = $this->analytics->overview($store, $from->startOfDay(), $to->endOfDay());

        $sources = collect($store->analyticsSummaries()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('sources'))
            ->flatMap(fn ($s) => $s ?? [])
            ->groupBy(fn ($v, $k) => $k)
            ->map(fn ($group) => array_sum($group->toArray()));

        $sourceTotals = [];
        foreach ($store->analyticsSummaries()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('sources') as $row) {
            foreach ($row ?? [] as $key => $value) {
                $sourceTotals[$key] = ($sourceTotals[$key] ?? 0) + $value;
            }
        }
        arsort($sourceTotals);

        return Inertia::render('Creator/Analytics', [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'stats' => $overview['current'],
            'change' => $overview['change'],
            'series' => $overview['series'],
            'topProducts' => $this->analytics->topProducts($store, $from, $to, 8),
            'sources' => $sourceTotals,
            'funnel' => [
                'views' => $overview['current']['views'],
                'checkouts' => $overview['current']['checkouts'],
                'orders' => $overview['current']['orders'],
            ],
            'topBlocks' => $store->blocks()
                ->orderByDesc('clicks')
                ->limit(6)
                ->get(['id', 'type', 'title', 'clicks', 'impressions'])
                ->map(fn ($b) => [
                    'title' => $b->title ?: $b->type->label(),
                    'clicks' => $b->clicks,
                    'impressions' => $b->impressions,
                ]),
            'advanced' => $this->plans->allows($request->user(), PlanService::ADVANCED_ANALYTICS),
            'eventNames' => [
                AnalyticsEvent::STORE_VIEW, AnalyticsEvent::PRODUCT_VIEW,
                AnalyticsEvent::BEGIN_CHECKOUT, AnalyticsEvent::PURCHASE,
            ],
        ]);
    }
}
