<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $stores = Store::with('owner:id,name,email')
            ->withCount(['products', 'orders'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($s) => $s->where('name', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Store $s) => [
                'id' => $s->id,
                'username' => $s->username,
                'name' => $s->name,
                'owner' => $s->owner->name,
                'owner_email' => $s->owner->email,
                'status' => $s->status,
                'suspension_reason' => $s->suspension_reason,
                'is_published' => (bool) $s->is_published,
                'products_count' => $s->products_count,
                'orders_count' => $s->orders_count,
                'view_count' => $s->view_count,
                'public_url' => $s->publicUrl(),
                'created_at' => $s->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/Stores', [
            'stores' => $stores,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function suspend(Request $request, Store $store)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        // Suspending takes the storefront offline immediately but leaves
        // existing orders and buyer access untouched.
        $store->update([
            'status' => 'suspended',
            'suspension_reason' => $data['reason'],
            'is_published' => false,
        ]);

        $this->audit->log('store.suspended', $store, reason: $data['reason']);

        return back()->with('success', 'Toko ditangguhkan.');
    }

    public function reinstate(Request $request, Store $store)
    {
        $store->update(['status' => 'active', 'suspension_reason' => null]);
        $this->audit->log('store.reinstated', $store);

        return back()->with('success', 'Toko diaktifkan kembali. Owner bisa publish ulang.');
    }
}
