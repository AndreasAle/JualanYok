<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingWebhookController extends Controller
{
    public function __invoke(Request $request, ShippingService $shipping): JsonResponse
    {
        return response()->json($shipping->handleWebhook($request));
    }
}
