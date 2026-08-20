<?php

namespace App\Services;

use App\Enums\BalanceBucket;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single writer for wallet balances.
 *
 * Nothing else in the application is allowed to touch the wallet balance
 * columns. Every mutation appends an immutable ledger entry and updates the
 * cached bucket in the same transaction, with the wallet row locked, so the
 * cached totals can never drift from the ledger.
 */
class LedgerService
{
    /**
     * Records one movement against a wallet bucket.
     *
     * @param  float  $amount  Signed: positive credits the bucket, negative debits it.
     * @param  string|null  $idempotencyKey  When supplied, a repeat call is a no-op
     *                                       and returns the entry already written.
     */
    public function record(
        Wallet $wallet,
        LedgerEntryType $type,
        BalanceBucket $bucket,
        float $amount,
        ?Model $reference = null,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $meta = [],
    ): LedgerEntry {
        $amount = Money::round($amount);

        return DB::transaction(function () use ($wallet, $type, $bucket, $amount, $reference, $description, $idempotencyKey, $meta) {
            if ($idempotencyKey !== null) {
                $existing = LedgerEntry::where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var Wallet $locked */
            $locked = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $column = $bucket->column();
            $balanceAfter = Money::round((float) $locked->{$column} + $amount);

            // Buckets model real money; none of them may go negative.
            if ($balanceAfter < -0.001) {
                throw new RuntimeException(sprintf(
                    'Saldo %s tidak mencukupi (tersedia %s, diminta %s).',
                    $bucket->label(),
                    Money::format((float) $locked->{$column}),
                    Money::format(abs($amount)),
                ));
            }

            $entry = new LedgerEntry([
                'wallet_id' => $locked->id,
                'type' => $type->value,
                'bucket' => $bucket->value,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'currency' => $locked->currency,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);

            if ($reference) {
                $entry->reference_type = $reference->getMorphClass();
                $entry->reference_id = $reference->getKey();
            }

            $entry->save();

            $locked->{$column} = $balanceAfter;

            if ($amount > 0 && in_array($type, [
                LedgerEntryType::SellerRevenue,
                LedgerEntryType::AffiliateCommission,
            ], true)) {
                $locked->lifetime_earned = Money::round((float) $locked->lifetime_earned + $amount);
            }

            $locked->save();

            $wallet->refresh();

            return $entry;
        });
    }

    /** Moves money between two buckets of the same wallet atomically. */
    public function move(
        Wallet $wallet,
        BalanceBucket $from,
        BalanceBucket $to,
        float $amount,
        LedgerEntryType $type,
        ?Model $reference = null,
        ?string $description = null,
        ?string $idempotencyKey = null,
    ): void {
        DB::transaction(function () use ($wallet, $from, $to, $amount, $type, $reference, $description, $idempotencyKey) {
            $this->record(
                $wallet, $type, $from, -abs($amount), $reference, $description,
                $idempotencyKey ? $idempotencyKey.':out' : null,
            );

            $this->record(
                $wallet, $type, $to, abs($amount), $reference, $description,
                $idempotencyKey ? $idempotencyKey.':in' : null,
            );
        });
    }

    public function walletFor(User $user, string $currency = 'IDR'): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id, 'currency' => $currency]);
    }

    /**
     * Verifies the cached balances still match the ledger. Returns the list of
     * mismatching buckets — empty means the wallet reconciles.
     */
    public function reconcile(Wallet $wallet): array
    {
        $mismatches = [];

        foreach (BalanceBucket::cases() as $bucket) {
            $ledger = Money::round((float) $wallet->entries()->where('bucket', $bucket->value)->sum('amount'));
            $cached = Money::round((float) $wallet->{$bucket->column()});

            if (! Money::equals($ledger, $cached)) {
                $mismatches[$bucket->value] = ['ledger' => $ledger, 'cached' => $cached];
            }
        }

        return $mismatches;
    }
}
