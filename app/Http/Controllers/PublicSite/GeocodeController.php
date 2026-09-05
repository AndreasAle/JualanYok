<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turning an address into a point on a map, and back.
 *
 * Biteship's Maps API only names areas — it returns no coordinates and serves
 * no tiles — so the pin needs a geocoder of its own. It is proxied rather than
 * called from the browser for two reasons: the buyer's IP and the address they
 * are typing never reach a third party directly, and the results are cached
 * once for everyone instead of once per shopper.
 *
 * The provider is configurable. Nominatim is the default because it needs no
 * key and no billing account, and its terms are met here: an identifying
 * User-Agent, cached results, and a throttled route.
 */
class GeocodeController extends Controller
{
    private const CACHE_HOURS = 168;

    public function search(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:200']]);

        $results = $this->remember('cari:'.mb_strtolower($data['q']), fn () => $this->call('search', [
            'q' => $data['q'],
            'countrycodes' => 'id',
            'format' => 'jsonv2',
            'limit' => 5,
            'addressdetails' => 1,
        ]));

        return response()->json([
            'results' => collect($results)
                ->map(fn (array $row) => [
                    'label' => $row['display_name'] ?? '',
                    'latitude' => (float) ($row['lat'] ?? 0),
                    'longitude' => (float) ($row['lon'] ?? 0),
                ])
                ->filter(fn (array $row) => $row['latitude'] !== 0.0)
                ->values(),
        ]);
    }

    /** The address under a dropped pin, so the buyer can sanity-check it. */
    public function reverse(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->isLive(), 404);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Rounded before it becomes a cache key: a pin nudged by two metres is
        // the same place, and caching every pixel would cache nothing.
        $lat = round((float) $data['lat'], 5);
        $lon = round((float) $data['lon'], 5);

        $row = $this->remember("balik:{$lat},{$lon}", fn () => $this->call('reverse', [
            'lat' => $lat,
            'lon' => $lon,
            'format' => 'jsonv2',
            'addressdetails' => 1,
        ]));

        return response()->json(['label' => $row['display_name'] ?? null]);
    }

    private function remember(string $key, callable $resolve): array
    {
        try {
            return Cache::remember(
                'geocode:'.md5($key),
                now()->addHours(self::CACHE_HOURS),
                fn () => (array) $resolve(),
            );
        } catch (Throwable $exception) {
            // A map that cannot find a place is a smaller problem than a
            // checkout that will not load, so this never throws upward.
            Log::warning('Geocoder request failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function call(string $path, array $query): array
    {
        $base = rtrim((string) config('shipping.geocoder.base_url', 'https://nominatim.openstreetmap.org'), '/');

        $response = Http::withHeaders([
            // Nominatim's policy requires an identifying agent that can be
            // contacted; an anonymous scraper gets blocked, and rightly.
            'User-Agent' => config('jualanyok.name', 'JualanYok').' ('.config('mail.from.address').')',
            'Accept-Language' => 'id',
        ])->timeout(8)->get("{$base}/{$path}", $query)->throw()->json();

        return is_array($response) ? $response : [];
    }
}
