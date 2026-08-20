<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\DigitalAccess;
use App\Services\DigitalDeliveryService;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    public function __construct(private readonly DigitalDeliveryService $delivery) {}

    /**
     * Serves a purchased file. The route is signed and short-lived; the access
     * record is re-validated here so a leaked link still cannot outlive the
     * buyer's entitlement.
     */
    public function serve(Request $request, string $token)
    {
        $access = DigitalAccess::where('token', $token)->firstOrFail();

        return $this->delivery->download($access, $request);
    }
}
