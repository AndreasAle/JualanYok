import { Link, router } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatusBadge, type Column } from '@/components/shared';
import { EmptyState, Select } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface OrderRow {
    number: string;
    store: string;
    store_username: string;
    customer_email: string;
    grand_total: number;
    platform_fee: number;
    status: string;
    status_label: string;
    created_at: string;
}

export default function AdminOrders({
    orders,
    filters,
    statuses,
}: {
    orders: Paginated<OrderRow>;
    filters: { q?: string; status?: string };
    statuses: { value: string; label: string }[];
}) {
    const columns: Column<OrderRow>[] = [
        {
            key: 'number',
            header: 'Pesanan',
            render: (row) => (
                <Link href={`/admin/pesanan/${row.number}`} className="group block">
                    <span className="block font-mono text-sm font-semibold group-hover:text-[var(--primary)]">
                        {row.number}
                    </span>
                    <span className="block text-xs text-muted">{formatDate(row.created_at, true)}</span>
                </Link>
            ),
        },
        {
            key: 'store',
            header: 'Toko',
            render: (row) => (
                <span>
                    <span className="block text-sm font-medium">{row.store}</span>
                    <span className="block text-xs text-muted">/{row.store_username}</span>
                </span>
            ),
        },
        {
            key: 'customer',
            header: 'Pembeli',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{row.customer_email}</span>,
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{formatIDR(row.grand_total)}</span>
                    <span className="block text-xs text-muted">Fee {formatIDR(row.platform_fee)}</span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <StatusBadge status={row.status} label={row.status_label} />,
        },
    ];

    return (
        <DashboardLayout title="Pesanan" area="admin">
            <PageHeader title="Pesanan" description="Semua transaksi lintas toko." />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/pesanan"
                    value={filters.q}
                    placeholder="Cari nomor atau email pembeli..."
                    extra={filters}
                />

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/admin/pesanan',
                            { ...filters, status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
            </div>

            <DataList
                rows={orders.data}
                columns={columns}
                rowKey={(row) => row.number}
                rowHref={(row) => `/admin/pesanan/${row.number}`}
                empty={
                    <EmptyState
                        icon={<ShoppingBag className="size-6" />}
                        title="Nggak ada pesanan yang cocok"
                        description="Coba ubah kata kunci atau filternya."
                    />
                }
            />

            <Pagination meta={orders} />
        </DashboardLayout>
    );
}
