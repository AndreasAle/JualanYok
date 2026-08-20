import { router } from '@inertiajs/react';
import { CheckCircle2, Minus } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, PageHeader } from '@/components/shared';
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle } from '@/components/ui';
import { cn, formatDate, formatIDR, formatNumber } from '@/lib/utils';

export default function Subscription({
    current,
    plans,
    usage,
    invoices,
    billingProvider,
}: {
    current: any | null;
    plans: any[];
    usage: Record<string, { used: number; limit: number | null }>;
    invoices: any[];
    billingProvider: string;
}) {
    const [yearly, setYearly] = useState(current?.interval === 'yearly');

    return (
        <DashboardLayout title="Langganan" area="creator">
            <PageHeader
                title="Langganan"
                description="Naik paket kalau butuh limit lebih besar dan biaya transaksi lebih rendah."
            />

            {billingProvider === 'mock' && (
                <div className="mb-4">
                    <Alert tone="info" title="Mode pengembangan">
                        Pembayaran langganan diproses lewat provider simulasi. Upgrade langsung aktif tanpa
                        tagihan sungguhan.
                    </Alert>
                </div>
            )}

            {/* Current plan */}
            <Card className="mb-6 p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Paket sekarang</p>
                        <p className="mt-1 text-2xl font-extrabold">{current?.plan_name ?? 'Gratis'}</p>
                        {current && (
                            <p className="mt-1 text-sm text-muted">
                                <Badge tone={current.status === 'ACTIVE' ? 'success' : 'warning'}>
                                    {current.status_label}
                                </Badge>{' '}
                                {current.current_period_end &&
                                    `Berlaku sampai ${formatDate(current.current_period_end)}`}
                            </p>
                        )}
                    </div>

                    {current && current.plan !== 'free' && !current.cancel_at_period_end && (
                        <ConfirmButton
                            title="Batalkan langganan?"
                            message="Paket kamu tetap aktif sampai akhir periode berjalan, setelah itu turun ke paket Gratis."
                            confirmLabel="Ya, batalkan"
                            onConfirm={() => router.post('/dashboard/langganan/batal')}
                        >
                            <Button variant="outline">Batalkan Langganan</Button>
                        </ConfirmButton>
                    )}
                </div>

                {current?.cancel_at_period_end && (
                    <div className="mt-4">
                        <Alert tone="warning">
                            Langganan akan berhenti pada {formatDate(current.current_period_end)}.
                        </Alert>
                    </div>
                )}

                {/* Usage */}
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    {Object.entries(usage).map(([key, value]) => {
                        const percent =
                            value.limit === null ? 0 : Math.min(100, Math.round((value.used / value.limit) * 100));

                        return (
                            <div key={key} className="rounded-[var(--radius-field)] bg-surface-2 p-4">
                                <div className="flex items-baseline justify-between text-sm">
                                    <span className="font-semibold capitalize">{key}</span>
                                    <span className="tabular-nums">
                                        {formatNumber(value.used)}
                                        {value.limit === null ? ' / tanpa batas' : ` / ${formatNumber(value.limit)}`}
                                    </span>
                                </div>
                                {value.limit !== null && (
                                    <div className="mt-2 h-2 rounded-full bg-[var(--border)]">
                                        <div
                                            className={cn(
                                                'h-full rounded-full',
                                                percent >= 90 ? 'bg-[var(--danger)]' : 'gradient-brand',
                                            )}
                                            style={{ width: `${Math.max(3, percent)}%` }}
                                        />
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </Card>

            {/* Interval toggle */}
            <div className="mb-4 flex items-center justify-center gap-3">
                <span className={cn('text-sm font-semibold', !yearly && 'text-[var(--primary)]')}>Bulanan</span>
                <button
                    type="button"
                    role="switch"
                    aria-checked={yearly}
                    aria-label="Tagihan tahunan"
                    onClick={() => setYearly((v) => !v)}
                    className={cn(
                        'relative h-6 w-11 rounded-full transition-colors',
                        yearly ? 'bg-[var(--primary)]' : 'bg-[var(--border)]',
                    )}
                >
                    <span
                        className={cn(
                            'absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform',
                            yearly && 'translate-x-5',
                        )}
                    />
                </button>
                <span className={cn('text-sm font-semibold', yearly && 'text-[var(--primary)]')}>
                    Tahunan <Badge tone="success">Hemat</Badge>
                </span>
            </div>

            {/* Plans */}
            <div className="grid gap-4 lg:grid-cols-4">
                {plans.map((plan) => {
                    const price = yearly ? plan.price_yearly : plan.price_monthly;
                    const isCurrent = current?.plan === plan.slug || (!current && plan.slug === 'free');

                    return (
                        <Card
                            key={plan.slug}
                            className={cn('flex flex-col p-5', isCurrent && 'ring-2 ring-[var(--primary)]')}
                        >
                            {isCurrent && (
                                <Badge tone="brand" className="mb-3 self-start">
                                    Paket kamu
                                </Badge>
                            )}

                            <p className="font-extrabold">{plan.name}</p>
                            <p className="mt-1 min-h-10 text-sm text-muted">{plan.tagline}</p>

                            <p className="mt-4 text-2xl font-extrabold">
                                {price === 0 ? 'Gratis' : formatIDR(price)}
                                {price > 0 && (
                                    <span className="text-sm font-medium text-muted">/{yearly ? 'thn' : 'bln'}</span>
                                )}
                            </p>
                            <p className="mt-1 text-xs text-muted">
                                Biaya transaksi {plan.transaction_fee_percent}%
                            </p>

                            <ul className="mt-4 flex-1 space-y-1.5">
                                {plan.features
                                    .filter((f: any) => f.label)
                                    .slice(0, 6)
                                    .map((feature: any) => (
                                        <li key={feature.key} className="flex items-start gap-2 text-xs">
                                            {feature.enabled ? (
                                                <CheckCircle2 className="mt-0.5 size-3.5 shrink-0 text-[var(--success)]" />
                                            ) : (
                                                <Minus className="mt-0.5 size-3.5 shrink-0 text-muted/50" />
                                            )}
                                            <span className={feature.enabled ? '' : 'text-muted line-through'}>
                                                {feature.label}
                                                {feature.enabled && feature.limit !== null && ` (${feature.limit})`}
                                            </span>
                                        </li>
                                    ))}
                            </ul>

                            {!isCurrent && (
                                <ConfirmButton
                                    title={`Pindah ke paket ${plan.name}?`}
                                    message={
                                        price === 0
                                            ? 'Kamu akan turun ke paket Gratis dengan limit yang lebih kecil.'
                                            : `Kamu akan ditagih ${formatIDR(price)} per ${yearly ? 'tahun' : 'bulan'}.`
                                    }
                                    confirmLabel="Ya, lanjut"
                                    variant="primary"
                                    onConfirm={() =>
                                        router.post('/dashboard/langganan', {
                                            plan: plan.slug,
                                            interval: yearly ? 'yearly' : 'monthly',
                                        })
                                    }
                                >
                                    <Button variant={plan.slug === 'pro' ? 'gradient' : 'outline'} block className="mt-5">
                                        Pilih {plan.name}
                                    </Button>
                                </ConfirmButton>
                            )}
                        </Card>
                    );
                })}
            </div>

            {invoices.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Riwayat tagihan</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <ul className="divide-y divide-[var(--border)]">
                            {invoices.map((invoice) => (
                                <li key={invoice.number} className="flex items-center justify-between gap-3 py-3">
                                    <span className="min-w-0">
                                        <span className="block font-mono text-sm font-semibold">{invoice.number}</span>
                                        <span className="block text-xs text-muted">
                                            {formatDate(invoice.period_start)} – {formatDate(invoice.period_end)}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right">
                                        <span className="block font-bold">{formatIDR(invoice.amount)}</span>
                                        <Badge tone={invoice.status === 'PAID' ? 'success' : 'warning'}>
                                            {invoice.status}
                                        </Badge>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardBody>
                </Card>
            )}
        </DashboardLayout>
    );
}
