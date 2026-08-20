import { Link, router } from '@inertiajs/react';
import { Download, Users } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, type Column } from '@/components/shared';
import { Badge, EmptyState, Switch } from '@/components/ui';
import { formatDate, formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    orders_count: number;
    lifetime_value: number;
    marketing_consent: boolean;
    source: string | null;
    last_order_at: string | null;
}

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<Customer>;
    filters: { q?: string; consent_only?: boolean };
}) {
    const columns: Column<Customer>[] = [
        {
            key: 'name',
            header: 'Pelanggan',
            render: (row) => (
                <Link href={`/dashboard/pelanggan/${row.id}`} className="group block">
                    <span className="block font-semibold group-hover:text-[var(--primary)]">{row.name}</span>
                    <span className="block text-xs text-muted">{row.email}</span>
                </Link>
            ),
        },
        {
            key: 'orders',
            header: 'Order',
            align: 'right',
            render: (row) => <span className="font-semibold">{formatNumber(row.orders_count)}</span>,
        },
        {
            key: 'ltv',
            header: 'Total belanja',
            align: 'right',
            render: (row) => <span className="font-semibold">{formatIDR(row.lifetime_value)}</span>,
        },
        {
            key: 'consent',
            header: 'Marketing',
            mobile: false,
            render: (row) =>
                row.marketing_consent ? <Badge tone="success">Boleh dikirimi</Badge> : <Badge>Tidak</Badge>,
        },
        {
            key: 'last',
            header: 'Order terakhir',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{formatDate(row.last_order_at)}</span>,
        },
    ];

    return (
        <DashboardLayout title="Pelanggan" area="creator">
            <PageHeader
                title="Pelanggan"
                description="Orang-orang yang pernah beli di tokomu."
                actions={
                    <a
                        href="/dashboard/pelanggan/export"
                        className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] border border-line px-4 text-sm font-semibold hover:bg-surface-2"
                    >
                        <Download className="size-4" />
                        Export CSV
                    </a>
                }
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    routeName="/dashboard/pelanggan"
                    value={filters.q}
                    placeholder="Cari nama atau email..."
                    extra={{ consent_only: filters.consent_only ? '1' : undefined }}
                />

                <Switch
                    checked={!!filters.consent_only}
                    onChange={(v) =>
                        router.get(
                            '/dashboard/pelanggan',
                            { ...filters, consent_only: v ? 1 : undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    label="Hanya yang kasih consent"
                />
            </div>

            <DataList
                rows={customers.data}
                columns={columns}
                rowKey={(row) => row.id}
                rowHref={(row) => `/dashboard/pelanggan/${row.id}`}
                empty={
                    <EmptyState
                        icon={<Users className="size-6" />}
                        title="Belum ada pelanggan"
                        description="Data pelanggan muncul otomatis setelah ada pembelian pertama."
                    />
                }
            />

            <Pagination meta={customers} />
        </DashboardLayout>
    );
}
