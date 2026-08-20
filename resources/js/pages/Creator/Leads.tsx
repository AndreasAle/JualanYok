import { Download, Gift } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, type Column } from '@/components/shared';
import { EmptyState } from '@/components/ui';
import { formatDate, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Lead {
    id: number;
    name: string | null;
    email: string | null;
    phone: string | null;
    source: string | null;
    created_at: string;
    created_human: string;
}

export default function Leads({
    leads,
    filters,
    total,
}: {
    leads: Paginated<Lead>;
    filters: { q?: string };
    total: number;
}) {
    const columns: Column<Lead>[] = [
        {
            key: 'name',
            header: 'Nama',
            render: (row) => <span className="font-semibold">{row.name ?? '—'}</span>,
        },
        {
            key: 'email',
            header: 'Email',
            render: (row) => <span className="text-sm text-muted">{row.email ?? '—'}</span>,
        },
        {
            key: 'phone',
            header: 'WhatsApp',
            render: (row) => <span className="text-sm text-muted">{row.phone ?? '—'}</span>,
        },
        {
            key: 'source',
            header: 'Sumber',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{row.source ?? '—'}</span>,
        },
        {
            key: 'created',
            header: 'Masuk',
            align: 'right',
            render: (row) => <span className="text-sm text-muted">{row.created_human}</span>,
        },
    ];

    return (
        <DashboardLayout title="Leads" area="creator">
            <PageHeader
                title="Leads"
                description={`${formatNumber(total)} orang sudah ninggalin kontaknya. Semua sudah kasih consent.`}
                actions={
                    <a
                        href="/dashboard/leads/export"
                        className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] border border-line px-4 text-sm font-semibold hover:bg-surface-2"
                    >
                        <Download className="size-4" />
                        Export CSV
                    </a>
                }
            />

            <div className="mb-4">
                <SearchInput routeName="/dashboard/leads" value={filters.q} placeholder="Cari nama, email, atau nomor..." />
            </div>

            <DataList
                rows={leads.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Gift className="size-6" />}
                        title="Belum ada leads"
                        description="Tambah block Form Leads di tokomu buat mulai mengumpulkan kontak."
                    />
                }
            />

            <Pagination meta={leads} />
        </DashboardLayout>
    );
}
