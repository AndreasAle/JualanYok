import { router } from '@inertiajs/react';
import { Check, Copy, Search, X } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, Pagination, StatusBadge } from '@/components/shared';
import { Alert, Badge, Button, Card, CardBody, EmptyState, Field, Input, Select, Textarea } from '@/components/ui';
import { formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface QrisPaymentRow {
    id: number;
    reference: string | null;
    order_number: string | null;
    store_name: string | null;
    customer_name: string | null;
    customer_email: string | null;
    base_amount: number;
    unique_suffix: number | null;
    amount: number;
    status: string;
    status_label: string;
    item_count: number;
    expires_at: string | null;
    created_at: string;
    paid_at: string | null;
}

export default function QrisPayments({
    payments,
    filters,
    statuses,
    counts,
}: {
    payments: Paginated<QrisPaymentRow>;
    filters: { status: string; q: string | null };
    statuses: { value: string; label: string }[];
    counts: { pending: number };
}) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [rejecting, setRejecting] = useState<QrisPaymentRow | null>(null);
    const [reason, setReason] = useState('');
    const [copied, setCopied] = useState<number | null>(null);

    const applyFilter = (patch: Record<string, string>) => {
        router.get('/admin/pembayaran-qris', { ...filters, q: query, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const copyAmount = async (row: QrisPaymentRow) => {
        await navigator.clipboard.writeText(String(row.amount));
        setCopied(row.id);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <DashboardLayout title="Pembayaran QRIS" area="admin">
            <PageHeader
                title="Pembayaran QRIS"
                description="Cocokkan dana masuk di dompet dengan pesanan di bawah, lalu konfirmasi."
            />

            <div className="mb-4">
                <Alert tone="warning" title="Konfirmasi memindahkan uang">
                    <span className="text-sm">
                        Begitu disetujui, stok dipotong, saldo penjual bertambah, komisi affiliate dihitung, dan
                        produk dikirim ke pembeli. Pastikan nominalnya <b>persis</b> sama dengan yang masuk — kalau
                        tidak ada yang cocok, jangan disetujui.
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
                            placeholder="Nominal, nomor pesanan, nama, atau email"
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

                <div className="pb-0.5">
                    <Badge tone="warning">{counts.pending} menunggu konfirmasi</Badge>
                </div>
            </div>

            {payments.data.length === 0 ? (
                <EmptyState
                    title="Belum ada pembayaran QRIS"
                    description="Pesanan yang dibayar lewat QRIS akan muncul di sini."
                />
            ) : (
                <div className="space-y-3">
                    {payments.data.map((row) => (
                        <Card key={row.id}>
                            <CardBody className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge status={row.status} label={row.status_label} />
                                        <span className="text-xs text-muted">{row.order_number}</span>
                                    </div>

                                    <p className="mt-2 text-lg font-extrabold tabular-nums">
                                        {formatIDR(row.amount)}
                                        {row.unique_suffix !== null && (
                                            <span className="ml-2 text-xs font-semibold text-muted">
                                                ({formatIDR(row.base_amount)} + {row.unique_suffix})
                                            </span>
                                        )}
                                    </p>

                                    <p className="mt-1 text-sm">
                                        <b>{row.store_name}</b> · {row.item_count} item · {row.customer_name}{' '}
                                        <span className="text-muted">({row.customer_email})</span>
                                    </p>

                                    <p className="mt-1 text-xs text-muted">
                                        Dibuat {row.created_at}
                                        {row.expires_at && ` · berlaku sampai ${row.expires_at}`}
                                        {row.paid_at && ` · lunas ${row.paid_at}`}
                                    </p>
                                </div>

                                <div className="flex shrink-0 flex-wrap gap-2">
                                    <Button type="button" variant="outline" onClick={() => copyAmount(row)}>
                                        {copied === row.id ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                                        {copied === row.id ? 'Tersalin' : 'Salin nominal'}
                                    </Button>

                                    {row.status === 'PENDING' && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="gradient"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/pembayaran-qris/${row.id}/setujui`,
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                <Check className="size-4" />
                                                Konfirmasi lunas
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
                                <h2 className="text-lg font-extrabold">Tolak pembayaran?</h2>
                                <p className="mt-1 text-sm text-muted">
                                    {rejecting.order_number} · {formatIDR(rejecting.amount)}
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
                                    onClick={() =>
                                        router.post(
                                            `/admin/pembayaran-qris/${rejecting.id}/tolak`,
                                            { reason },
                                            { preserveScroll: true, onSuccess: () => setRejecting(null) },
                                        )
                                    }
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
