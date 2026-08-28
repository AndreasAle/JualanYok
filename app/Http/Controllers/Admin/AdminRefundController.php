<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly AuditLogger $audit,
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

        return back()->with('success', 'Dana refund dikonfirmasi terkirim. Saldo, komisi, dan jurnal sudah disesuaikan.');
    }

    public function reject(Request $request, Refund $refund)
    {
        $this->ensureFinance($request);

        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);

        $this->refunds->reject($refund, $request->user(), $data['note']);
        $this->audit->log('refund.rejected', $refund, reason: $data['note']);

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
}
