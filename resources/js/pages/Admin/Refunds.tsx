import { router, useForm } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Alert, Badge, Button, Card, EmptyState, Field, Select, Textarea } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface RefundRow {
    id: number;
    order_number: string;
    store: string;
    amount: number;
    order_total: number;
    status: string;
    reason: string | null;
    admin_note: string | null;
    requested_by: string | null;
    created_at: string;
}

export default function AdminRefunds({
    refunds,
    filters,
    canProcess,
}: {
    refunds: Paginated<RefundRow>;
    filters: { status?: string };
    canProcess: boolean;
}) {
    const [acting, setActing] = useState<{ row: RefundRow; mode: 'approve' | 'reject' } | null>(null);

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
                <Badge
                    tone={
                        row.status === 'COMPLETED'
                            ? 'success'
                            : row.status === 'REJECTED'
                              ? 'danger'
                              : 'warning'
                    }
                >
                    {row.status}
                </Badge>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) =>
                canProcess && row.status === 'REQUESTED' ? (
                    <span className="flex justify-end gap-1">
                        <Button size="sm" variant="success" onClick={() => setActing({ row, mode: 'approve' })}>
                            Setujui
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setActing({ row, mode: 'reject' })}>
                            Tolak
                        </Button>
                    </span>
                ) : null,
        },
    ];

    return (
        <DashboardLayout title="Refund" area="admin">
            <PageHeader
                title="Refund"
                description="Menyetujui refund otomatis menyesuaikan saldo penjual dan membatalkan komisi affiliate."
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
                    <option value="REQUESTED">Menunggu</option>
                    <option value="COMPLETED">Disetujui</option>
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
                        title="Nggak ada pengajuan refund"
                        description="Pengajuan dari pembeli atau penjual muncul di sini."
                    />
                }
            />

            <Pagination meta={refunds} />

            {acting && <RefundDialog action={acting} onClose={() => setActing(null)} />}
        </DashboardLayout>
    );
}

function RefundDialog({
    action,
    onClose,
}: {
    action: { row: RefundRow; mode: 'approve' | 'reject' };
    onClose: () => void;
}) {
    const { row, mode } = action;
    const form = useForm({ note: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.post(
            mode === 'approve' ? `/admin/refund/${row.id}/setujui` : `/admin/refund/${row.id}/tolak`,
            { preserveScroll: true, onSuccess: onClose },
        );
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="w-full max-w-md animate-rise p-6">
                <h2 className="text-lg font-bold">
                    {mode === 'approve' ? 'Setujui refund' : 'Tolak refund'}
                </h2>
                <p className="mt-1 text-sm text-muted">
                    {row.order_number} · {formatIDR(row.amount)}
                </p>

                {mode === 'approve' && (
                    <div className="mt-3">
                        <Alert tone="warning">
                            Saldo penjual dipotong proporsional dan komisi affiliate untuk pesanan ini dibatalkan.
                            Kalau refund penuh, akses produk digital dicabut.
                        </Alert>
                    </div>
                )}

                <form onSubmit={submit} className="mt-4 space-y-3">
                    <Field
                        label={mode === 'approve' ? 'Catatan (opsional)' : 'Alasan penolakan'}
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
                        <Button
                            type="submit"
                            variant={mode === 'approve' ? 'success' : 'danger'}
                            loading={form.processing}
                        >
                            {mode === 'approve' ? 'Setujui Refund' : 'Tolak'}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
