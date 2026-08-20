<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSummary;
use App\Models\Order;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Records one event. Visitors are identified by a salted daily hash of
     * IP + user agent — enough to count uniques, not enough to track a person
     * across days or stores.
     */
    public function record(Store $store, string $name, ?Model $subject = null, array $context = [], float $value = 0): void
    {
        AnalyticsEvent::create([
            'store_id' => $store->id,
            'name' => $name,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'visitor_hash' => $context['visitor_hash'] ?? null,
            'session_hash' => $context['session_hash'] ?? null,
            'referrer' => $context['referrer'] ?? null,
            'utm_source' => $context['utm']['source'] ?? null,
            'utm_medium' => $context['utm']['medium'] ?? null,
            'utm_campaign' => $context['utm']['campaign'] ?? null,
            'utm_term' => $context['utm']['term'] ?? null,
            'utm_content' => $context['utm']['content'] ?? null,
            'device' => $context['device'] ?? null,
            'country' => $context['country'] ?? null,
            'value' => $value,
            'meta' => $context['meta'] ?? null,
            'created_at' => now(),
        ]);
    }

    /** Builds the request context once so every event uses the same identity. */
    public function contextFrom(Request $request): array
    {
        $agent = (string) $request->userAgent();

        $visitorHash = hash('sha256', implode('|', [
            $request->ip(),
            $agent,
            now()->toDateString(),                 // rotates daily
            config('app.key'),
        ]));

        return [
            'visitor_hash' => $visitorHash,
            'session_hash' => hash('sha256', $request->session()->getId().config('app.key')),
            'referrer' => $request->headers->get('referer'),
            'utm' => array_filter([
                'source' => $request->query('utm_source'),
                'medium' => $request->query('utm_medium'),
                'campaign' => $request->query('utm_campaign'),
                'term' => $request->query('utm_term'),
                'content' => $request->query('utm_content'),
            ]),
            'device' => $this->deviceFrom($agent),
        ];
    }

    private function deviceFrom(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/tablet|ipad/i', $agent) => 'tablet',
            (bool) preg_match('/mobile|android|iphone/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }

    /**
     * Rolls raw events into the per-day summary table. Dashboards read the
     * summary, never the raw event stream.
     */
    public function aggregate(Store $store, Carbon $date): AnalyticsSummary
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $events = AnalyticsEvent::where('store_id', $store->id)
            ->whereBetween('created_at', [$start, $end]);

        $counts = (clone $events)
            ->selectRaw('name, COUNT(*) as total, COUNT(DISTINCT visitor_hash) as uniques')
            ->groupBy('name')
            ->get()
            ->keyBy('name');

        $orders = Order::where('store_id', $store->id)
            ->paid()
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(grand_total),0) as gross, COALESCE(SUM(seller_net),0) as net')
            ->first();

        $sources = (clone $events)
            ->where('name', AnalyticsEvent::STORE_VIEW)
            ->selectRaw('COALESCE(utm_source, ?) as source, COUNT(*) as total', ['direct'])
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();

        return AnalyticsSummary::updateOrCreate(
            ['store_id' => $store->id, 'date' => $date->toDateString()],
            [
                'views' => (int) ($counts[AnalyticsEvent::STORE_VIEW]->total ?? 0),
                'unique_visitors' => (int) ($counts[AnalyticsEvent::STORE_VIEW]->uniques ?? 0),
                'product_views' => (int) ($counts[AnalyticsEvent::PRODUCT_VIEW]->total ?? 0),
                'block_clicks' => (int) ($counts[AnalyticsEvent::BLOCK_CLICK]->total ?? 0),
                'checkouts' => (int) ($counts[AnalyticsEvent::BEGIN_CHECKOUT]->total ?? 0),
                'leads' => (int) ($counts[AnalyticsEvent::LEAD]->total ?? 0),
                'orders' => (int) ($orders->total ?? 0),
                'gross_revenue' => Money::round((float) ($orders->gross ?? 0)),
                'net_revenue' => Money::round((float) ($orders->net ?? 0)),
                'sources' => $sources,
            ],
        );
    }

    /** Dashboard payload for a date range, with previous-period comparison. */
    public function overview(Store $store, Carbon $from, Carbon $to): array
    {
        $current = $this->rangeTotals($store, $from, $to);

        $length = max(1, $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($length - 1);
        $previous = $this->rangeTotals($store, $prevFrom, $prevTo);

        $series = AnalyticsSummary::where('store_id', $store->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get(['date', 'views', 'orders', 'gross_revenue', 'net_revenue'])
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'views' => (int) $row->views,
                'orders' => (int) $row->orders,
                'gross' => (float) $row->gross_revenue,
                'net' => (float) $row->net_revenue,
            ]);

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => collect($current)->mapWithKeys(fn ($value, $key) => [
                $key => $this->percentChange((float) $previous[$key], (float) $value),
            ])->all(),
            'series' => $series,
        ];
    }

    private function rangeTotals(Store $store, Carbon $from, Carbon $to): array
    {
        $row = AnalyticsSummary::where('store_id', $store->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(views),0) v, COALESCE(SUM(unique_visitors),0) u, COALESCE(SUM(orders),0) o, COALESCE(SUM(gross_revenue),0) g, COALESCE(SUM(net_revenue),0) n, COALESCE(SUM(leads),0) l, COALESCE(SUM(checkouts),0) c')
            ->first();

        $orders = (int) ($row->o ?? 0);
        $views = (int) ($row->v ?? 0);
        $gross = Money::round((float) ($row->g ?? 0));

        return [
            'views' => $views,
            'visitors' => (int) ($row->u ?? 0),
            'orders' => $orders,
            'gross_revenue' => $gross,
            'net_revenue' => Money::round((float) ($row->n ?? 0)),
            'leads' => (int) ($row->l ?? 0),
            'checkouts' => (int) ($row->c ?? 0),
            'conversion_rate' => $views > 0 ? round($orders / $views * 100, 2) : 0.0,
            'average_order_value' => $orders > 0 ? Money::round($gross / $orders) : 0.0,
        ];
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /** Top products in a range, straight from paid order items. */
    public function topProducts(Store $store, Carbon $from, Carbon $to, int $limit = 5): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $store->id)
            ->whereIn('orders.status', ['PAID', 'PROCESSING', 'COMPLETED'])
            ->whereBetween('orders.paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('order_items.product_id, order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'name' => $r->name,
                'quantity' => (int) $r->qty,
                'revenue' => Money::round((float) $r->revenue),
            ])
            ->all();
    }
}
