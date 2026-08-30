<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketplaceStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\CampaignBanner;
use App\Models\HomepageSection;
use App\Models\MarketplaceSearchTerm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Services\AuditLogger;
use App\Services\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketplaceController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->where('is_marketplace_listed', true)
            ->with(['store:id,username,name,status,is_published', 'marketplaceCategory:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('marketplace_status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim()->toString().'%';
                $query->where(fn ($search) => $search->where('name', 'like', $term)
                    ->orWhereHas('store', fn ($store) => $store->where('name', 'like', $term)->orWhere('username', 'like', $term)));
            })
            ->orderByRaw("CASE marketplace_status WHEN 'PENDING_REVIEW' THEN 0 WHEN 'REJECTED' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->label(),
                'price' => (float) $product->price,
                'thumbnail_url' => $product->thumbnailUrl(),
                'status' => $product->marketplace_status->value,
                'status_label' => $product->marketplace_status->label(),
                'category' => $product->marketplaceCategory?->name,
                'reason' => $product->rejection_reason,
                'featured_at' => $product->featured_at?->toDateTimeString(),
                'featured_until' => $product->featured_until?->toDateTimeString(),
                'quality_score' => $product->marketplace_quality_score,
                'creator' => ['name' => $product->store->name, 'username' => $product->store->username],
                'storefront_url' => route('storefront.product', [$product->store->username, $product->slug]),
            ]);

        return Inertia::render('Admin/Marketplace', [
            'products' => $products,
            'filters' => $request->only(['q', 'status']),
            'stats' => collect(MarketplaceStatus::cases())->mapWithKeys(fn ($status) => [
                $status->value => Product::where('marketplace_status', $status->value)->count(),
            ]),
            'configuration' => [
                'categories' => ProductCategory::where('is_active', true)->count(),
                'sections' => HomepageSection::where('is_active', true)->count(),
                'banners' => CampaignBanner::running()->count(),
                'featured_creators' => Store::where('is_featured', true)->count(),
                'search_terms' => MarketplaceSearchTerm::where('search_count', '>', 0)->count(),
            ],
        ]);
    }

    public function moderate(Request $request, Product $product)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'suspend'])],
            'reason' => [Rule::requiredIf(in_array($request->input('decision'), ['reject', 'suspend'], true)), 'nullable', 'string', 'min:5', 'max:2000'],
        ]);

        if ($data['decision'] === 'approve' && (
            ! $product->is_marketplace_listed
            || $product->status !== ProductStatus::Active
            || $product->visibility !== 'public'
            || ! $product->marketplace_category_id
        )) {
            throw ValidationException::withMessages(['decision' => 'Produk belum aktif, belum publik, atau belum memiliki kategori marketplace.']);
        }

        $next = match ($data['decision']) {
            'approve' => MarketplaceStatus::Approved,
            'reject' => MarketplaceStatus::Rejected,
            default => MarketplaceStatus::Suspended,
        };

        DB::transaction(function () use ($request, $product, $data, $next) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $before = $locked->only(['marketplace_status', 'rejection_reason', 'moderated_at', 'moderated_by']);
            $locked->forceFill([
                'marketplace_status' => $next,
                'rejection_reason' => $next === MarketplaceStatus::Approved ? null : trim((string) $data['reason']),
                'moderated_at' => now(),
                'moderated_by' => $request->user()->id,
                'featured_at' => $next === MarketplaceStatus::Approved ? $locked->featured_at : null,
                'featured_until' => $next === MarketplaceStatus::Approved ? $locked->featured_until : null,
            ])->save();
            $this->audit->log('marketplace.product.'.$data['decision'], $locked, $before, $locked->fresh()->only(array_keys($before)), $data['reason'] ?? null);
        });

        $product->loadMissing('store.owner');
        $approved = $next === MarketplaceStatus::Approved;
        $this->notifications->send($product->store->owner, [
            'type' => $approved ? 'marketplace.approved' : ($next === MarketplaceStatus::Rejected ? 'marketplace.rejected' : 'marketplace.suspended'),
            'category' => 'marketplace',
            'priority' => $approved ? 'normal' : 'high',
            'title' => $approved ? 'Produk tayang di marketplace' : ($next === MarketplaceStatus::Rejected ? 'Produk perlu diperbaiki' : 'Distribusi produk ditangguhkan'),
            'message' => $approved
                ? "{$product->name} sudah dapat ditemukan dari halaman Jelajahi JualanYok."
                : (trim((string) ($data['reason'] ?? '')) ?: 'Buka produk untuk melihat tindakan yang diperlukan.'),
            'url' => route('creator.products.edit', $product),
            'action_label' => $approved ? 'Lihat produk' : 'Perbaiki produk',
            'action_required' => ! $approved,
            'group_key' => 'marketplace:product:'.$product->id,
            'tone' => $approved ? 'success' : 'danger',
            'email_required' => ! $approved,
            'meta' => ['product_id' => $product->id, 'decision' => $data['decision']],
        ]);

        return back()->with('success', 'Keputusan moderasi tersimpan dan tercatat di Audit Log.');
    }

    public function feature(Request $request, Product $product)
    {
        abort_unless($product->marketplace_status === MarketplaceStatus::Approved, 422);
        $data = $request->validate(['until' => ['nullable', 'date', 'after:now']]);
        $changes = ['featured_at' => now(), 'featured_until' => $data['until'] ?? null];
        $this->audit->logChange('marketplace.product.featured', $product, $changes);
        $product->update($changes);

        return back()->with('success', 'Produk masuk kurasi editorial tanpa mengubah data penjualan.');
    }
}
