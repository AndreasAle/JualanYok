import { ShoppingBag } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, StatusBadge, type Column } from '@/components/shared';
import { EmptyState } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface OrderRow {
    number: string;
    store: string;
    grand_total: number;
    status: string;
    status_label: string;
    items_count: number;
    created_at: string;
}

export default function MemberOrdersIndex({ orders }: { orders: Paginated<OrderRow> }) {
    const columns: Column<OrderRow>[] = [
        {
            key: 'number',
            header: 'Pesanan',
            render: (row) => (
                <span>
                    <span className="block font-mono text-sm font-semibold">{row.number}</span>
                    <span className="block text-xs text-muted">{formatDate(row.created_at, true)}</span>
                </span>
            ),
        },
        {
            key: 'store',
            header: 'Toko',
            render: (row) => <span className="text-sm font-medium">{row.store}</span>,
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
            render: (row) => <span className="font-semibold">{formatIDR(row.grand_total)}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <StatusBadge status={row.status} label={row.status_label} />,
        },
    ];

    return (
        <DashboardLayout title="Pembelian" area="member">
            <PageHeader title="Pembelian" description="Riwayat semua pesanan kamu." />

            <DataList
                rows={orders.data}
                columns={columns}
                rowKey={(row) => row.number}
                rowHref={(row) => `/member/pembelian/${row.number}`}
                empty={
                    <EmptyState
                        icon={<ShoppingBag className="size-6" />}
                        title="Belum ada pembelian"
                        description="Setelah kamu beli sesuatu, pesanannya muncul di sini."
                    />
                }
            />

            <Pagination meta={orders} />
        </DashboardLayout>
    );
}
