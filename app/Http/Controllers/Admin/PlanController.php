<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans', [
            'plans' => Plan::with('features')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'tagline' => $p->tagline,
                    'price_monthly' => (float) $p->price_monthly,
                    'price_yearly' => (float) $p->price_yearly,
                    'transaction_fee_percent' => (float) $p->transaction_fee_percent,
                    'transaction_fee_fixed' => (float) $p->transaction_fee_fixed,
                    'trial_days' => $p->trial_days,
                    'is_active' => (bool) $p->is_active,
                    'is_public' => (bool) $p->is_public,
                    'subscribers' => $p->subscriptions()->where('status', 'ACTIVE')->count(),
                    'features' => $p->features->map(fn ($f) => [
                        'key' => $f->key,
                        'label' => $f->label,
                        'value_type' => $f->value_type,
                        'enabled' => (bool) $f->enabled,
                        'limit' => $f->limit,
                    ]),
                ]),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'transaction_fee_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'transaction_fee_fixed' => ['required', 'numeric', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'features' => ['array'],
            'features.*.key' => ['required', 'string', 'max:60'],
            'features.*.enabled' => ['boolean'],
            'features.*.limit' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->except('features')->all());

            foreach ($data['features'] ?? [] as $feature) {
                $plan->features()->updateOrCreate(
                    ['key' => $feature['key']],
                    [
                        'enabled' => $feature['enabled'] ?? true,
                        'limit' => $feature['limit'] ?? null,
                    ],
                );
            }
        });

        $this->audit->log('plan.updated', $plan, after: $data);

        return back()->with('success', "Paket {$plan->name} diperbarui.");
    }
}
