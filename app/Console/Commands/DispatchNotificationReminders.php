<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Inventory;
use App\Models\Subscription;
use App\Services\NotificationCenterService;
use Illuminate\Console\Command;

class DispatchNotificationReminders extends Command
{
    protected $signature = 'jualanyok:notification-reminders';

    protected $description = 'Buat reminder proaktif untuk langganan dan stok yang perlu ditindaklanjuti';

    public function handle(NotificationCenterService $notifications): int
    {
        $created = $this->subscriptionReminders($notifications)
            + $this->inventoryReminders($notifications);

        $this->info("{$created} reminder baru dibuat.");

        return self::SUCCESS;
    }

    private function subscriptionReminders(NotificationCenterService $notifications): int
    {
        $created = 0;

        foreach ([7, 3, 1, 0] as $days) {
            Subscription::query()
                ->with(['user', 'plan'])
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                ->whereDate('current_period_end', now()->addDays($days)->toDateString())
                ->chunkById(100, function ($subscriptions) use ($notifications, $days, &$created) {
                    foreach ($subscriptions as $subscription) {
                        if (! $subscription->user || ! $subscription->current_period_end) {
                            continue;
                        }

                        $endingToday = $days === 0;
                        $title = $endingToday
                            ? 'Paket berakhir hari ini'
                            : "Paket berakhir {$days} hari lagi";

                        $created += (int) $notifications->sendOnce($subscription->user, [
                            'type' => 'subscription.expiring',
                            'category' => 'subscription',
                            'priority' => $days <= 1 ? 'high' : 'normal',
                            'title' => $title,
                            'message' => sprintf(
                                'Paket %s aktif sampai %s. Perpanjang agar fitur toko tidak terhenti.',
                                $subscription->plan?->name ?? 'JualanYok',
                                $subscription->current_period_end->translatedFormat('d F Y'),
                            ),
                            'url' => route('creator.subscription'),
                            'action_label' => 'Lihat langganan',
                            'action_required' => true,
                            'group_key' => "subscription:{$subscription->id}:expires:{$days}",
                            'tone' => $days <= 1 ? 'danger' : 'warning',
                            'email_required' => $days <= 1,
                            'meta' => [
                                'subscription_id' => $subscription->id,
                                'days_remaining' => $days,
                            ],
                        ], 48);
                    }
                });
        }

        return $created;
    }

    private function inventoryReminders(NotificationCenterService $notifications): int
    {
        $created = 0;

        Inventory::query()
            ->with(['product.store.owner', 'variant'])
            ->where('track_stock', true)
            ->where('allow_backorder', false)
            ->whereRaw('(quantity - reserved) <= low_stock_threshold')
            ->chunkById(100, function ($inventories) use ($notifications, &$created) {
                foreach ($inventories as $inventory) {
                    $product = $inventory->product;
                    $owner = $product?->store?->owner;

                    if (! $product || ! $owner) {
                        continue;
                    }

                    $available = $inventory->availableQuantity();
                    $empty = $available === 0;
                    $label = $inventory->variant?->name
                        ? "{$product->name} - {$inventory->variant->name}"
                        : $product->name;

                    $created += (int) $notifications->sendOnce($owner, [
                        'type' => $empty ? 'inventory.out' : 'inventory.low',
                        'category' => 'inventory',
                        'priority' => $empty ? 'high' : 'normal',
                        'title' => $empty ? 'Stok masih habis' : 'Stok masih menipis',
                        'message' => sprintf('%s tersisa %d unit. Segera perbarui agar penjualan tidak terhenti.', $label, $available),
                        'url' => route('creator.products.edit', $product),
                        'action_label' => 'Perbarui stok',
                        'action_required' => true,
                        'group_key' => 'inventory:'.$inventory->id.':reminder:'.($empty ? 'out' : 'low'),
                        'tone' => $empty ? 'danger' : 'warning',
                        'email_required' => $empty,
                        'meta' => [
                            'product_id' => $product->id,
                            'inventory_id' => $inventory->id,
                            'available' => $available,
                        ],
                    ], 23);
                }
            });

        return $created;
    }
}
