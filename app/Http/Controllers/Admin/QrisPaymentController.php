<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\PaymentResult;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an admin matches an incoming QRIS transfer to a customer order.
 *
 * Confirming here goes through the very same PaymentService::markPaid a gateway
 * callback would use, so stock, the ledger, affiliate commission, fulfilment and
 * receipts all behave identically — there is no second, weaker "manual" path.
 */
class QrisPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        // Lapsed windows should not sit in the queue looking actionable.
        $this->payments->expireStale();

        $status = $request->query('status', PaymentStatus::Pending->value);

        $rows = Payment::with(['order.store', 'order.items'])
            ->where('provider', 'qris')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = trim($request->query('q'));

                // An admin reads the figure off a wallet notification and types
                // it as they see it — "100775" or "100.775". Amounts are whole
                // rupiah, so match them numerically rather than as text, which
                // would miss the stored decimal.
                $digits = preg_replace('/\D/', '', $needle);

                $q->where(function ($inner) use ($needle, $digits) {
                    $inner->where('reference', 'like', "%{$needle}%")
                        ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$needle}%")
                            ->orWhere('customer_email', 'like', "%{$needle}%")
                            ->orWhere('customer_name', 'like', "%{$needle}%"));

                    if ($digits !== '') {
                        $inner->orWhere('amount', (float) $digits);
                    }
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'order_number' => $p->order?->number,
                'store_name' => $p->order?->store?->name,
                'customer_name' => $p->order?->customer_name,
                'customer_email' => $p->order?->customer_email,
                'base_amount' => (int) (($p->instructions['base_amount'] ?? $p->amount)),
                'unique_suffix' => $p->unique_suffix !== null ? (int) $p->unique_suffix : null,
                'amount' => (int) $p->amount,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'item_count' => $p->order?->items?->sum('quantity') ?? 0,
                'expires_at' => $p->expires_at?->toDateTimeString(),
                'created_at' => $p->created_at->toDateTimeString(),
                'paid_at' => $p->paid_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/QrisPayments', [
            'payments' => $rows,
            'filters' => ['status' => $status, 'q' => $request->query('q')],
            'statuses' => collect(PaymentStatus::cases())
                ->map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'counts' => [
                'pending' => Payment::where('provider', 'qris')
                    ->where('status', PaymentStatus::Pending->value)
                    ->count(),
            ],
        ]);
    }

    public function approve(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === 'qris', 404);

        if ($payment->status === PaymentStatus::Paid) {
            return back()->with('info', 'Pembayaran ini sudah lunas.');
        }

        if (! $payment->isOpen()) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini sudah ditutup dan tidak bisa disetujui.',
            ]);
        }

        $settled = $this->payments->markPaid($payment, new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            // Matches by construction; markPaid re-checks it against the bill.
            amount: (float) $payment->amount,
            paidAt: now(),
            eventId: 'qris-manual:'.$payment->id,
        ));

        // The amount is free for the next buyer once it is no longer awaited.
        $settled->forceFill(['claimable_amount' => null])->save();

        $this->audit->log('payment.qris_approved', $settled, after: [
            'reference' => $settled->reference,
            'amount' => (int) $settled->amount,
            'order' => $settled->order?->number,
        ]);

        return back()->with('success', "Pembayaran {$settled->reference} dikonfirmasi. Pesanan diproses.");
    }

    public function reject(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === 'qris', 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:300'],
        ]);

        if (! $payment->isOpen()) {
            throw ValidationException::withMessages([
                'payment' => 'Pembayaran ini sudah ditutup.',
            ]);
        }

        $this->payments->markFailed($payment);
        $payment->forceFill(['claimable_amount' => null])->save();

        $this->audit->log('payment.qris_rejected', $payment, reason: $data['reason']);

        return back()->with('info', "Pembayaran {$payment->reference} ditolak.");
    }
}
