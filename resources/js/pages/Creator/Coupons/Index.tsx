import { router } from '@inertiajs/react';
import { Pencil, Plus, Ticket, Trash2 } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Badge, Button, ButtonLink, EmptyState } from '@/components/ui';
import { formatDate, formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Coupon {
    id: number;
    code: string;
    type: string;
    value: number;
    min_order_amount: number;
    usage_limit: number | null;
    used_count: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    is_live: boolean;
}

export default function CouponsIndex({ coupons }: { coupons: Paginated<Coupon> }) {
    const columns: Column<Coupon>[] = [
        {
            key: 'code',
            header: 'Kode',
            render: (row) => (
                <span>
                    <span className="block font-mono font-bold">{row.code}</span>
                    <span className="block text-xs text-muted">
                        {row.type === 'percentage' ? `${row.value}% off` : `${formatIDR(row.value)} off`}
                    </span>
                </span>
            ),
        },
        {
            key: 'min',
            header: 'Min. belanja',
            align: 'right',
            mobile: false,
            render: (row) => (
                <span className="text-sm text-muted">
                    {row.min_order_amount > 0 ? formatIDR(row.min_order_amount) : '—'}
                </span>
            ),
        },
        {
            key: 'usage',
            header: 'Terpakai',
            align: 'right',
            render: (row) => (
                <span className="font-semibold">
                    {formatNumber(row.used_count)}
                    {row.usage_limit !== null && ` / ${formatNumber(row.usage_limit)}`}
                </span>
            ),
        },
        {
            key: 'period',
            header: 'Periode',
            mobile: false,
            render: (row) => (
                <span className="text-sm text-muted">
                    {row.starts_at || row.ends_at
                        ? `${formatDate(row.starts_at)} – ${formatDate(row.ends_at)}`
                        : 'Tanpa batas waktu'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) =>
                row.is_live ? (
                    <Badge tone="success">Aktif</Badge>
                ) : row.is_active ? (
                    <Badge tone="warning">Di luar periode</Badge>
                ) : (
                    <Badge>Nonaktif</Badge>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => (
                <span className="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Edit ${row.code}`}
                        onClick={() => router.visit(`/dashboard/kupon/${row.id}/edit`)}
                    >
                        <Pencil className="size-4" />
                    </Button>

                    <ConfirmButton
                        title={`Hapus kupon ${row.code}?`}
                        message="Kupon ini nggak bisa dipakai lagi di checkout."
                        confirmLabel="Ya, hapus"
                        onConfirm={() => router.delete(`/dashboard/kupon/${row.id}`)}
                    >
                        <Button variant="ghost" size="icon" aria-label={`Hapus ${row.code}`}>
                            <Trash2 className="size-4 text-[var(--danger)]" />
                        </Button>
                    </ConfirmButton>
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Kupon" area="creator">
            <PageHeader
                title="Kupon"
                description="Bikin kode diskon buat naikin konversi."
                actions={
                    <ButtonLink href="/dashboard/kupon/create" variant="gradient">
                        <Plus className="size-4" />
                        Buat Kupon
                    </ButtonLink>
                }
            />

            <DataList
                rows={coupons.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Ticket className="size-6" />}
                        title="Belum ada kupon"
                        description="Kupon bisa bikin pembeli ragu jadi checkout."
                        action={
                            <ButtonLink href="/dashboard/kupon/create" variant="gradient">
                                <Plus className="size-4" />
                                Buat Kupon
                            </ButtonLink>
                        }
                    />
                }
            />

            <Pagination meta={coupons} />
        </DashboardLayout>
    );
}
