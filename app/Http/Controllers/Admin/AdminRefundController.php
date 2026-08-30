<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\NotificationCenterService;
use App\Services\RefundService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $refunds = Refund::with(['order.store:id,name', 'requester:id,name', 'payment:id,provider'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Refund $r) => [
                'id' => $r->id,
                'order_number' => $r->order->number,
                'store' => $r->order->store->name,
                'amount' => (float) $r->amount,
                'order_total' => (float) $r->order->grand_total,
                'status' => $r->status,
                'execution_mode' => $r->execution_mode,
                'payment_provider' => $r->payment?->provider,
                'transfer_reference' => $r->transfer_reference,
                'reason' => $r->reason,
                'admin_note' => $r->admin_note,
                'requested_by' => $r->requester?->name,
                'created_at' => $r->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Refunds', [
            'refunds' => $refunds,
            'filters' => $request->only('status'),
            'canProcess' => $request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN),
        ]);
    }

    public function approve(Request $request, Refund $refund)
    {
        $this->ensureFinance($request);

        $note = $request->input('note');

        $refund = $this->refunds->approve($refund, $request->user(), $note);
        $this->audit->log('refund.approved', $refund, reason: $note);
        $this->notifyOutcome($refund, $refund->status === 'COMPLETED' ? 'completed' : 'approved', $note);

        $message = $refund->status === 'COMPLETED'
            ? 'Refund berhasil dikirim dan pembukuan sudah diselesaikan.'
            : 'Pengajuan diterima. Kirim dana melalui dashboard provider, lalu konfirmasi nomor referensinya.';

        return back()->with('success', $message);
    }

    public function complete(Request $request, Refund $refund)
    {
        $this->ensureFinance($request);

        $data = $request->validate([
            'transfer_reference' => ['required', 'string', 'min:4', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund = $this->refunds->completeManual(
            $refund,
            $request->user(),
            $data['transfer_reference'],
            $data['note'] ?? null,
        );
        $this->audit->log('refund.completed', $refund, reason: $data['note'] ?? null, after: [
            'transfer_reference' => $data['transfer_reference'],
        ]);
        $this->notifyOutcome($refund, 'completed', $data['note'] ?? null);

        return back()->with('success', 'Dana refund dikonfirmasi terkirim. Saldo, komisi, dan jurnal sudah disesuaikan.');
    }

    public function reject(Request $request, Refund $refund)
    {
        $this->ensureFinance($request);

        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);

        $refund = $this->refunds->reject($refund, $request->user(), $data['note']);
        $this->audit->log('refund.rejected', $refund, reason: $data['note']);
        $this->notifyOutcome($refund, 'rejected', $data['note']);

        return back()->with('success', 'Refund ditolak.');
    }

    private function ensureFinance(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN),
            403,
            'Hanya finance admin yang bisa memproses refund.',
        );
    }

    private function notifyOutcome(Refund $refund, string $outcome, ?string $note): void
    {
        $refund->loadMissing('order.store.owner');
        $completed = $outcome === 'completed';
        $rejected = $outcome === 'rejected';
        $title = $completed ? 'Refund sudah dikirim' : ($rejected ? 'Pengajuan refund ditolak' : 'Refund disetujui');
        $message = $completed
            ? 'Refund '.Money::format((float) $refund->amount)." untuk pesanan {$refund->order->number} sudah diselesaikan."
            : ($rejected ? ($note ?: 'Pengajuan tidak memenuhi ketentuan refund.') : 'Tim finance sedang menyelesaikan pengiriman dana refund.');

        $payload = [
            'type' => 'refund.'.$outcome,
            'category' => 'refunds',
            'priority' => 'high',
            'title' => $title,
            'message' => $message,
            'url' => route('creator.orders.show', $refund->order->number),
            'action_label' => 'Lihat pesanan',
            'action_required' => $rejected,
            'group_key' => 'refund:'.$refund->id,
            'tone' => $rejected ? 'danger' : 'success',
            'meta' => ['refund_id' => $refund->id, 'order_id' => $refund->order_id],
        ];

        $this->notifications->send($refund->order->store->owner, $payload);
        $this->notifications->sendToMail($refund->order->customer_email, array_replace($payload, [
            'url' => route('checkout.status', $refund->order->number),
        ]));
    }
}
