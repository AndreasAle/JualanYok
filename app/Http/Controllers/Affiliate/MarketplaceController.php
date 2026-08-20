<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliateApplication;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->publiclyListed()
            ->where('affiliate_enabled', true)
            ->whereHas('store', fn ($q) => $q->live())
            ->with(['store:id,name,username', 'category:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('product_category_id', $request->query('category')))
            ->when($request->query('sort') === 'commission', fn ($q) => $q->orderByDesc('price'))
            ->when($request->query('sort') === 'popular', fn ($q) => $q->orderByDesc('sales_count'))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(function (Product $p) use ($request) {
                $program = $this->affiliates->programFor($p);

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'thumbnail_url' => $p->thumbnailUrl(),
                    'price' => (float) $p->price,
                    'type_label' => $p->type->label(),
                    'store' => $p->store->name,
                    'store_username' => $p->store->username,
                    'category' => $p->category?->name,
                    'sales_count' => $p->sales_count,
                    'commission_label' => $program
                        ? ($program->commission_type === 'percentage'
                            ? $program->commission_value.'%'
                            : 'Rp'.number_format((float) $program->commission_value, 0, ',', '.'))
                        : '—',
                    'commission_amount' => $program?->commissionFor((float) $p->price) ?? 0,
                    'cookie_days' => $program?->cookie_days,
                    'joined' => $request->user()->affiliateLinks()
                        ->where('product_id', $p->id)
                        ->exists(),
                    'public_url' => route('storefront.product', [$p->store->username, $p->slug]),
                ];
            });

        return Inertia::render('Affiliate/Marketplace', [
            'products' => $products,
            'filters' => $request->only(['q', 'category', 'sort']),
            'categories' => ProductCategory::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    /** Joins a program and mints the affiliate's tracking link. */
    public function join(Request $request, Product $product)
    {
        abort_unless($product->affiliate_enabled, 404);

        $user = $request->user();

        abort_if($product->store->user_id === $user->id, 422, 'Kamu nggak bisa jadi affiliate produk sendiri.');

        $program = $this->affiliates->programFor($product);

        abort_unless($program && $program->is_active, 404, 'Program affiliate produk ini tidak aktif.');

        $application = AffiliateApplication::firstOrCreate(
            ['affiliate_program_id' => $program->id, 'user_id' => $user->id],
            [
                'status' => $program->auto_approve ? 'APPROVED' : 'PENDING',
                'reviewed_at' => $program->auto_approve ? now() : null,
                'message' => $request->input('message'),
            ],
        );

        if ($application->status !== 'APPROVED') {
            return back()->with('info', 'Aplikasi kamu dikirim. Tunggu persetujuan seller ya.');
        }

        $link = $this->affiliates->linkFor($program, $user->id, $product, $request->input('campaign'));

        if (! $user->is_affiliate) {
            $user->forceFill(['is_affiliate' => true])->save();
            $user->roles()->syncWithoutDetaching([Role::where('slug', Role::AFFILIATE)->value('id')]);
        }

        return back()->with('success', 'Link affiliate kamu siap: '.$link->shareUrl());
    }
}
