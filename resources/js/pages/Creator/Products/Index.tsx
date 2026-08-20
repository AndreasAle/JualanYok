import { Link, router } from '@inertiajs/react';
import { Copy, ExternalLink, Package, Plus } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatusBadge, type Column } from '@/components/shared';
import { Badge, Button, ButtonLink, EmptyState, Select } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface ProductRow {
    id: number;
    name: string;
    slug: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    price: number;
    compare_at_price: number | null;
    thumbnail_url: string | null;
    sales_count: number;
    view_count: number;
    affiliate_enabled: boolean;
    public_url: string;
}

export default function ProductsIndex({
    products,
    filters,
    types,
    limits,
}: {
    products: Paginated<ProductRow>;
    filters: { q?: string; type?: string; status?: string };
    types: { value: string; label: string }[];
    limits: { limit: number | null; used: number };
}) {
    const atLimit = limits.limit !== null && limits.used >= limits.limit;

    const filter = (key: string, value: string) => {
        router.get('/dashboard/produk', { ...filters, [key]: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: Column<ProductRow>[] = [
        {
            key: 'name',
            header: 'Produk',
            render: (row) => (
                <Link href={`/dashboard/produk/${row.id}/edit`} className="flex items-center gap-3 group">
                    {row.thumbnail_url ? (
                        <img src={row.thumbnail_url} alt="" className="size-11 shrink-0 rounded-xl object-cover" />
                    ) : (
                        <span className="grid size-11 shrink-0 place-items-center rounded-xl gradient-brand text-white">
                            <Package className="size-5" />
                        </span>
                    )}
                    <span className="min-w-0">
                        <span className="block truncate font-semibold group-hover:text-[var(--primary)]">
                            {row.name}
                        </span>
                        <span className="block text-xs text-muted">{row.type_label}</span>
                    </span>
                </Link>
            ),
        },
        {
            key: 'price',
            header: 'Harga',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{formatIDR(row.price)}</span>
                    {row.compare_at_price && (
                        <span className="block text-xs text-muted line-through">
                            {formatIDR(row.compare_at_price)}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span className="flex flex-wrap gap-1">
                    <StatusBadge status={row.status} label={row.status_label} />
                    {row.affiliate_enabled && <Badge tone="info">Affiliate</Badge>}
                </span>
            ),
        },
        {
            key: 'sales',
            header: 'Terjual',
            align: 'right',
            render: (row) => <span className="font-semibold">{formatNumber(row.sales_count)}</span>,
        },
        {
            key: 'views',
            header: 'Dilihat',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{formatNumber(row.view_count)}</span>,
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
                        className="grid size-9 place-items-center rounded-[var(--radius-field)] text-muted hover:bg-surface-2 hover:text-fg"
                        aria-label={`Buka ${row.name}`}
                    >
                        <ExternalLink className="size-4" />
                    </a>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Duplikat ${row.name}`}
                        onClick={() => router.post(`/dashboard/produk/${row.id}/duplicate`)}
                    >
                        <Copy className="size-4" />
                    </Button>
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Produk" area="creator">
            <PageHeader
                title="Produk"
                description={
                    limits.limit === null
                        ? `${limits.used} produk`
                        : `${limits.used} dari ${limits.limit} produk terpakai`
                }
                actions={
                    <ButtonLink
                        href={atLimit ? '/dashboard/langganan' : '/dashboard/produk/create'}
                        variant="gradient"
                    >
                        <Plus className="size-4" />
                        {atLimit ? 'Upgrade buat Tambah' : 'Tambah Produk'}
                    </ButtonLink>
                }
            />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput routeName="/dashboard/produk" value={filters.q} placeholder="Cari produk..." extra={filters} />

                <Select
                    value={filters.type ?? ''}
                    onChange={(e) => filter('type', e.target.value)}
                    aria-label="Filter jenis"
                    className="sm:w-48"
                >
                    <option value="">Semua jenis</option>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </Select>

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) => filter('status', e.target.value)}
                    aria-label="Filter status"
                    className="sm:w-40"
                >
                    <option value="">Semua status</option>
                    <option value="ACTIVE">Aktif</option>
                    <option value="DRAFT">Draft</option>
                    <option value="ARCHIVED">Diarsipkan</option>
                </Select>
            </div>

            <DataList
                rows={products.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Package className="size-6" />}
                        title={filters.q ? 'Nggak ada produk yang cocok' : 'Belum ada produk'}
                        description={
                            filters.q
                                ? 'Coba kata kunci lain atau ubah filternya.'
                                : 'Tambah produk pertamamu biar tokonya bisa mulai jualan.'
                        }
                        action={
                            !filters.q && (
                                <ButtonLink href="/dashboard/produk/create" variant="gradient">
                                    <Plus className="size-4" />
                                    Tambah Produk
                                </ButtonLink>
                            )
                        }
                    />
                }
            />

            <Pagination meta={products} />
        </DashboardLayout>
    );
}
