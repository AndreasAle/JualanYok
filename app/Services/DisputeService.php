<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function open(Order $order, string $type, string $description, ?User $user = null, array $evidence = []): OrderDispute
    {
        if (! $order->canOpenDispute()) {
            throw ValidationException::withMessages(['dispute' => 'Masa komplain tidak tersedia atau sudah berakhir.']);
        }

        return DB::transaction(function () use ($order, $type, $description, $user, $evidence) {
            $dispute = $order->disputes()->create([
                'customer_id' => $order->customer_id,
                'opened_by' => $user?->id,
                'type' => $type,
                'status' => DisputeStatus::Open,
                'description' => $description,
                'evidence' => $evidence,
                'seller_response_due_at' => now()->addDays(2),
            ]);

            $order->update(['status' => OrderStatus::Disputed, 'funds_release_at' => null]);

            return $dispute;
        });
    }

    public function sellerRespond(OrderDispute $dispute, User $seller, string $response): OrderDispute
    {
        abort_unless($dispute->order->store->user_id === $seller->id, 403);

        if ($dispute->status->isClosed()) {
            throw ValidationException::withMessages(['dispute' => 'Komplain ini sudah ditutup.']);
        }

        $dispute->update([
            'status' => DisputeStatus::SellerResponded,
            'seller_response' => $response,
        ]);

        return $dispute->fresh();
    }

    public function resolve(OrderDispute $dispute, User $admin, string $winner, string $note): OrderDispute
    {
        return DB::transaction(function () use ($dispute, $admin, $winner, $note) {
            $refundId = null;

            if ($winner === 'buyer') {
                $refund = app(RefundService::class)->request(
                    $dispute->order,
                    $dispute->order->refundableAmount(),
                    'Penyelesaian komplain '.$dispute->number.': '.$note,
                    $admin,
                );
                $refundId = $refund->id;
            } else {
                $dispute->order->update([
                    'status' => OrderStatus::Completed,
                    'completed_at' => now(),
                    'funds_release_at' => now(),
                ]);
            }

            $dispute->update([
                'status' => DisputeStatus::Resolved,
                'resolution' => $winner,
                'admin_note' => $note,
                'resolved_by' => $admin->id,
                'refund_id' => $refundId,
                'resolved_at' => now(),
            ]);

            return $dispute->fresh();
        });
    }
}
