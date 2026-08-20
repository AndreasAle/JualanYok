import { Link, router } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatCard, StatusBadge, type Column } from '@/components/shared';
import { EmptyState, Select } from '@/components/ui';
import { formatDate, formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface OrderRow {
    number: string;
    customer_name: string;
    customer_email: string;
    grand_total: number;
    seller_net: number;
    status: string;
    status_label: string;
    fulfillment_status: string;
    fulfillment_label: string;
    items_count: number;
    created_at: string;
    created_human: string;
}

export default function OrdersIndex({
    orders,
    filters,
    summary,
}: {
    orders: Paginated<OrderRow>;
    filters: { q?: string; status?: string };
    summary: { total: number; awaiting_payment: number; to_ship: number };
}) {
    const columns: Column<OrderRow>[] = [
        {
            key: 'number',
            header: 'Pesanan',
            render: (row) => (
                <Link href={`/dashboard/pesanan/${row.number}`} className="group block">
                    <span className="block font-mono text-sm font-semibold group-hover:text-[var(--primary)]">
                        {row.number}
                    </span>
                    <span className="block text-xs text-muted">{row.created_human}</span>
                </Link>
            ),
        },
        {
            key: 'customer',
            header: 'Pembeli',
            render: (row) => (
                <span>
                    <span className="block text-sm font-medium">{row.customer_name}</span>
                    <span className="block text-xs text-muted">{row.customer_email}</span>
                </span>
            ),
        },
        {
            key: 'items',
            header: 'Item',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{row.items_count}</span>,
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{formatIDR(row.grand_total)}</span>
                    <span className="block text-xs text-muted">Bersih {formatIDR(row.seller_net)}</span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span className="flex flex-col items-start gap-1">
                    <StatusBadge status={row.status} label={row.status_label} />
                    <StatusBadge status={row.fulfillment_status} label={row.fulfillment_label} />
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Pesanan" area="creator">
            <PageHeader title="Pesanan" description="Semua pesanan yang masuk ke tokomu." />

            <div className="mb-5 grid gap-3 sm:grid-cols-3">
                <StatCard label="Pesanan dibayar" value={formatNumber(summary.total)} />
                <StatCard label="Menunggu pembayaran" value={formatNumber(summary.awaiting_payment)} />
                <StatCard label="Perlu dikirim" value={formatNumber(summary.to_ship)} />
            </div>

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/dashboard/pesanan"
                    value={filters.q}
                    placeholder="Cari nomor, nama, atau email..."
                    extra={filters}
                />

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/dashboard/pesanan',
                            { ...filters, status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    <option value="PENDING_PAYMENT">Menunggu pembayaran</option>
                    <option value="PROCESSING">Diproses</option>
                    <option value="COMPLETED">Selesai</option>
                    <option value="REFUND_REQUESTED">Refund diajukan</option>
                    <option value="REFUNDED">Direfund</option>
                    <option value="EXPIRED">Kedaluwarsa</option>
                </Select>
            </div>

            <DataList
                rows={orders.data}
                columns={columns}
                rowKey={(row) => row.number}
                rowHref={(row) => `/dashboard/pesanan/${row.number}`}
                empty={
                    <EmptyState
                        icon={<ShoppingBag className="size-6" />}
                        title={filters.q ? 'Nggak ada pesanan yang cocok' : 'Belum ada pesanan'}
                        description={
                            filters.q
                                ? 'Coba kata kunci lain.'
                                : 'Bagikan link tokomu biar mulai ada yang beli.'
                        }
                    />
                }
            />

            <Pagination meta={orders} />
        </DashboardLayout>
    );
}
