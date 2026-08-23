<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PlanPayment;
use App\Services\AuditLogger;
use App\Services\PlanPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an admin matches an incoming QRIS transfer to a waiting subscriber.
 *
 * The queue is ordered by the amount the payer was told to send, because that
 * is the one thing an admin can read off a wallet notification and search for.
 */
class PlanPaymentController extends Controller
{
    public function __construct(
        private readonly PlanPaymentService $payments,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        // Keeps the queue honest: lapsed windows should not look actionable.
        $this->payments->expireLapsed();

        $status = $request->query('status', PlanPaymentStatus::AwaitingReview->value);

        $rows = PlanPayment::with(['user', 'plan', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = trim($request->query('q'));

                $q->where(function ($inner) use ($needle) {
                    $inner->where('amount', 'like', "%{$needle}%")
                        ->orWhere('reference', 'like', "%{$needle}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$needle}%")
                            ->orWhere('name', 'like', "%{$needle}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PlanPayment $p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'user_name' => $p->user?->name,
                'user_email' => $p->user?->email,
                'plan_name' => $p->plan?->name,
                'interval' => $p->billing_interval,
                'base_amount' => (int) $p->base_amount,
                'amount' => (int) $p->amount,
                'unique_suffix' => (int) $p->unique_suffix,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'payer_note' => $p->payer_note,
                'review_note' => $p->review_note,
                'reviewer' => $p->reviewer?->name,
                'confirmed_at' => $p->confirmed_at?->toDateTimeString(),
                'expires_at' => $p->expires_at->toDateTimeString(),
                'created_at' => $p->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/PlanPayments', [
            'payments' => $rows,
            'filters' => ['status' => $status, 'q' => $request->query('q')],
            'statuses' => collect(PlanPaymentStatus::cases())
                ->map(fn (PlanPaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
            'counts' => [
                'awaiting' => PlanPayment::where('status', PlanPaymentStatus::AwaitingReview->value)->count(),
                'pending' => PlanPayment::where('status', PlanPaymentStatus::Pending->value)->count(),
            ],
        ]);
    }

    public function approve(Request $request, PlanPayment $payment)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $approved = $this->payments->approve($payment, $request->user(), $data['note'] ?? null);

        $this->audit->log('plan_payment.approved', $approved, after: [
            'reference' => $approved->reference,
            'amount' => (int) $approved->amount,
            'plan' => $approved->plan?->slug,
        ]);

        return back()->with('success', "Pembayaran {$approved->reference} disetujui. Paket sudah aktif.");
    }

    public function reject(Request $request, PlanPayment $payment)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:300'],
            'status' => ['nullable', Rule::in([PlanPaymentStatus::Rejected->value])],
        ]);

        $rejected = $this->payments->reject($payment, $request->user(), $data['reason']);

        $this->audit->log('plan_payment.rejected', $rejected, reason: $data['reason']);

        return back()->with('info', "Pembayaran {$rejected->reference} ditolak.");
    }
}
