<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $leads = $request->user()->store->leads()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($s) => $s->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'email' => $l->email,
                'phone' => $l->phone,
                'fields' => $l->fields ?? [],
                'source' => $l->source,
                'created_at' => $l->created_at->toDateTimeString(),
                'created_human' => $l->created_at->diffForHumans(),
            ]);

        return Inertia::render('Creator/Leads', [
            'leads' => $leads,
            'filters' => $request->only('q'),
            'total' => $request->user()->store->leads()->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $store = $request->user()->store;

        return response()->streamDownload(function () use ($store) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Sumber', 'Tanggal']);

            $store->leads()->chunk(500, function ($leads) use ($out) {
                foreach ($leads as $l) {
                    fputcsv($out, [$l->name, $l->email, $l->phone, $l->source, $l->created_at->toDateTimeString()]);
                }
            });

            fclose($out);
        }, 'leads-'.$store->username.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
