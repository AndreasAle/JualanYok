<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayoutMethodController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['bank', 'ewallet'])],
            'provider' => ['required', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'is_default' => ['boolean'],
        ], [
            'account_number.regex' => 'Nomor rekening hanya boleh angka.',
        ]);

        DB::transaction(function () use ($request, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->payoutMethods()->update(['is_default' => false]);
            }

            $request->user()->payoutMethods()->create([
                'type' => $data['type'],
                'provider' => $data['provider'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],  // encrypted by the cast
                'account_number_last4' => substr($data['account_number'], -4),
                'is_default' => $data['is_default'] ?? ! $request->user()->payoutMethods()->exists(),
                // Verification is a manual finance step; funds cannot be sent
                // to an unverified account.
                'status' => 'unverified',
            ]);
        });

        return back()->with('success', 'Rekening ditambahkan. Menunggu verifikasi tim kami.');
    }

    public function destroy(Request $request, PayoutMethod $payoutMethod)
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 403);

        $hasOpenWithdrawal = $request->user()->withdrawals()
            ->where('payout_method_id', $payoutMethod->id)
            ->whereIn('status', ['REQUESTED', 'UNDER_REVIEW', 'APPROVED', 'PROCESSING'])
            ->exists();

        if ($hasOpenWithdrawal) {
            return back()->with('error', 'Rekening ini masih dipakai penarikan yang sedang diproses.');
        }

        $payoutMethod->delete();

        return back()->with('success', 'Rekening dihapus.');
    }
}
