<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        abort_unless(config("payments.providers.{$provider}.enabled"), 404);

        $result = $this->payments->handleWebhook($provider, $request);

        // An invalid signature returns 401 so the gateway retries or alerts;
        // a duplicate returns 200 because replaying is not an error.
        $status = match ($result['status']) {
            'invalid' => 401,
            default => 200,
        };

        return response()->json($result, $status);
    }
}
