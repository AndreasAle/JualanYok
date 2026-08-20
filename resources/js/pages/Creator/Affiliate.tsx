import { router, useForm } from '@inertiajs/react';
import { Handshake } from 'lucide-react';
import type { FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { BarList, PageHeader, StatCard } from '@/components/shared';
import {
    Alert, Badge, Button, ButtonLink, Card, CardBody, CardHeader, CardTitle, EmptyState, Field,
    Input, Select, Switch, Textarea,
} from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

export default function CreatorAffiliate({
    program,
    stats,
    applications,
    topAffiliates,
    canUseTools,
}: {
    program: any;
    stats: any;
    applications: any[];
    topAffiliates: any[];
    canUseTools: boolean;
}) {
    const { data, setData, put, processing, errors } = useForm({
        commission_type: program.commission_type,
        commission_value: program.commission_value,
        cookie_days: program.cookie_days,
        auto_approve: program.auto_approve,
        is_active: program.is_active,
        terms: program.terms ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/dashboard/affiliate', { preserveScroll: true });
    };

    return (
        <DashboardLayout title="Affiliate" area="creator">
            <PageHeader
                title="Program Affiliate"
                description="Biarkan orang lain promosiin produkmu dan dapat komisi kalau laku."
                actions={
                    <ButtonLink href="/affiliate/marketplace" variant="outline">
                        Cari Produk buat Dipromosiin
                    </ButtonLink>
                }
            />

            {!canUseTools && (
                <div className="mb-4">
                    <Alert tone="info" title="Tool affiliate lengkap ada di paket Pro">
                        Program dasar tetap bisa kamu pakai. Upgrade buat materi promosi dan laporan lebih detail.
                    </Alert>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Afiliator aktif" value={formatNumber(stats.affiliates)} />
                <StatCard label="Klik masuk" value={formatNumber(stats.clicks)} />
                <StatCard label="Konversi" value={formatNumber(stats.conversions)} />
                <StatCard label="Komisi tertahan" value={formatIDR(stats.pending)} />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Pengaturan program</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <form onSubmit={submit} className="space-y-4">
                            <Switch
                                checked={data.is_active}
                                onChange={(v) => setData('is_active', v)}
                                label="Aktifkan program affiliate"
                                description="Kalau aktif, produk yang kamu izinkan muncul di marketplace affiliate."
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Jenis komisi" error={errors.commission_type} htmlFor="ctype">
                                    <Select
                                        id="ctype"
                                        value={data.commission_type}
                                        onChange={(e) => setData('commission_type', e.target.value)}
                                    >
                                        <option value="percentage">Persentase (%)</option>
                                        <option value="fixed">Nominal tetap (Rp)</option>
                                    </Select>
                                </Field>

                                <Field
                                    label={data.commission_type === 'percentage' ? 'Besar komisi (%)' : 'Nominal komisi'}
                                    error={errors.commission_value}
                                    htmlFor="cvalue"
                                >
                                    <Input
                                        id="cvalue"
                                        type="number"
                                        min={0}
                                        max={data.commission_type === 'percentage' ? 90 : undefined}
                                        value={data.commission_value}
                                        onChange={(e) => setData('commission_value', Number(e.target.value))}
                                        invalid={!!errors.commission_value}
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Durasi tracking (hari)"
                                error={errors.cookie_days}
                                hint="Berapa lama klik afiliator tetap dihitung."
                                htmlFor="cookie"
                            >
                                <Input
                                    id="cookie"
                                    type="number"
                                    min={1}
                                    max={365}
                                    value={data.cookie_days}
                                    onChange={(e) => setData('cookie_days', Number(e.target.value))}
                                />
                            </Field>

                            <Switch
                                checked={data.auto_approve}
                                onChange={(v) => setData('auto_approve', v)}
                                label="Setujui afiliator otomatis"
                                description="Kalau dimatikan, kamu review satu per satu."
                            />

                            <Field label="Syarat buat afiliator" error={errors.terms} htmlFor="terms">
                                <Textarea
                                    id="terms"
                                    rows={4}
                                    value={data.terms}
                                    onChange={(e) => setData('terms', e.target.value)}
                                    placeholder="Contoh: dilarang pakai iklan berbayar dengan nama brand."
                                />
                            </Field>

                            <Button type="submit" variant="gradient" loading={processing}>
                                Simpan Pengaturan
                            </Button>
                        </form>
                    </CardBody>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Afiliator teratas</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {topAffiliates.length === 0 ? (
                                <EmptyState
                                    icon={<Handshake className="size-6" />}
                                    title="Belum ada afiliator"
                                    description="Aktifkan program dan izinkan produkmu buat dipromosiin."
                                />
                            ) : (
                                <BarList
                                    items={topAffiliates.map((a) => ({
                                        label: a.name ?? a.username,
                                        value: a.revenue,
                                        hint: `${a.clicks} klik · ${a.conversions} konversi`,
                                    }))}
                                    format={formatIDR}
                                />
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Aplikasi affiliate</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {applications.length === 0 ? (
                                <p className="text-sm text-muted">Belum ada yang mendaftar.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {applications.map((application) => (
                                        <li
                                            key={application.id}
                                            className="rounded-[var(--radius-field)] bg-surface-2 p-3"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {application.user?.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted">
                                                        @{application.user?.username} · {application.created_at}
                                                    </p>
                                                </div>
                                                <Badge
                                                    tone={
                                                        application.status === 'APPROVED'
                                                            ? 'success'
                                                            : application.status === 'PENDING'
                                                              ? 'warning'
                                                              : 'danger'
                                                    }
                                                >
                                                    {application.status}
                                                </Badge>
                                            </div>

                                            {application.message && (
                                                <p className="mt-2 text-xs text-muted">{application.message}</p>
                                            )}

                                            {application.status === 'PENDING' && (
                                                <div className="mt-3 flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="success"
                                                        onClick={() =>
                                                            router.post(
                                                                `/dashboard/affiliate/aplikasi/${application.id}/review`,
                                                                { status: 'APPROVED' },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Setujui
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `/dashboard/affiliate/aplikasi/${application.id}/review`,
                                                                { status: 'REJECTED' },
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Tolak
                                                    </Button>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
