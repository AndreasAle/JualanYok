import { router } from '@inertiajs/react';
import { PieChart } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, StatCard, type Column } from '@/components/shared';
import { Alert, Badge, EmptyState, Select } from '@/components/ui';
import { cn, formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface EntryRow {
    id: number;
    user: string | null;
    username: string | null;
    type: string;
    type_label: string;
    bucket: string;
    amount: number;
    balance_after: number;
    description: string | null;
    reference: string | null;
    created_at: string | null;
}

export default function AdminLedger({
    entries,
    filters,
    types,
    totals,
}: {
    entries: Paginated<EntryRow>;
    filters: { type?: string; bucket?: string; user_id?: string };
    types: { value: string; label: string }[];
    totals: { pending: number; available: number; held: number; withdrawn: number };
}) {
    const setFilter = (key: string, value: string) => {
        router.get('/admin/ledger', { ...filters, [key]: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: Column<EntryRow>[] = [
        {
            key: 'id',
            header: 'ID',
            mobile: false,
            render: (row) => <span className="font-mono text-xs text-muted">#{row.id}</span>,
        },
        {
            key: 'user',
            header: 'Pemilik dompet',
            render: (row) => (
                <span>
                    <span className="block text-sm font-medium">{row.user ?? 'Platform'}</span>
                    {row.username && <span className="block text-xs text-muted">@{row.username}</span>}
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Jenis',
            render: (row) => (
                <span>
                    <Badge>{row.type_label}</Badge>
                    {row.description && (
                        <span className="mt-1 block truncate text-xs text-muted">{row.description}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'bucket',
            header: 'Kantong',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{row.bucket}</span>,
        },
        {
            key: 'amount',
            header: 'Nominal',
            align: 'right',
            render: (row) => (
                <span
                    className={cn(
                        'font-bold',
                        row.amount >= 0 ? 'text-[var(--success)]' : 'text-[var(--danger)]',
                    )}
                >
                    {row.amount >= 0 ? '+' : '−'}
                    {formatIDR(Math.abs(row.amount))}
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Saldo akhir',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{formatIDR(row.balance_after)}</span>,
        },
        {
            key: 'created',
            header: 'Waktu',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-xs text-muted">{formatDate(row.created_at, true)}</span>,
        },
    ];

    return (
        <DashboardLayout title="Ledger" area="admin">
            <PageHeader
                title="Ledger"
                description="Catatan permanen setiap pergerakan dana. Baca saja — entri nggak bisa diubah atau dihapus."
            />

            <div className="mb-4">
                <Alert tone="info">
                    Ledger bersifat append-only. Koreksi selalu berupa entri baru dengan tanda berlawanan, bukan
                    perubahan pada entri lama.
                </Alert>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total tertahan" value={formatIDR(totals.pending)} />
                <StatCard label="Total tersedia" value={formatIDR(totals.available)} tone="brand" />
                <StatCard label="Total dibekukan" value={formatIDR(totals.held)} />
                <StatCard label="Total ditarik" value={formatIDR(totals.withdrawn)} />
            </div>

            <div className="my-4 flex flex-col gap-2 sm:flex-row">
                <Select
                    value={filters.type ?? ''}
                    onChange={(e) => setFilter('type', e.target.value)}
                    aria-label="Filter jenis"
                    className="sm:w-60"
                >
                    <option value="">Semua jenis</option>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </Select>

                <Select
                    value={filters.bucket ?? ''}
                    onChange={(e) => setFilter('bucket', e.target.value)}
                    aria-label="Filter kantong"
                    className="sm:w-52"
                >
                    <option value="">Semua kantong</option>
                    <option value="PENDING">Tertahan</option>
                    <option value="AVAILABLE">Tersedia</option>
                    <option value="HELD">Dibekukan</option>
                    <option value="WITHDRAWN">Sudah ditarik</option>
                </Select>
            </div>

            <DataList
                rows={entries.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<PieChart className="size-6" />}
                        title="Belum ada entri ledger"
                        description="Entri tercatat otomatis saat ada transaksi."
                    />
                }
            />

            <Pagination meta={entries} />
        </DashboardLayout>
    );
}
