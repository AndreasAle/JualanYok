<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Models\Product;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateLinkController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request): Response
    {
        $links = $request->user()->affiliateLinks()
            ->with(['program.store:id,name,username', 'product:id,name,slug'])
            ->latest()
            ->paginate(15)
            ->through(fn (AffiliateLink $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'store' => $l->program->store->name,
                'product' => $l->product?->name,
                'campaign' => $l->campaign,
                'sub_id' => $l->sub_id,
                'clicks' => $l->clicks,
                'conversions' => $l->conversions,
                'conversion_rate' => $l->conversionRate(),
                'revenue' => (float) $l->revenue,
                'is_active' => (bool) $l->is_active,
                'url' => $l->shareUrl(),
            ]);

        return Inertia::render('Affiliate/Links', [
            'links' => $links,
            'products' => Product::whereIn('id', $request->user()->affiliateLinks()->pluck('product_id')->filter())
                ->get(['id', 'name']),
        ]);
    }

    /** Creates a campaign variant of a link the affiliate already has. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'campaign' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'sub_id' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $program = $this->affiliates->programFor($product);

        abort_unless($program && $program->is_active, 404);

        $approved = $program->applications()
            ->where('user_id', $request->user()->id)
            ->where('status', 'APPROVED')
            ->exists();

        abort_unless($approved, 403, 'Kamu belum disetujui untuk program ini.');

        $link = $this->affiliates->linkFor(
            $program,
            $request->user()->id,
            $product,
            $data['campaign'] ?? null,
            $data['sub_id'] ?? null,
        );

        return back()->with('success', 'Link kampanye dibuat: '.$link->shareUrl());
    }

    public function destroy(Request $request, AffiliateLink $link)
    {
        abort_unless($link->user_id === $request->user()->id, 403);

        // Deactivated rather than deleted: existing commissions reference it.
        $link->update(['is_active' => false]);

        return back()->with('success', 'Link dinonaktifkan.');
    }
}
