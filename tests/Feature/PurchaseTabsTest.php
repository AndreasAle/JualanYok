<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The buyer's order tabs.
 *
 * The tabs are the four questions a buyer has — do I still owe money, is it
 * being prepared, is it on the way, is it done. What makes that worth pinning
 * down is that "on the way" is not an order status at all: nothing ever sets
 * one, so the tab has to ask the shipment instead.
 */
class PurchaseTabsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();

        $this->store = $this->makeStore();
        $this->buyer = $this->makeUser();
    }

    private function order(OrderStatus $status): Order
    {
        return Order::create([
            'number' => 'JY-'.uniqid(),
            'store_id' => $this->store->id,
            'customer_email' => $this->buyer->email,
            'customer_name' => $this->buyer->name,
            'status' => $status,
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);
    }

    private function tabCounts(string $tab = 'semua'): array
    {
        $props = $this->actingAs($this->buyer)
            ->get('/member/pembelian'.($tab === 'semua' ? '' : '?tab='.$tab))
            ->assertOk()
            ->viewData('page')['props'];

        return [
            'rows' => count($props['orders']['data']),
            'counts' => collect($props['tabs'])->pluck('count', 'key')->all(),
        ];
    }

    public function test_each_tab_shows_only_its_own_stage(): void
    {
        $this->order(OrderStatus::PendingPayment);
        $this->order(OrderStatus::Paid);
        $this->order(OrderStatus::Processing);
        $this->order(OrderStatus::Completed);
        $this->order(OrderStatus::Cancelled);

        $this->assertSame(1, $this->tabCounts('belum-bayar')['rows']);
        $this->assertSame(2, $this->tabCounts('dikemas')['rows'], 'Dibayar dan diproses sama-sama "dikemas".');
        $this->assertSame(1, $this->tabCounts('selesai')['rows']);
        $this->assertSame(1, $this->tabCounts('dibatalkan')['rows']);
        $this->assertSame(5, $this->tabCounts('semua')['rows']);
    }

    public function test_the_counts_are_of_everything_not_just_this_page(): void
    {
        foreach (range(1, 12) as $ignored) {
            $this->order(OrderStatus::PendingPayment);
        }

        $result = $this->tabCounts('belum-bayar');

        // A page holds ten; the badge has to say twelve.
        $this->assertSame(10, $result['rows']);
        $this->assertSame(12, $result['counts']['belum-bayar']);
    }

    public function test_an_unknown_tab_falls_back_to_everything(): void
    {
        $this->order(OrderStatus::Completed);

        $this->actingAs($this->buyer)
            ->get('/member/pembelian?tab=dibuat-sendiri')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tab', 'semua')->has('orders.data', 1));
    }

    public function test_another_buyers_orders_are_never_counted_or_listed(): void
    {
        $this->order(OrderStatus::Paid);

        $stranger = $this->makeUser();

        $props = $this->actingAs($stranger)
            ->get('/member/pembelian')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame([], $props['orders']['data']);
        $this->assertSame(0, collect($props['tabs'])->firstWhere('key', 'semua')['count']);
    }
}
