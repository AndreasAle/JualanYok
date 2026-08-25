<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DigitalAccess;
use App\Models\Order;
use App\Services\DigitalDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;

/**
 * The buyer's delivery page, reachable with no account.
 *
 * Most people who buy a digital product never sign up — they type an email and
 * pay. A receipt that links somewhere login-walled means the sale completes and
 * the product never arrives, so this page is the primary delivery route rather
 * than a fallback.
 *
 * The token in the URL identifies exactly one order. It grants no session and
 * reaches nothing else, and every download is re-checked against the quota and
 * revocation rules at the moment it is requested.
 */
class OrderAccessController extends Controller
{
    public function __construct(private readonly DigitalDeliveryService $delivery) {}

    public function show(Request $request, string $token): HttpResponse
    {
        $order = $this->resolve($token);

        $order->load(['items.product', 'store', 'digitalAccesses.file']);


        return Inertia::render('Order/Access', [
            'order' => [
                'number' => $order->number,
                'token' => $order->access_token,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'is_paid' => $order->status->isSettled(),
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'grand_total' => (float) $order->grand_total,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'variant_name' => $item->variant_name,
                    'quantity' => $item->quantity,
                    'total' => (float) $item->total,
                    'thumbnail_url' => $item->product?->thumbnailUrl(),
                    'type_label' => $item->product?->type->label(),
                    'post_purchase_message' => $item->product?->post_purchase_message,
                ]),
            ],
            'store' => [
                'name' => $order->store->name,
                'username' => $order->store->username,
                'avatar_url' => $order->store->avatarUrl(),
                'url' => route('storefront.show', $order->store->username),
                'whatsapp' => $order->store->whatsapp,
            ],
            'downloads' => $order->digitalAccesses
                ->filter(fn (DigitalAccess $access) => $access->file !== null)
                ->values()
                ->map(fn (DigitalAccess $access) => [
                    'id' => $access->id,
                    'name' => $access->file->name,
                    'version' => $access->file->version,
                    'size' => (int) $access->file->size,
                    'is_external' => $access->file->external_url !== null,
                    'remaining' => $access->remainingDownloads(),
                    'limit' => $access->download_limit,
                    'used' => (int) $access->download_count,
                    'expires_at' => $access->expires_at?->toDateString(),
                    'available' => $access->isDownloadable(),
                    'blocked_reason' => $this->blockedReason($access),
                    'url' => route('order.access.download', [$order->access_token, $access->id]),
                ]),
            /*
             * Courses and memberships track progress against a person, so they
             * genuinely need an account. Saying so plainly beats linking a guest
             * to a login wall and letting them think the purchase failed.
             */
            'needsAccount' => $order->items
                ->pluck('product_type')
                ->intersect(['COURSE', 'MEMBERSHIP'])
                ->isNotEmpty(),
            // Claiming turns this one-off purchase into a proper library, but is
            // never required to collect what was already paid for.
            'canClaim' => Auth::check() && $order->user_id === null,
            'isClaimed' => $order->user_id !== null,
        ])
            /*
             * The URL is the credential, so it must never be indexed. Sent as a
             * header rather than a meta tag: a tag rendered client-side is
             * invisible to a crawler that does not execute JavaScript.
             */
            ->toResponse($request)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function download(Request $request, string $token, DigitalAccess $access)
    {
        $order = $this->resolve($token);

        abort_unless($access->order_id === $order->id, 403);
        abort_unless($order->status->isSettled(), 403, 'Pesanan ini belum lunas.');

        // Externally hosted files are a redirect, not a stream.
        if ($access->file?->external_url) {
            abort_unless($access->isDownloadable(), 403, 'Akses download sudah tidak berlaku.');
            $access->increment('download_count');

            return redirect()->away($access->file->external_url);
        }

        return $this->delivery->download($access, $request);
    }

    /** Attaches an existing guest purchase to the signed-in account. */
    public function claim(Request $request, string $token)
    {
        $order = $this->resolve($token);
        $user = $request->user();

        abort_unless($user, 403);

        if ($order->user_id !== null) {
            return back()->with('info', 'Pesanan ini sudah tersimpan di akun.');
        }

        $customer = Customer::firstOrCreate(
            ['store_id' => $order->store_id, 'email' => $order->customer_email],
            ['name' => $order->customer_name, 'user_id' => $user->id],
        );

        if ($customer->user_id === null) {
            $customer->forceFill(['user_id' => $user->id])->save();
        }

        $order->forceFill([
            'user_id' => $user->id,
            'customer_id' => $order->customer_id ?? $customer->id,
        ])->save();

        return back()->with('success', 'Pembelian tersimpan di akunmu.');
    }

    /**
     * A wrong or retired token must look exactly like a token that never
     * existed, so nobody can probe for valid ones.
     */
    private function resolve(string $token): Order
    {
        return Order::where('access_token', $token)->firstOrFail();
    }

    private function blockedReason(DigitalAccess $access): ?string
    {
        if ($access->is_revoked) {
            return 'Akses dicabut penjual.';
        }

        if ($access->expires_at && $access->expires_at->isPast()) {
            return 'Masa akses sudah lewat.';
        }

        if ($access->download_limit !== null && $access->download_count >= $access->download_limit) {
            return 'Kuota unduh habis.';
        }

        return null;
    }
}
