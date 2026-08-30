<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OrderTrackingController extends Controller
{
    public function __construct(private readonly OrderTrackingService $tracking) {}

    public function index(Request $request): HttpResponse
    {
        return Inertia::render('Marketing/TrackOrder', ['tracking' => null])
            ->toResponse($request);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tracking_code' => ['required', 'string', 'max:40'],
        ]);
        $code = strtoupper(trim($data['tracking_code']));

        $exists = Order::where('payment_status', PaymentStatus::Paid->value)->where('tracking_code', $code)->exists();
        if (! $exists) {
            return back()->withErrors([
                'tracking_code' => 'ID pembelian tidak ditemukan. Periksa kembali karakter yang kamu masukkan.',
            ])->withInput();
        }

        return redirect()->route('tracking.show', $code);
    }

    public function show(Request $request, string $trackingCode): HttpResponse
    {
        $order = Order::where('payment_status', PaymentStatus::Paid->value)
            ->where('tracking_code', strtoupper($trackingCode))
            ->firstOrFail();

        return Inertia::render('Marketing/TrackOrder', [
            'tracking' => $this->tracking->payload($order),
        ])->toResponse($request)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
