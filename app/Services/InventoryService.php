<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** Set a physical stock balance and keep an auditable movement. */
    public function setQuantity(
        Inventory $inventory,
        int $quantity,
        string $reason,
        ?User $actor = null,
        ?string $note = null,
        ?int $lowStockThreshold = null,
    ): Inventory {
        return DB::transaction(function () use ($inventory, $quantity, $reason, $actor, $note, $lowStockThreshold) {
            /** @var Inventory $locked */
            $locked = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if ($quantity < $locked->reserved) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok tidak boleh kurang dari {$locked->reserved} unit yang sedang dikunci pembeli.",
                ]);
            }

            $change = $quantity - $locked->quantity;
            $locked->quantity = $quantity;

            if ($lowStockThreshold !== null) {
                $locked->low_stock_threshold = $lowStockThreshold;
            }

            $locked->save();

            if ($change !== 0) {
                $locked->movements()->create([
                    'change' => $change,
                    'balance_after' => $locked->availableQuantity(),
                    'reason' => $reason,
                    'user_id' => $actor?->id,
                    'note' => filled($note) ? $note : null,
                ]);
            }

            if ($locked->product_variant_id) {
                ProductVariant::whereKey($locked->product_variant_id)->update(['stock' => $quantity]);
            }

            return $locked->fresh(['variant']);
        });
    }
}
