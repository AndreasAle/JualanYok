<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function edit(Request $request): Response
    {
        $store = $request->user()->store;
        $profile = $store->shippingProfile;

        return Inertia::render('Creator/Shipping/Edit', [
            'profile' => $profile,
            'provider' => [
                'name' => config('shipping.default'),
                'ready' => config('shipping.default') === 'manual' || ((bool) config('shipping.providers.biteship.enabled') && filled(config('shipping.providers.biteship.token'))),
                'couriers' => config('shipping.providers.biteship.couriers', []),
            ],
            'webhookUrl' => route('webhooks.shipping.biteship'),
            'webhookHeader' => config('shipping.providers.biteship.webhook_header', 'X-Callback-Token'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'address_line' => ['required', 'string', 'max:500'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:12'],
            'area_id' => [config('shipping.default') === 'biteship' ? 'required' : 'nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'collection_method' => ['required', 'in:pickup,drop_off'],
            'enabled_couriers' => [config('shipping.default') === 'biteship' ? 'required' : 'nullable', 'array', 'min:1'],
            'enabled_couriers.*' => ['string', 'max:32'],
            'default_insurance' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $request->user()->store->shippingProfile()->updateOrCreate([], $data);

        return back()->with('success', 'Alamat asal dan preferensi kurir sudah disimpan.');
    }

    public function areas(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:120']]);

        return response()->json(['areas' => $this->shipping->searchAreas($data['q'])]);
    }
}
