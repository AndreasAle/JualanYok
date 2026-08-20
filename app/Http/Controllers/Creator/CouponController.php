<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        return Inertia::render('Creator/Coupons/Index', [
            'coupons' => $store->coupons()
                ->latest()
                ->paginate(15)
                ->through(fn (Coupon $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type,
                    'value' => (float) $c->value,
                    'min_order_amount' => (float) $c->min_order_amount,
                    'usage_limit' => $c->usage_limit,
                    'used_count' => $c->used_count,
                    'starts_at' => $c->starts_at?->toDateString(),
                    'ends_at' => $c->ends_at?->toDateString(),
                    'is_active' => (bool) $c->is_active,
                    'is_live' => $c->is_active && $c->isWithinWindow() && $c->hasQuotaLeft(),
                ]),
            'products' => $store->products()->active()->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Creator/Coupons/Form', [
            'coupon' => null,
            'products' => $request->user()->store->products()->active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $request->user()->store->coupons()->create($data);

        return redirect()->route('creator.coupons.index')->with('success', 'Kupon dibuat.');
    }

    public function edit(Request $request, Coupon $coupon): Response
    {
        $this->authorizeCoupon($request, $coupon);

        return Inertia::render('Creator/Coupons/Form', [
            'coupon' => $coupon->only([
                'id', 'code', 'type', 'value', 'max_discount', 'min_order_amount',
                'usage_limit', 'usage_limit_per_customer', 'product_ids', 'is_active',
            ]) + [
                'starts_at' => $coupon->starts_at?->toDateString(),
                'ends_at' => $coupon->ends_at?->toDateString(),
            ],
            'products' => $request->user()->store->products()->active()->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeCoupon($request, $coupon);

        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('creator.coupons.index')->with('success', 'Kupon diperbarui.');
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->authorizeCoupon($request, $coupon);

        $coupon->delete();

        return back()->with('success', 'Kupon dihapus.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $storeId = $request->user()->store->id;

        return $request->validate([
            'code' => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')
                    ->where('store_id', $storeId)
                    ->ignore($coupon?->id),
            ],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('store_id', $storeId)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ], [
            'code.regex' => 'Kode kupon hanya huruf kapital, angka, - dan _.',
        ]);
    }

    private function authorizeCoupon(Request $request, Coupon $coupon): void
    {
        abort_unless($coupon->store_id === $request->user()->store->id, 403);
    }
}
