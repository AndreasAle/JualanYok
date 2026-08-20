<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        // Read-only by design: the ledger model itself blocks updates and
        // deletes, so admins can inspect history but never rewrite it.
        $entries = LedgerEntry::with('wallet.user:id,name,username')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('bucket'), fn ($q) => $q->where('bucket', $request->query('bucket')))
            ->when($request->filled('user_id'), fn ($q) => $q->whereHas('wallet', fn ($w) => $w->where('user_id', $request->query('user_id'))))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (LedgerEntry $e) => [
                'id' => $e->id,
                'user' => $e->wallet?->user?->name,
                'username' => $e->wallet?->user?->username,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'bucket' => $e->bucket->value,
                'amount' => (float) $e->amount,
                'balance_after' => (float) $e->balance_after,
                'description' => $e->description,
                'reference' => $e->reference_type ? class_basename($e->reference_type).'#'.$e->reference_id : null,
                'created_at' => $e->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Ledger', [
            'entries' => $entries,
            'filters' => $request->only(['type', 'bucket', 'user_id']),
            'types' => collect(LedgerEntryType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'totals' => [
                'pending' => Money::round((float) Wallet::sum('pending_balance')),
                'available' => Money::round((float) Wallet::sum('available_balance')),
                'held' => Money::round((float) Wallet::sum('held_balance')),
                'withdrawn' => Money::round((float) Wallet::sum('withdrawn_balance')),
            ],
        ]);
    }
}
