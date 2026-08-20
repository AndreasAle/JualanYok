<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => PlatformSetting::all_settings(),
            'providers' => collect(config('payments.providers'))
                ->map(fn ($cfg, $key) => [
                    'key' => $key,
                    'enabled' => (bool) ($cfg['enabled'] ?? false),
                    // Whether the credentials are present, never their values.
                    'configured' => filled($cfg['server_key'] ?? $cfg['secret_key'] ?? $cfg['secret'] ?? null),
                ])
                ->values(),
            'defaultProvider' => config('payments.default'),
            'storage' => config('filesystems.default'),
            'mailer' => config('mail.default'),
            'queue' => config('queue.default'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'withdrawal_minimum' => ['required', 'numeric', 'min:0'],
            'withdrawal_fee' => ['required', 'numeric', 'min:0'],
            'withdrawal_holding_days' => ['required', 'integer', 'min:0', 'max:90'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:30'],
            'affiliate_hold_days' => ['required', 'integer', 'min:0', 'max:90'],
            'manual_accounts' => ['nullable', 'array'],
            'manual_accounts.*.bank' => ['required', 'string', 'max:60'],
            'manual_accounts.*.number' => ['required', 'string', 'max:40'],
            'manual_accounts.*.holder' => ['required', 'string', 'max:120'],
        ]);

        $map = [
            'withdrawal.minimum' => $data['withdrawal_minimum'],
            'withdrawal.fee' => $data['withdrawal_fee'],
            'withdrawal.holding_days' => $data['withdrawal_holding_days'],
            'tax.percent' => $data['tax_percent'],
            'affiliate.hold_days' => $data['affiliate_hold_days'],
            'payments.manual_accounts' => $data['manual_accounts'] ?? [],
        ];

        foreach ($map as $key => $value) {
            PlatformSetting::put($key, $value, 'finance');
        }

        $this->audit->log('platform.settings.updated', after: $map);

        return back()->with('success', 'Pengaturan platform disimpan.');
    }
}
