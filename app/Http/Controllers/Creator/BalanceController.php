<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $wallet = $request->user()->walletOrCreate();

        $entries = LedgerEntry::where('wallet_id', $wallet->id)
            ->when($request->filled('bucket'), fn ($q) => $q->where('bucket', $request->query('bucket')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LedgerEntry $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'bucket' => $e->bucket->value,
                'bucket_label' => $e->bucket->label(),
                'amount' => (float) $e->amount,
                'balance_after' => (float) $e->balance_after,
                'description' => $e->description,
                'created_at' => $e->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Creator/Balance', [
            'wallet' => [
                'pending' => (float) $wallet->pending_balance,
                'available' => (float) $wallet->available_balance,
                'held' => (float) $wallet->held_balance,
                'withdrawn' => (float) $wallet->withdrawn_balance,
                'lifetime_earned' => (float) $wallet->lifetime_earned,
                'is_frozen' => (bool) $wallet->is_frozen,
                'currency' => $wallet->currency,
            ],
            'entries' => $entries,
            'filters' => $request->only(['bucket', 'type']),
            'holdingDays' => (int) config('jualanyok.holding_period_days'),
        ]);
    }
}
