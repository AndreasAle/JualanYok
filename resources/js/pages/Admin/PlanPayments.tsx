import { router } from '@inertiajs/react';
import { Check, Copy, Search, X } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, Pagination, StatusBadge } from '@/components/shared';
import { Alert, Badge, Button, Card, CardBody, EmptyState, Field, Input, Select, Textarea } from '@/components/ui';
import { formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface PlanPaymentRow {
    id: number;
    reference: string;
    user_name: string | null;
    user_email: string | null;
    plan_name: string | null;
    interval: string;
    base_amount: number;
    amount: number;
    unique_suffix: number;
    status: string;
    status_label: string;
    payer_note: string | null;
    review_note: string | null;
    reviewer: string | null;
    confirmed_at: string | null;
    expires_at: string;
    created_at: string;
}

export default function PlanPayments({
    payments,
    filters,
    statuses,
    counts,
}: {
    payments: Paginated<PlanPaymentRow>;
    filters: { status: string; q: string | null };
    statuses: { value: string; label: string }[];
    counts: { awaiting: number; pending: number };
}) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [rejecting, setRejecting] = useState<PlanPaymentRow | null>(null);
    const [reason, setReason] = useState('');
    const [copied, setCopied] = useState<number | null>(null);

    const applyFilter = (patch: Record<string, string>) => {
        router.get('/admin/pembayaran-langganan', { ...filters, q: query, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const copyAmount = async (row: PlanPaymentRow) => {
        await navigator.clipboard.writeText(String(row.amount));
        setCopied(row.id);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <DashboardLayout title="Pembayaran Langganan" area="admin">
            <PageHeader
                title="Pembayaran Langganan"
                description="Cocokkan nominal masuk di aplikasi dompet dengan antrean di bawah, lalu setujui."
            />

            <div className="mb-4">
                <Alert tone="info" title="Cara mencocokkan">
                    <span className="text-sm">
                        Setiap orang dapat nominal unik dengan tiga digit terakhir berbeda. Cari nominal yang
                        persis sama dengan yang masuk ke dompet — kalau tidak ada yang cocok,{' '}
                        <b>jangan disetujui</b>.
                    </span>
                </Alert>
            </div>

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <Field label="Cari" htmlFor="q" className="min-w-[240px] flex-1">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                        <Input
                            id="q"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={(event) => event.key === 'Enter' && applyFilter({})}
                            placeholder="Nominal, referensi, nama, atau email"
                            className="pl-9"
                        />
                    </div>
                </Field>

                <Field label="Status" htmlFor="status" className="w-56">
                    <Select
                        id="status"
                        value={filters.status}
                        onChange={(event) => applyFilter({ status: event.target.value })}
                    >
                        <option value="all">Semua status</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </Select>
                </Field>

                <div className="flex gap-2 pb-0.5">
                    <Badge tone="warning">{counts.awaiting} perlu dicek</Badge>
                    <Badge tone="neutral">{counts.pending} menunggu bayar</Badge>
                </div>
            </div>

            {payments.data.length === 0 ? (
                <EmptyState
                    title="Belum ada pembayaran"
                    description="Pembayaran langganan lewat QRIS akan muncul di sini."
                />
            ) : (
                <div className="space-y-3">
                    {payments.data.map((row) => (
                        <Card key={row.id}>
                            <CardBody className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge status={row.status} label={row.status_label} />
                                        <span className="text-xs text-muted">{row.reference}</span>
                                    </div>

                                    <p className="mt-2 text-lg font-semibold tabular-nums">
                                        {formatIDR(row.amount)}
                                        <span className="ml-2 text-xs font-semibold text-muted">
                                            ({formatIDR(row.base_amount)} + {row.unique_suffix})
                                        </span>
                                    </p>

                                    <p className="mt-1 text-sm">
                                        <b>{row.plan_name}</b> · {row.interval === 'yearly' ? 'tahunan' : 'bulanan'} ·{' '}
                                        {row.user_name}{' '}
                                        <span className="text-muted">({row.user_email})</span>
                                    </p>

                                    {row.payer_note && (
                                        <p className="mt-1 text-xs text-muted">Catatan pembayar: {row.payer_note}</p>
                                    )}
                                    {row.review_note && (
                                        <p className="mt-1 text-xs text-muted">
                                            Catatan admin{row.reviewer ? ` (${row.reviewer})` : ''}: {row.review_note}
                                        </p>
                                    )}

                                    <p className="mt-1 text-xs text-muted">
                                        Dibuat {row.created_at}
                                        {row.confirmed_at && ` · dikonfirmasi pembayar ${row.confirmed_at}`}
                                    </p>
                                </div>

                                <div className="flex shrink-0 flex-wrap gap-2">
                                    <Button type="button" variant="outline" onClick={() => copyAmount(row)}>
                                        {copied === row.id ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                                        {copied === row.id ? 'Tersalin' : 'Salin nominal'}
                                    </Button>

                                    {(row.status === 'AWAITING_REVIEW' || row.status === 'PENDING') && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="gradient"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/pembayaran-langganan/${row.reference}/setujui`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <Check className="size-4" />
                                                Setujui
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setRejecting(row);
                                                    setReason('');
                                                }}
                                            >
                                                <X className="size-4 text-[var(--danger)]" />
                                                Tolak
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </CardBody>
                        </Card>
                    ))}
                </div>
            )}

            <Pagination meta={payments} />

            {rejecting && (
                <div
                    className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4"
                    role="dialog"
                    aria-modal="true"
                    onClick={(event) => event.target === event.currentTarget && setRejecting(null)}
                >
                    <Card className="w-full max-w-md">
                        <CardBody className="space-y-4">
                            <div>
                                <h2 className="text-lg font-semibold">Tolak pembayaran?</h2>
                                <p className="mt-1 text-sm text-muted">
                                    {rejecting.reference} · {formatIDR(rejecting.amount)}
                                </p>
                            </div>

                            <Field label="Alasan" required htmlFor="reason">
                                <Textarea
                                    id="reason"
                                    rows={3}
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    placeholder="Contoh: tidak ada dana masuk dengan nominal ini."
                                />
                            </Field>

                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    block
                                    disabled={!reason.trim()}
                                    onClick={() => {
                                        router.post(
                                            `/admin/pembayaran-langganan/${rejecting.reference}/tolak`,
                                            { reason },
                                            { preserveScroll: true, onSuccess: () => setRejecting(null) },
                                        );
                                    }}
                                >
                                    Ya, tolak
                                </Button>
                                <Button type="button" variant="ghost" block onClick={() => setRejecting(null)}>
                                    Batal
                                </Button>
                            </div>
                        </CardBody>
                    </Card>
                </div>
            )}
        </DashboardLayout>
    );
}
