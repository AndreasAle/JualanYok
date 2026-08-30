<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\SubscriptionStatus;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Subscription;
use App\Notifications\BusinessNotification;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
    }

    public function test_notification_center_only_lists_the_signed_in_users_notifications(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $other = $this->makeUser([Role::CREATOR]);

        $this->databaseNotification($user, 'Pesanan baru', 'orders:mine');
        $this->databaseNotification($other, 'Bukan milik saya', 'orders:other');

        $this->actingAs($user)
            ->get('/notifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Pesanan baru')
                ->where('stats.unread', 1));
    }

    public function test_opening_a_notification_marks_it_read_and_rejects_cross_account_access(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $other = $this->makeUser([Role::CREATOR]);
        $mine = $this->databaseNotification($user, 'Stok menipis', 'inventory:1', '/dashboard/produk');
        $theirs = $this->databaseNotification($other, 'Rahasia', 'finance:1');

        $this->actingAs($user)
            ->get("/notifikasi/{$mine->id}/buka")
            ->assertRedirect('/dashboard/produk');

        $this->assertNotNull($mine->fresh()->read_at);

        $this->actingAs($user)
            ->patch("/notifikasi/{$theirs->id}/baca")
            ->assertNotFound();
    }

    public function test_header_groups_repeated_notifications_and_counts_required_actions(): void
    {
        $user = $this->makeUser([Role::CREATOR]);
        $this->databaseNotification($user, 'Stok menipis', 'inventory:12');
        $this->databaseNotification($user, 'Stok menipis lagi', 'inventory:12');

        $header = app(NotificationCenterService::class)->header($user);

        $this->assertSame(2, $header['unread_count']);
        $this->assertSame(2, $header['action_count']);
        $this->assertCount(1, $header['items']);
        $this->assertSame(2, $header['items'][0]['group_count']);

        $user->unreadNotifications()->update(['read_at' => now()]);
        $afterReading = app(NotificationCenterService::class)->header($user);

        $this->assertSame(0, $afterReading['unread_count']);
        $this->assertSame(2, $afterReading['action_count']);
        $this->assertCount(1, $afterReading['items']);
    }

    public function test_users_can_change_optional_email_preferences_but_not_locked_categories(): void
    {
        $user = $this->makeUser([Role::CREATOR]);

        $this->actingAs($user)->put('/notifikasi/preferensi/email', [
            'preferences' => [
                ['category' => 'inventory', 'email_frequency' => 'off'],
                ['category' => 'finance', 'email_frequency' => 'off'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'inventory',
            'email_frequency' => 'off',
        ]);
        $this->assertFalse(NotificationPreference::where('user_id', $user->id)->where('category', 'finance')->exists());
    }

    public function test_reminder_command_creates_subscription_and_inventory_actions_without_duplicates(): void
    {
        $owner = $this->makeUser([Role::CREATOR]);
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, [
            'type' => ProductType::Physical,
            'stock' => 2,
        ]);
        $product->inventories()->firstOrFail()->update(['low_stock_threshold' => 3]);

        Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $this->freePlan()->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->addDays(3),
        ]);

        $this->artisan('jualanyok:notification-reminders')->assertSuccessful();
        $this->artisan('jualanyok:notification-reminders')->assertSuccessful();

        $notifications = $owner->notifications()->get();
        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains(fn ($item) => ($item->data['type'] ?? null) === 'subscription.expiring'));
        $this->assertTrue($notifications->contains(fn ($item) => ($item->data['type'] ?? null) === 'inventory.low'));
    }

    private function databaseNotification(
        $user,
        string $title,
        string $groupKey,
        string $url = '/notifikasi',
    ) {
        $user->notifyNow(new BusinessNotification([
            'type' => 'test.action',
            'category' => str_starts_with($groupKey, 'inventory') ? 'inventory' : 'orders',
            'title' => $title,
            'message' => 'Pesan pengujian.',
            'url' => $url,
            'action_required' => true,
            'group_key' => $groupKey,
        ]), ['database']);

        return $user->notifications()->latest()->firstOrFail();
    }
}
