<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = $request->user()->store->customers()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($s) => $s->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->boolean('consent_only'), fn ($q) => $q->where('marketing_consent', true))
            ->orderByDesc('lifetime_value')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Customer $c) => $this->payload($c));

        return Inertia::render('Creator/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['q', 'consent_only']),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        $customer->load(['orders' => fn ($q) => $q->latest()->limit(20), 'addresses']);

        return Inertia::render('Creator/Customers/Show', [
            'customer' => $this->payload($customer) + [
                'notes' => $customer->notes,
                'addresses' => $customer->addresses,
                'orders' => $customer->orders->map(fn ($o) => [
                    'number' => $o->number,
                    'grand_total' => (float) $o->grand_total,
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'created_at' => $o->created_at->toDateTimeString(),
                ]),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $store = $request->user()->store;
        $filename = 'pelanggan-'.$store->username.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($store) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Email', 'Telepon', 'Total Order', 'Lifetime Value', 'Consent', 'Order Terakhir']);

            // Chunked so a large customer list never loads fully into memory.
            $store->customers()->chunk(500, function ($customers) use ($out) {
                foreach ($customers as $c) {
                    fputcsv($out, [
                        $c->name, $c->email, $c->phone, $c->orders_count,
                        $c->lifetime_value, $c->marketing_consent ? 'ya' : 'tidak',
                        $c->last_order_at?->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function payload(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'tags' => $c->tags ?? [],
            'orders_count' => $c->orders_count,
            'lifetime_value' => (float) $c->lifetime_value,
            'marketing_consent' => (bool) $c->marketing_consent,
            'source' => $c->source,
            'last_order_at' => $c->last_order_at?->toDateString(),
        ];
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        abort_unless($customer->store_id === $request->user()->store->id, 403);
    }
}
