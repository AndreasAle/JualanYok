import { router, useForm } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, Select, Textarea } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface RefundRow {
    id: number;
    order_number: string;
    store: string;
    amount: number;
    order_total: number;
    status: string;
    execution_mode: string | null;
    payment_provider: string | null;
    transfer_reference: string | null;
    reason: string | null;
    admin_note: string | null;
    requested_by: string | null;
    created_at: string;
}

type RefundAction = { row: RefundRow; mode: 'approve' | 'complete' | 'reject' };

const statusLabel: Record<string, string> = {
    REQUESTED: 'Menunggu keputusan',
    APPROVED: 'Menunggu transfer',
    COMPLETED: 'Dana dikembalikan',
    REJECTED: 'Ditolak',
};

export default function AdminRefunds({
    refunds,
    filters,
    canProcess,
}: {
    refunds: Paginated<RefundRow>;
    filters: { status?: string };
    canProcess: boolean;
}) {
    const [acting, setActing] = useState<RefundAction | null>(null);

    const columns: Column<RefundRow>[] = [
        {
            key: 'order',
            header: 'Pesanan',
            render: (row) => (
                <span>
                    <span className="block font-mono text-sm font-semibold">{row.order_number}</span>
                    <span className="block text-xs text-muted">
                        {row.store} · {formatDate(row.created_at, true)}
                    </span>
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Nominal',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-bold">{formatIDR(row.amount)}</span>
                    <span className="block text-xs text-muted">dari {formatIDR(row.order_total)}</span>
                </span>
            ),
        },
        {
            key: 'reason',
            header: 'Alasan',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{row.reason ?? '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span>
                    <Badge
                        tone={
                            row.status === 'COMPLETED'
                                ? 'success'
                                : row.status === 'REJECTED'
                                  ? 'danger'
                                  : 'warning'
                        }
                    >
                        {statusLabel[row.status] ?? row.status}
                    </Badge>
                    {row.status === 'APPROVED' && (
                        <span className="mt-1 block text-xs text-muted">{row.payment_provider ?? 'Manual'}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => {
                if (!canProcess) return null;
                if (row.status === 'REQUESTED') {
                    return (
                        <span className="flex justify-end gap-1">
                            <Button size="sm" variant="success" onClick={() => setActing({ row, mode: 'approve' })}>
                                Terima
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setActing({ row, mode: 'reject' })}>
                                Tolak
                            </Button>
                        </span>
                    );
                }
                if (row.status === 'APPROVED') {
                    return (
                        <Button size="sm" variant="success" onClick={() => setActing({ row, mode: 'complete' })}>
                            Konfirmasi terkirim
                        </Button>
                    );
                }
                return null;
            },
        },
    ];

    return (
        <DashboardLayout title="Refund" area="admin">
            <PageHeader
                title="Refund"
                description="Dana hanya dibukukan sebagai refund setelah gateway mengonfirmasi atau finance memasukkan referensi transfer."
            />

            {!canProcess && (
                <div className="mb-4">
                    <Alert tone="info">Hanya finance admin yang bisa memproses refund.</Alert>
                </div>
            )}

            <div className="mb-4">
                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/admin/refund',
                            { status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    <option value="REQUESTED">Menunggu keputusan</option>
                    <option value="APPROVED">Menunggu transfer</option>
                    <option value="COMPLETED">Dana dikembalikan</option>
                    <option value="REJECTED">Ditolak</option>
                </Select>
            </div>

            <DataList
                rows={refunds.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Receipt className="size-6" />}
                        title="Belum ada pengajuan refund"
                        description="Pengajuan dari pembeli atau penjual akan muncul di sini."
                    />
                }
            />

            <Pagination meta={refunds} />
            {acting && <RefundDialog action={acting} onClose={() => setActing(null)} />}
        </DashboardLayout>
    );
}

function RefundDialog({ action, onClose }: { action: RefundAction; onClose: () => void }) {
    const { row, mode } = action;
    const form = useForm({ note: '', transfer_reference: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const endpoint =
            mode === 'approve'
                ? `/admin/refund/${row.id}/setujui`
                : mode === 'complete'
                  ? `/admin/refund/${row.id}/selesaikan`
                  : `/admin/refund/${row.id}/tolak`;
        form.post(endpoint, { preserveScroll: true, onSuccess: onClose });
    };

    const title =
        mode === 'approve'
            ? 'Terima pengajuan refund'
            : mode === 'complete'
              ? 'Konfirmasi dana terkirim'
              : 'Tolak refund';

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="w-full max-w-md animate-rise p-6">
                <h2 className="text-lg font-bold">{title}</h2>
                <p className="mt-1 text-sm text-muted">
                    {row.order_number} · {formatIDR(row.amount)}
                </p>

                {mode === 'approve' && (
                    <div className="mt-3">
                        <Alert tone="warning">
                            Penerimaan belum selalu berarti dana sudah kembali. Jika provider tidak mendukung refund
                            otomatis, selesaikan transfer lalu masukkan nomor referensinya.
                        </Alert>
                    </div>
                )}
                {mode === 'complete' && (
                    <div className="mt-3">
                        <Alert tone="warning">
                            Pastikan dana benar-benar sudah terkirim. Konfirmasi ini akan menyesuaikan saldo seller,
                            komisi affiliate, akses produk, dan jurnal secara permanen.
                        </Alert>
                    </div>
                )}

                <form onSubmit={submit} className="mt-4 space-y-3">
                    {mode === 'complete' && (
                        <Field
                            label="Nomor referensi transfer"
                            required
                            error={form.errors.transfer_reference}
                            htmlFor="transfer_reference"
                            hint="Salin nomor transaksi dari iPaymu, bank, atau provider pembayaran."
                        >
                            <Input
                                id="transfer_reference"
                                value={form.data.transfer_reference}
                                onChange={(e) => form.setData('transfer_reference', e.target.value)}
                                placeholder="Contoh: IPAYMU-RF-20260827-001"
                                required
                            />
                        </Field>
                    )}

                    <Field
                        label={mode === 'reject' ? 'Alasan penolakan' : 'Catatan (opsional)'}
                        required={mode === 'reject'}
                        error={form.errors.note}
                        htmlFor="note"
                    >
                        <Textarea
                            id="note"
                            rows={3}
                            value={form.data.note}
                            onChange={(e) => form.setData('note', e.target.value)}
                            required={mode === 'reject'}
                        />
                    </Field>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Batal
                        </Button>
                        <Button type="submit" variant={mode === 'reject' ? 'danger' : 'success'} loading={form.processing}>
                            {mode === 'approve'
                                ? 'Terima pengajuan'
                                : mode === 'complete'
                                  ? 'Ya, dana sudah terkirim'
                                  : 'Tolak'}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
