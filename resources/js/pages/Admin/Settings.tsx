import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import {
    Badge, Button, Card, CardBody, CardHeader, CardTitle, Field, Input,
} from '@/components/ui';

export default function AdminSettings({
    settings,
    providers,
    defaultProvider,
    storage,
    mailer,
    queue,
}: {
    settings: Record<string, any>;
    providers: { key: string; enabled: boolean; configured: boolean }[];
    defaultProvider: string;
    storage: string;
    mailer: string;
    queue: string;
}) {
    const { data, setData, put, processing, errors } = useForm({
        withdrawal_minimum: settings['withdrawal.minimum'] ?? 50000,
        withdrawal_fee: settings['withdrawal.fee'] ?? 5000,
        withdrawal_holding_days: settings['withdrawal.holding_days'] ?? 7,
        tax_percent: settings['tax.percent'] ?? 0,
        affiliate_hold_days: settings['affiliate.hold_days'] ?? 14,
        manual_accounts: (settings['payments.manual_accounts'] ?? []) as {
            bank: string;
            number: string;
            holder: string;
        }[],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/pengaturan', { preserveScroll: true });
    };

    return (
        <DashboardLayout title="Pengaturan Platform" area="admin">
            <PageHeader
                title="Pengaturan Platform"
                description="Nilai di sini dibaca langsung oleh sistem — perubahan berlaku tanpa deploy."
            />

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Keuangan</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Minimum penarikan"
                                    error={errors.withdrawal_minimum}
                                    htmlFor="min-withdrawal"
                                >
                                    <Input
                                        id="min-withdrawal"
                                        type="number"
                                        min={0}
                                        step={1000}
                                        value={data.withdrawal_minimum}
                                        onChange={(e) => setData('withdrawal_minimum', Number(e.target.value))}
                                    />
                                </Field>

                                <Field label="Biaya penarikan" error={errors.withdrawal_fee} htmlFor="fee-withdrawal">
                                    <Input
                                        id="fee-withdrawal"
                                        type="number"
                                        min={0}
                                        step={500}
                                        value={data.withdrawal_fee}
                                        onChange={(e) => setData('withdrawal_fee', Number(e.target.value))}
                                    />
                                </Field>

                                <Field
                                    label="Masa tahan dana (hari)"
                                    error={errors.withdrawal_holding_days}
                                    hint="Sebelum dana penjualan bisa ditarik."
                                    htmlFor="holding"
                                >
                                    <Input
                                        id="holding"
                                        type="number"
                                        min={0}
                                        max={90}
                                        value={data.withdrawal_holding_days}
                                        onChange={(e) => setData('withdrawal_holding_days', Number(e.target.value))}
                                    />
                                </Field>

                                <Field
                                    label="Masa tahan komisi (hari)"
                                    error={errors.affiliate_hold_days}
                                    htmlFor="affiliate-hold"
                                >
                                    <Input
                                        id="affiliate-hold"
                                        type="number"
                                        min={0}
                                        max={90}
                                        value={data.affiliate_hold_days}
                                        onChange={(e) => setData('affiliate_hold_days', Number(e.target.value))}
                                    />
                                </Field>

                                <Field label="Pajak (%)" error={errors.tax_percent} htmlFor="tax">
                                    <Input
                                        id="tax"
                                        type="number"
                                        min={0}
                                        max={30}
                                        step={0.1}
                                        value={data.tax_percent}
                                        onChange={(e) => setData('tax_percent', Number(e.target.value))}
                                    />
                                </Field>
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rekening transfer manual</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-3">
                            {data.manual_accounts.map((account, index) => (
                                <div key={index} className="grid gap-2 sm:grid-cols-[1fr_1.2fr_1.2fr_auto]">
                                    <Input
                                        value={account.bank}
                                        onChange={(e) =>
                                            setData(
                                                'manual_accounts',
                                                data.manual_accounts.map((a, i) =>
                                                    i === index ? { ...a, bank: e.target.value } : a,
                                                ),
                                            )
                                        }
                                        placeholder="Bank"
                                        aria-label="Nama bank"
                                    />
                                    <Input
                                        value={account.number}
                                        onChange={(e) =>
                                            setData(
                                                'manual_accounts',
                                                data.manual_accounts.map((a, i) =>
                                                    i === index ? { ...a, number: e.target.value } : a,
                                                ),
                                            )
                                        }
                                        placeholder="Nomor rekening"
                                        aria-label="Nomor rekening"
                                    />
                                    <Input
                                        value={account.holder}
                                        onChange={(e) =>
                                            setData(
                                                'manual_accounts',
                                                data.manual_accounts.map((a, i) =>
                                                    i === index ? { ...a, holder: e.target.value } : a,
                                                ),
                                            )
                                        }
                                        placeholder="Atas nama"
                                        aria-label="Atas nama"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Hapus rekening"
                                        onClick={() =>
                                            setData(
                                                'manual_accounts',
                                                data.manual_accounts.filter((_, i) => i !== index),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4 text-[var(--danger)]" />
                                    </Button>
                                </div>
                            ))}

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData('manual_accounts', [
                                        ...data.manual_accounts,
                                        { bank: '', number: '', holder: '' },
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Tambah Rekening
                            </Button>
                        </CardBody>
                    </Card>

                    <Button type="submit" variant="gradient" size="lg" loading={processing}>
                        Simpan Pengaturan
                    </Button>
                </form>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment provider</CardTitle>
                        </CardHeader>
                        <CardBody>
                            <p className="mb-3 text-sm text-muted">
                                Provider diatur lewat environment variable, bukan dari sini.
                            </p>

                            <ul className="space-y-2">
                                {providers.map((provider) => (
                                    <li
                                        key={provider.key}
                                        className="flex items-center justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3"
                                    >
                                        <span className="font-mono text-sm">
                                            {provider.key}
                                            {provider.key === defaultProvider && (
                                                <Badge tone="brand" className="ml-2">
                                                    default
                                                </Badge>
                                            )}
                                        </span>

                                        <span className="flex shrink-0 gap-1">
                                            <Badge tone={provider.enabled ? 'success' : 'neutral'}>
                                                {provider.enabled ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                            {provider.enabled && !provider.configured && (
                                                <Badge tone="warning">Kredensial kosong</Badge>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Infrastruktur</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-2 text-sm">
                            <Row label="Storage" value={storage} />
                            <Row label="Mailer" value={mailer} />
                            <Row label="Queue" value={queue} />
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted">{label}</span>
            <span className="font-mono font-semibold">{value}</span>
        </div>
    );
}
