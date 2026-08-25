<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanPaymentService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly PlanPaymentService $planPayments,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();
        $store = $user->store;

        return Inertia::render('Creator/Subscription', [
            'current' => $subscription ? [
                'plan' => $subscription->plan->slug,
                'plan_name' => $subscription->plan->name,
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'interval' => $subscription->billing_interval,
                'amount' => (float) $subscription->amount,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            ] : null,
            'plans' => Plan::with('features')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'tagline' => $p->tagline,
                    'price_monthly' => (float) $p->price_monthly,
                    'price_yearly' => (float) $p->price_yearly,
                    'transaction_fee_percent' => (float) $p->transaction_fee_percent,
                    'trial_days' => $p->trial_days,
                    'highlights' => $p->highlights ?? [],
                    'features' => $p->features->map(fn ($f) => [
                        'key' => $f->key,
                        'label' => $f->label,
                        'enabled' => (bool) $f->enabled,
                        'limit' => $f->limit,
                    ]),
                ]),
            'usage' => [
                'products' => [
                    'used' => $store?->products()->count() ?? 0,
                    'limit' => $this->plans->limit($user, PlanService::PRODUCTS_LIMIT),
                ],
                'blocks' => [
                    'used' => $store?->blocks()->count() ?? 0,
                    'limit' => $this->plans->limit($user, PlanService::BLOCKS_LIMIT),
                ],
            ],
            'invoices' => $subscription?->invoices()->latest()->limit(12)->get()->map(fn ($i) => [
                'number' => $i->number,
                'amount' => (float) $i->amount,
                'status' => $i->status,
                'period_start' => $i->period_start->toDateString(),
                'period_end' => $i->period_end->toDateString(),
            ]) ?? [],
            'billing' => [
                'enabled' => $this->planPayments->enabled(),
                'provider' => $this->planPayments->usesIpaymu() ? 'ipaymu' : 'qris',
                'provider_name' => $this->planPayments->providerName(),
                'automatic' => $this->planPayments->automatic(),
                'merchant' => $this->planPayments->merchantName(),
                'window_minutes' => $this->planPayments->minutesToPay(),
            ],
            'openPayment' => ($open = $this->planPayments->openPaymentFor($user)) ? [
                'reference' => $open->reference,
                'plan_name' => $open->plan->name,
                'amount' => (int) $open->amount,
                'status' => $open->status->value,
                'status_label' => $open->status->label(),
            ] : null,
        ]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();

        $isFree = (float) ($data['interval'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly) <= 0;

        // With billing configured, a paid plan must actually be paid for. The UI
        // posts to the payment endpoint instead; this is the server-side guard
        // that stops a crafted request from granting a plan for free.
        if ($this->planPayments->enabled() && ! $isFree) {
            throw ValidationException::withMessages([
                'plan' => 'Paket berbayar harus lewat halaman pembayaran.',
            ]);
        }

        $this->plans->subscribe($request->user(), $plan, $data['interval']);

        return back()->with('success', "Mantap! Kamu sekarang di paket {$plan->name}.");
    }

    public function cancel(Request $request)
    {
        $subscription = $request->user()->activeSubscription();

        abort_unless($subscription, 404);

        $this->plans->cancel($subscription, immediately: false);

        return back()->with('info', 'Langganan akan berhenti di akhir periode berjalan.');
    }
}
