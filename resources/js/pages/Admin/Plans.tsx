import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import {
    Badge, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Switch,
} from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

interface Feature {
    key: string;
    label: string | null;
    value_type: string;
    enabled: boolean;
    limit: number | null;
}

interface Plan {
    slug: string;
    name: string;
    tagline: string | null;
    price_monthly: number;
    price_yearly: number;
    transaction_fee_percent: number;
    transaction_fee_fixed: number;
    trial_days: number;
    is_active: boolean;
    is_public: boolean;
    subscribers: number;
    features: Feature[];
}

export default function AdminPlans({ plans }: { plans: Plan[] }) {
    const [editing, setEditing] = useState<string | null>(null);

    return (
        <DashboardLayout title="Paket" area="admin">
            <PageHeader
                title="Paket Langganan"
                description="Ubah harga, biaya transaksi, dan limit fitur tanpa perlu deploy ulang."
            />

            <div className="space-y-4">
                {plans.map((plan) =>
                    editing === plan.slug ? (
                        <PlanEditor key={plan.slug} plan={plan} onClose={() => setEditing(null)} />
                    ) : (
                        <Card key={plan.slug} className="p-5">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-lg font-extrabold">{plan.name}</h2>
                                        {!plan.is_active && <Badge tone="danger">Nonaktif</Badge>}
                                        {!plan.is_public && <Badge tone="warning">Tersembunyi</Badge>}
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted">{plan.tagline}</p>

                                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                                        <span>
                                            <span className="text-muted">Bulanan:</span>{' '}
                                            <strong>{formatIDR(plan.price_monthly)}</strong>
                                        </span>
                                        <span>
                                            <span className="text-muted">Tahunan:</span>{' '}
                                            <strong>{formatIDR(plan.price_yearly)}</strong>
                                        </span>
                                        <span>
                                            <span className="text-muted">Fee transaksi:</span>{' '}
                                            <strong>{plan.transaction_fee_percent}%</strong>
                                            {plan.transaction_fee_fixed > 0 &&
                                                ` + ${formatIDR(plan.transaction_fee_fixed)}`}
                                        </span>
                                        <span>
                                            <span className="text-muted">Pelanggan aktif:</span>{' '}
                                            <strong>{formatNumber(plan.subscribers)}</strong>
                                        </span>
                                    </div>
                                </div>

                                <Button variant="outline" onClick={() => setEditing(plan.slug)} className="shrink-0">
                                    Ubah
                                </Button>
                            </div>

                            <div className="mt-4 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                                {plan.features.map((feature) => (
                                    <div
                                        key={feature.key}
                                        className="flex items-center justify-between gap-2 rounded-[var(--radius-field)] bg-surface-2 px-3 py-2 text-xs"
                                    >
                                        <span className="min-w-0 truncate">{feature.label ?? feature.key}</span>
                                        <span className="shrink-0 font-bold">
                                            {!feature.enabled
                                                ? '—'
                                                : feature.limit === null
                                                  ? '∞'
                                                  : formatNumber(feature.limit)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    ),
                )}
            </div>
        </DashboardLayout>
    );
}

function PlanEditor({ plan, onClose }: { plan: Plan; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: plan.name,
        tagline: plan.tagline ?? '',
        price_monthly: plan.price_monthly,
        price_yearly: plan.price_yearly,
        transaction_fee_percent: plan.transaction_fee_percent,
        transaction_fee_fixed: plan.transaction_fee_fixed,
        trial_days: plan.trial_days,
        is_active: plan.is_active,
        is_public: plan.is_public,
        features: plan.features.map((f) => ({ key: f.key, enabled: f.enabled, limit: f.limit })),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/paket/${plan.slug}`, { preserveScroll: true, onSuccess: onClose });
    };

    const setFeature = (key: string, patch: Partial<{ enabled: boolean; limit: number | null }>) => {
        setData(
            'features',
            data.features.map((f) => (f.key === key ? { ...f, ...patch } : f)),
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Ubah paket {plan.name}</CardTitle>
            </CardHeader>
            <CardBody>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nama" required error={errors.name} htmlFor={`${plan.slug}-name`}>
                            <Input
                                id={`${plan.slug}-name`}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </Field>

                        <Field label="Tagline" error={errors.tagline} htmlFor={`${plan.slug}-tagline`}>
                            <Input
                                id={`${plan.slug}-tagline`}
                                value={data.tagline}
                                onChange={(e) => setData('tagline', e.target.value)}
                            />
                        </Field>

                        <Field label="Harga bulanan" error={errors.price_monthly} htmlFor={`${plan.slug}-monthly`}>
                            <Input
                                id={`${plan.slug}-monthly`}
                                type="number"
                                min={0}
                                step={1000}
                                value={data.price_monthly}
                                onChange={(e) => setData('price_monthly', Number(e.target.value))}
                            />
                        </Field>

                        <Field label="Harga tahunan" error={errors.price_yearly} htmlFor={`${plan.slug}-yearly`}>
                            <Input
                                id={`${plan.slug}-yearly`}
                                type="number"
                                min={0}
                                step={1000}
                                value={data.price_yearly}
                                onChange={(e) => setData('price_yearly', Number(e.target.value))}
                            />
                        </Field>

                        <Field
                            label="Biaya transaksi (%)"
                            error={errors.transaction_fee_percent}
                            htmlFor={`${plan.slug}-fee`}
                        >
                            <Input
                                id={`${plan.slug}-fee`}
                                type="number"
                                min={0}
                                max={50}
                                step={0.1}
                                value={data.transaction_fee_percent}
                                onChange={(e) => setData('transaction_fee_percent', Number(e.target.value))}
                            />
                        </Field>

                        <Field
                            label="Biaya transaksi tetap"
                            error={errors.transaction_fee_fixed}
                            htmlFor={`${plan.slug}-fee-fixed`}
                        >
                            <Input
                                id={`${plan.slug}-fee-fixed`}
                                type="number"
                                min={0}
                                step={500}
                                value={data.transaction_fee_fixed}
                                onChange={(e) => setData('transaction_fee_fixed', Number(e.target.value))}
                            />
                        </Field>

                        <Field label="Hari masa coba" error={errors.trial_days} htmlFor={`${plan.slug}-trial`}>
                            <Input
                                id={`${plan.slug}-trial`}
                                type="number"
                                min={0}
                                max={90}
                                value={data.trial_days}
                                onChange={(e) => setData('trial_days', Number(e.target.value))}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Switch
                            checked={data.is_active}
                            onChange={(v) => setData('is_active', v)}
                            label="Paket aktif"
                        />
                        <Switch
                            checked={data.is_public}
                            onChange={(v) => setData('is_public', v)}
                            label="Tampil di halaman harga"
                        />
                    </div>

                    <div className="border-t border-line pt-4">
                        <p className="mb-3 text-sm font-bold">Fitur &amp; limit</p>

                        <div className="space-y-2">
                            {data.features.map((feature) => {
                                const meta = plan.features.find((f) => f.key === feature.key);

                                return (
                                    <div
                                        key={feature.key}
                                        className="flex flex-wrap items-center gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-medium">
                                                {meta?.label ?? feature.key}
                                            </span>
                                            <span className="block font-mono text-[11px] text-muted">
                                                {feature.key}
                                            </span>
                                        </span>

                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={feature.enabled}
                                                onChange={(e) =>
                                                    setFeature(feature.key, { enabled: e.target.checked })
                                                }
                                                className="size-4 accent-[var(--primary)]"
                                            />
                                            Aktif
                                        </label>

                                        {meta?.value_type === 'limit' && (
                                            <Input
                                                type="number"
                                                min={0}
                                                placeholder="∞"
                                                value={feature.limit ?? ''}
                                                onChange={(e) =>
                                                    setFeature(feature.key, {
                                                        limit: e.target.value === '' ? null : Number(e.target.value),
                                                    })
                                                }
                                                className="w-24"
                                                aria-label={`Limit ${feature.key}`}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" variant="gradient" loading={processing}>
                            Simpan Paket
                        </Button>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Batal
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}
