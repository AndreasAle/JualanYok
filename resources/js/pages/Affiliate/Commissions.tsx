import { router } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, StatCard, StatusBadge, type Column } from '@/components/shared';
import { EmptyState, Select } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface CommissionRow {
    id: number;
    order_number: string;
    store: string;
    base_amount: number;
    amount: number;
    status: string;
    status_label: string;
    available_at: string | null;
    created_at: string;
}

export default function Commissions({
    commissions,
    filters,
    summary,
}: {
    commissions: Paginated<CommissionRow>;
    filters: { status?: string };
    summary: { pending: number; approved: number; paid: number; revenue: number };
}) {
    const columns: Column<CommissionRow>[] = [
        {
            key: 'order',
            header: 'Pesanan',
            render: (row) => (
                <span>
                    <span className="block font-mono text-sm font-semibold">{row.order_number}</span>
                    <span className="block text-xs text-muted">
                        {row.store} · {formatDate(row.created_at)}
                    </span>
                </span>
            ),
        },
        {
            key: 'base',
            header: 'Nilai pesanan',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{formatIDR(row.base_amount)}</span>,
        },
        {
            key: 'amount',
            header: 'Komisi',
            align: 'right',
            render: (row) => (
                <span className="font-bold text-[var(--success)]">+{formatIDR(row.amount)}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span>
                    <StatusBadge status={row.status} label={row.status_label} />
                    {row.status === 'PENDING' && row.available_at && (
                        <span className="mt-1 block text-xs text-muted">Cair {formatDate(row.available_at)}</span>
                    )}
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Komisi" area="affiliate">
            <PageHeader title="Komisi" description="Semua komisi dari penjualan lewat link kamu." />

            <div className="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Menunggu" value={formatIDR(summary.pending)} hint="masa refund" />
                <StatCard label="Disetujui" value={formatIDR(summary.approved)} hint="siap ditarik" tone="brand" />
                <StatCard label="Sudah dibayar" value={formatIDR(summary.paid)} />
                <StatCard label="Omzet dihasilkan" value={formatIDR(summary.revenue)} />
            </div>

            <div className="mb-4">
                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/affiliate/komisi',
                            { status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status komisi"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    <option value="PENDING">Menunggu</option>
                    <option value="APPROVED">Disetujui</option>
                    <option value="PAID">Dibayar</option>
                    <option value="REVERSED">Dibatalkan</option>
                </Select>
            </div>

            <DataList
                rows={commissions.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Wallet className="size-6" />}
                        title="Belum ada komisi"
                        description="Bagikan link affiliate kamu biar mulai ada yang beli."
                    />
                }
            />

            <Pagination meta={commissions} />
        </DashboardLayout>
    );
}
