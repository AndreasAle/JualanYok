<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(private readonly NotificationCenterService $notifications) {}

    public function open(Order $order, string $type, string $description, ?User $user = null, array $evidence = []): OrderDispute
    {
        if (! $order->canOpenDispute()) {
            throw ValidationException::withMessages(['dispute' => 'Masa komplain tidak tersedia atau sudah berakhir.']);
        }

        $dispute = DB::transaction(function () use ($order, $type, $description, $user, $evidence) {
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

        $order->loadMissing('store.owner');
        $payload = [
            'type' => 'dispute.opened',
            'category' => 'refunds',
            'priority' => 'high',
            'title' => 'Komplain baru membutuhkan respons',
            'message' => "{$dispute->number} untuk pesanan {$order->number} perlu direspons maksimal 2 hari.",
            'url' => route('creator.orders.show', $order->number),
            'action_label' => 'Respons komplain',
            'action_required' => true,
            'group_key' => 'dispute:'.$dispute->id,
            'tone' => 'danger',
            'meta' => ['dispute_id' => $dispute->id, 'order_id' => $order->id],
        ];
        $this->notifications->send($order->store->owner, $payload);
        $this->notifications->sendToAdmins([Role::SUPPORT_ADMIN, Role::SUPER_ADMIN], array_replace($payload, [
            'url' => route('admin.disputes.index', ['status' => 'OPEN']),
            'action_label' => 'Pantau komplain',
            'group_key' => 'support:disputes:open',
        ]));

        return $dispute;
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

        $dispute = $dispute->fresh(['order']);
        $this->notifications->sendToMail($dispute->order->customer_email, [
            'type' => 'dispute.seller_responded',
            'category' => 'refunds',
            'priority' => 'high',
            'title' => 'Penjual merespons komplain',
            'message' => "Penjual sudah memberikan respons untuk {$dispute->number}.",
            'url' => route('checkout.status', $dispute->order->number),
            'action_label' => 'Lihat respons',
            'group_key' => 'dispute:'.$dispute->id,
            'tone' => 'info',
        ]);

        return $dispute;
    }

    public function resolve(OrderDispute $dispute, User $admin, string $winner, string $note): OrderDispute
    {
        $dispute = DB::transaction(function () use ($dispute, $admin, $winner, $note) {
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

        $dispute->loadMissing('order.store.owner');
        $sellerWon = $winner === 'seller';
        $payload = [
            'type' => 'dispute.resolved',
            'category' => 'refunds',
            'priority' => 'high',
            'title' => 'Komplain sudah diputuskan',
            'message' => "Keputusan {$dispute->number}: ".($sellerWon ? 'dana dilepas kepada penjual.' : 'refund diproses untuk pembeli.'),
            'url' => route('creator.orders.show', $dispute->order->number),
            'action_label' => 'Lihat keputusan',
            'group_key' => 'dispute:'.$dispute->id,
            'tone' => $sellerWon ? 'success' : 'warning',
            'meta' => ['dispute_id' => $dispute->id, 'winner' => $winner],
        ];
        $this->notifications->send($dispute->order->store->owner, $payload);
        $this->notifications->sendToMail($dispute->order->customer_email, array_replace($payload, [
            'url' => route('checkout.status', $dispute->order->number),
        ]));

        return $dispute;
    }
}
