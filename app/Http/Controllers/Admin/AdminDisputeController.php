<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderDispute;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\DisputeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputes,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $rows = OrderDispute::query()
            ->with(['order.store:id,name', 'order.shipment', 'customer:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (OrderDispute $dispute) => [
                'id' => $dispute->id,
                'number' => $dispute->number,
                'order_number' => $dispute->order->number,
                'store' => $dispute->order->store->name,
                'buyer' => $dispute->customer?->name ?: $dispute->order->customer_name,
                'buyer_email' => $dispute->customer?->email ?: $dispute->order->customer_email,
                'type' => $dispute->type,
                'status' => $dispute->status->value,
                'status_label' => $dispute->status->label(),
                'description' => $dispute->description,
                'seller_response' => $dispute->seller_response,
                'seller_response_due_at' => $dispute->seller_response_due_at?->toDateTimeString(),
                'order_total' => (float) $dispute->order->grand_total,
                'courier' => $dispute->order->shipment?->courier_name,
                'waybill_id' => $dispute->order->shipment?->waybill_id,
                'created_at' => $dispute->created_at->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Disputes', [
            'disputes' => $rows,
            'filters' => $request->only('status'),
            'canResolve' => $request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN),
        ]);
    }

    public function resolve(Request $request, OrderDispute $dispute)
    {
        abort_unless($request->user()->hasRole(Role::FINANCE_ADMIN, Role::SUPER_ADMIN), 403);

        $data = $request->validate([
            'winner' => ['required', 'in:buyer,seller'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        abort_if($dispute->status->isClosed(), 422, 'Komplain sudah ditutup.');

        $this->disputes->resolve($dispute, $request->user(), $data['winner'], $data['note']);
        $this->audit->log('dispute.resolved', $dispute, after: ['winner' => $data['winner']], reason: $data['note']);

        return back()->with('success', $data['winner'] === 'buyer'
            ? 'Keputusan untuk pembeli dicatat. Refund masuk ke antrean finance.'
            : 'Keputusan untuk penjual dicatat dan dana siap dilepas.');
    }
}
