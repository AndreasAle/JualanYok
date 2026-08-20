import { router, useForm } from '@inertiajs/react';
import { ExternalLink, Store } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, DataList, PageHeader, Pagination, SearchInput, type Column } from '@/components/shared';
import { Badge, Button, Card, EmptyState, Field, Select, Textarea } from '@/components/ui';
import { formatDate, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface StoreRow {
    id: number;
    username: string;
    name: string;
    owner: string;
    owner_email: string;
    status: string;
    suspension_reason: string | null;
    is_published: boolean;
    products_count: number;
    orders_count: number;
    view_count: number;
    public_url: string;
    created_at: string;
}

export default function AdminStores({
    stores,
    filters,
}: {
    stores: Paginated<StoreRow>;
    filters: { q?: string; status?: string };
}) {
    const [suspending, setSuspending] = useState<StoreRow | null>(null);

    const columns: Column<StoreRow>[] = [
        {
            key: 'name',
            header: 'Toko',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{row.name}</span>
                    <span className="block text-xs text-muted">/{row.username}</span>
                </span>
            ),
        },
        {
            key: 'owner',
            header: 'Pemilik',
            render: (row) => (
                <span>
                    <span className="block text-sm">{row.owner}</span>
                    <span className="block text-xs text-muted">{row.owner_email}</span>
                </span>
            ),
        },
        {
            key: 'stats',
            header: 'Aktivitas',
            align: 'right',
            mobile: false,
            render: (row) => (
                <span className="text-xs text-muted">
                    {formatNumber(row.products_count)} produk · {formatNumber(row.orders_count)} order ·{' '}
                    {formatNumber(row.view_count)} view
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span className="flex flex-wrap gap-1">
                    {row.status === 'active' ? (
                        row.is_published ? (
                            <Badge tone="success">Live</Badge>
                        ) : (
                            <Badge>Draft</Badge>
                        )
                    ) : (
                        <Badge tone="danger">Ditangguhkan</Badge>
                    )}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => (
                <span className="flex justify-end gap-1">
                    <a
                        href={row.public_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="grid size-10 place-items-center rounded-[var(--radius-field)] text-muted hover:bg-surface-2 hover:text-fg"
                        aria-label={`Buka ${row.name}`}
                    >
                        <ExternalLink className="size-4" />
                    </a>

                    {row.status === 'active' ? (
                        <Button size="sm" variant="ghost" onClick={() => setSuspending(row)}>
                            Tangguhkan
                        </Button>
                    ) : (
                        <ConfirmButton
                            title={`Aktifkan kembali ${row.name}?`}
                            message="Pemilik bisa mempublikasikan tokonya lagi."
                            confirmLabel="Ya, aktifkan"
                            variant="primary"
                            onConfirm={() => router.post(`/admin/toko/${row.id}/aktifkan`)}
                        >
                            <Button size="sm" variant="outline">
                                Aktifkan
                            </Button>
                        </ConfirmButton>
                    )}
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Toko" area="admin">
            <PageHeader title="Toko" description="Semua storefront di platform." />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/toko"
                    value={filters.q}
                    placeholder="Cari nama atau username toko..."
                    extra={filters}
                />

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/admin/toko',
                            { ...filters, status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status"
                    className="sm:w-48"
                >
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="suspended">Ditangguhkan</option>
                </Select>
            </div>

            <DataList
                rows={stores.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Store className="size-6" />}
                        title="Nggak ada toko yang cocok"
                        description="Coba ubah kata kunci atau filternya."
                    />
                }
            />

            <Pagination meta={stores} />

            {suspending && <SuspendDialog store={suspending} onClose={() => setSuspending(null)} />}
        </DashboardLayout>
    );
}

function SuspendDialog({ store, onClose }: { store: StoreRow; onClose: () => void }) {
    const form = useForm({ reason: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/toko/${store.id}/suspend`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="w-full max-w-md animate-rise p-6">
                <h2 className="text-lg font-bold">Tangguhkan {store.name}?</h2>
                <p className="mt-1 text-sm text-muted">
                    Toko langsung offline. Pesanan yang sudah ada dan akses pembeli tetap aman.
                </p>

                <form onSubmit={submit} className="mt-4 space-y-3">
                    <Field label="Alasan" required error={form.errors.reason} htmlFor="reason">
                        <Textarea
                            id="reason"
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            required
                        />
                    </Field>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Batal
                        </Button>
                        <Button type="submit" variant="danger" loading={form.processing}>
                            Tangguhkan
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
