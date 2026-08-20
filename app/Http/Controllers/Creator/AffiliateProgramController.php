<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\AffiliateApplication;
use App\Models\AffiliateProgram;
use App\Models\Commission;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateProgramController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->store;

        $program = AffiliateProgram::firstOrCreate(
            ['store_id' => $store->id, 'product_id' => null],
            [
                'commission_type' => 'percentage',
                'commission_value' => config('jualanyok.affiliate.default_commission_percent'),
                'cookie_days' => config('jualanyok.affiliate.default_cookie_days'),
                'is_active' => false,
            ],
        );

        $commissions = Commission::where('store_id', $store->id)
            ->selectRaw('status, COUNT(*) c, COALESCE(SUM(amount),0) total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('Creator/Affiliate', [
            'program' => [
                'id' => $program->id,
                'commission_type' => $program->commission_type,
                'commission_value' => (float) $program->commission_value,
                'cookie_days' => $program->cookie_days,
                'auto_approve' => (bool) $program->auto_approve,
                'is_active' => (bool) $program->is_active,
                'terms' => $program->terms,
            ],
            'stats' => [
                'affiliates' => $program->links()->distinct('user_id')->count('user_id'),
                'clicks' => (int) $program->links()->sum('clicks'),
                'conversions' => (int) $program->links()->sum('conversions'),
                'pending' => (float) ($commissions['PENDING']->total ?? 0),
                'approved' => (float) ($commissions['APPROVED']->total ?? 0),
                'paid' => (float) ($commissions['PAID']->total ?? 0),
            ],
            'applications' => AffiliateApplication::whereIn('affiliate_program_id', $store->affiliatePrograms()->pluck('id'))
                ->with('user:id,name,username,email')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (AffiliateApplication $a) => [
                    'id' => $a->id,
                    'status' => $a->status,
                    'message' => $a->message,
                    'user' => $a->user?->only(['id', 'name', 'username', 'email']),
                    'created_at' => $a->created_at->diffForHumans(),
                ]),
            'topAffiliates' => $program->links()
                ->with('affiliate:id,name,username')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(fn ($l) => [
                    'name' => $l->affiliate?->name,
                    'username' => $l->affiliate?->username,
                    'clicks' => $l->clicks,
                    'conversions' => $l->conversions,
                    'revenue' => (float) $l->revenue,
                ]),
            'canUseTools' => $this->plans->allows($request->user(), PlanService::AFFILIATE_TOOLS),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'commission_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'auto_approve' => ['boolean'],
            'is_active' => ['boolean'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['commission_type'] === 'percentage' && $data['commission_value'] > 90) {
            return back()->withErrors(['commission_value' => 'Komisi persentase maksimal 90%.']);
        }

        AffiliateProgram::where('store_id', $request->user()->store->id)
            ->whereNull('product_id')
            ->update($data);

        return back()->with('success', 'Pengaturan affiliate disimpan.');
    }

    public function review(Request $request, AffiliateApplication $application)
    {
        abort_unless(
            $application->program->store_id === $request->user()->store->id,
            403,
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['APPROVED', 'REJECTED', 'SUSPENDED'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $application->update([
            'status' => $data['status'],
            'review_note' => $data['note'] ?? null,
            'reviewed_at' => now(),
        ]);

        // Suspending an affiliate immediately stops new attributions but does
        // not touch commissions already earned.
        $application->program->links()
            ->where('user_id', $application->user_id)
            ->update(['is_active' => $data['status'] === 'APPROVED']);

        return back()->with('success', 'Aplikasi affiliate diperbarui.');
    }
}
