<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanUsage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for what a plan allows. Feature keys are never
 * hard-coded in controllers — they are resolved here from plan_features, so a
 * super admin can change a limit without a deploy.
 */
class PlanService
{
    public const PRODUCTS_LIMIT = 'products.limit';

    public const BLOCKS_LIMIT = 'blocks.limit';

    public const CUSTOM_DOMAIN = 'domain.custom';

    public const REMOVE_BRANDING = 'branding.remove';

    public const PIXELS = 'marketing.pixels';

    public const WEBHOOKS = 'integration.webhooks';

    public const EMAIL_BROADCAST = 'marketing.broadcast';

    public const AFFILIATE_TOOLS = 'affiliate.tools';

    public const TEAM_MEMBERS = 'team.members';

    public const ADVANCED_ANALYTICS = 'analytics.advanced';

    public const PREMIUM_TEMPLATES = 'templates.premium';

    public function planFor(User $user): Plan
    {
        return $user->currentPlan()->load('features');
    }

    public function allows(User $user, string $key): bool
    {
        $feature = $this->planFor($user)->feature($key);

        return $feature?->enabled ?? false;
    }

    /** Null means unlimited. */
    public function limit(User $user, string $key): ?int
    {
        $feature = $this->planFor($user)->feature($key);

        if (! $feature || ! $feature->enabled) {
            return 0;
        }

        return $feature->limit;
    }

    public function remaining(User $user, string $key, int $used): ?int
    {
        $limit = $this->limit($user, $key);

        return $limit === null ? null : max(0, $limit - $used);
    }

    public function withinLimit(User $user, string $key, int $used): bool
    {
        $limit = $this->limit($user, $key);

        return $limit === null || $used < $limit;
    }

    /** Throws a friendly upgrade prompt when a limit is hit. */
    public function ensureWithinLimit(User $user, string $key, int $used, string $label): void
    {
        if (! $this->withinLimit($user, $key, $used)) {
            $limit = $this->limit($user, $key);

            throw ValidationException::withMessages([
                'plan' => sprintf(
                    'Paket %s cuma bisa %d %s. Upgrade dulu ya biar bisa nambah lagi.',
                    $this->planFor($user)->name,
                    $limit,
                    $label,
                ),
            ]);
        }
    }

    public function ensureAllowed(User $user, string $key, string $label): void
    {
        if (! $this->allows($user, $key)) {
            throw ValidationException::withMessages([
                'plan' => sprintf('Fitur %s belum tersedia di paket %s. Upgrade dulu yuk.', $label, $this->planFor($user)->name),
            ]);
        }
    }

    /**
     * Starts or switches a subscription. In development this settles through
     * the mock provider; the shape is ready for a real gateway subscription id.
     */
    public function subscribe(
        User $user,
        Plan $plan,
        string $interval = 'monthly',
        ?string $provider = null,
        ?string $providerReference = null,
    ): Subscription {
        $current = $user->activeSubscription();

        $current?->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $amount = $interval === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        // A successfully paid checkout starts immediately. Trials only apply
        // to subscriptions created without a settled payment provider.
        $trialEnds = $plan->trial_days > 0 && ! $current && $provider === null
            ? now()->addDays($plan->trial_days)
            : null;

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $trialEnds ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'billing_interval' => $interval,
            'amount' => $amount,
            'provider' => $provider ?? config('payments.default'),
            'provider_reference' => $providerReference,
            'trial_ends_at' => $trialEnds,
            'current_period_start' => now(),
            'current_period_end' => $interval === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);
    }

    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        $subscription->update($immediately
            ? ['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]
            : ['cancel_at_period_end' => true, 'cancelled_at' => now()],
        );

        return $subscription;
    }

    /** Expires lapsed subscriptions; run from the scheduler. */
    public function expireLapsed(): int
    {
        return Subscription::whereIn('status', [
            SubscriptionStatus::Active->value,
            SubscriptionStatus::Trialing->value,
            SubscriptionStatus::PastDue->value,
        ])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->where(function ($q) {
                $q->where('cancel_at_period_end', true)
                    ->orWhere('grace_ends_at', '<', now());
            })
            ->update(['status' => SubscriptionStatus::Expired->value]);
    }

    public function trackUsage(User $user, string $key, int $delta = 1, ?string $period = null): void
    {
        $usage = PlanUsage::firstOrCreate(
            ['user_id' => $user->id, 'key' => $key, 'period' => $period],
            ['used' => 0],
        );

        $usage->increment('used', $delta);
    }

    /** Feature snapshot shared with the frontend for gating UI affordances. */
    public function snapshot(User $user): array
    {
        $plan = $this->planFor($user);

        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'transaction_fee_percent' => (float) $plan->transaction_fee_percent,
            'features' => $plan->features->mapWithKeys(fn ($f) => [
                $f->key => ['enabled' => (bool) $f->enabled, 'limit' => $f->limit],
            ])->all(),
        ];
    }
}
