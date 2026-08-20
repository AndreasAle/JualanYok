import { router } from '@inertiajs/react';
import { Boxes, Check, ExternalLink } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, Pagination, SearchInput } from '@/components/shared';
import { Badge, Button, Card, EmptyState, Select } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface MarketProduct {
    id: number;
    name: string;
    thumbnail_url: string | null;
    price: number;
    type_label: string;
    store: string;
    store_username: string;
    category: string | null;
    sales_count: number;
    commission_label: string;
    commission_amount: number;
    cookie_days: number | null;
    joined: boolean;
    public_url: string;
}

export default function Marketplace({
    products,
    filters,
    categories,
}: {
    products: Paginated<MarketProduct>;
    filters: { q?: string; category?: string; sort?: string };
    categories: { id: number; name: string }[];
}) {
    const setFilter = (key: string, value: string) => {
        router.get('/affiliate/marketplace', { ...filters, [key]: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    return (
        <DashboardLayout title="Marketplace Affiliate" area="affiliate">
            <PageHeader
                title="Marketplace Affiliate"
                description="Pilih produk orang lain buat kamu promosiin. Dapat komisi tiap ada yang beli."
            />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/affiliate/marketplace"
                    value={filters.q}
                    placeholder="Cari produk..."
                    extra={filters}
                />

                <Select
                    value={filters.category ?? ''}
                    onChange={(e) => setFilter('category', e.target.value)}
                    aria-label="Filter kategori"
                    className="sm:w-48"
                >
                    <option value="">Semua kategori</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                        </option>
                    ))}
                </Select>

                <Select
                    value={filters.sort ?? ''}
                    onChange={(e) => setFilter('sort', e.target.value)}
                    aria-label="Urutkan"
                    className="sm:w-48"
                >
                    <option value="">Terbaru</option>
                    <option value="popular">Paling laris</option>
                    <option value="commission">Komisi terbesar</option>
                </Select>
            </div>

            {products.data.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<Boxes className="size-6" />}
                        title="Belum ada produk affiliate"
                        description="Coba ubah filter, atau cek lagi nanti — creator baru terus bergabung."
                    />
                </Card>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {products.data.map((product) => (
                        <Card key={product.id} className="flex flex-col overflow-hidden">
                            {product.thumbnail_url ? (
                                <img src={product.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
                            ) : (
                                <div className="aspect-video w-full gradient-brand" />
                            )}

                            <div className="flex flex-1 flex-col p-4">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Badge>{product.type_label}</Badge>
                                    {product.category && <Badge>{product.category}</Badge>}
                                </div>

                                <p className="mt-2 font-bold leading-snug">{product.name}</p>
                                <p className="text-xs text-muted">oleh {product.store}</p>

                                <div className="mt-3 flex-1">
                                    <div className="flex items-baseline justify-between">
                                        <span className="text-lg font-extrabold">{formatIDR(product.price)}</span>
                                        <span className="text-xs text-muted">
                                            {formatNumber(product.sales_count)} terjual
                                        </span>
                                    </div>

                                    <div className="mt-2 rounded-[var(--radius-field)] bg-emerald-50 p-3 dark:bg-emerald-950/30">
                                        <p className="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            Komisi {product.commission_label}
                                        </p>
                                        <p className="text-lg font-extrabold text-emerald-700 dark:text-emerald-300">
                                            {formatIDR(product.commission_amount)}
                                        </p>
                                        {product.cookie_days && (
                                            <p className="text-[11px] text-emerald-700/80 dark:text-emerald-300/80">
                                                Tracking {product.cookie_days} hari
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="mt-4 flex gap-2">
                                    {product.joined ? (
                                        <Button variant="outline" block disabled>
                                            <Check className="size-4" />
                                            Sudah Gabung
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="gradient"
                                            block
                                            onClick={() =>
                                                router.post(
                                                    `/affiliate/marketplace/${product.id}/gabung`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Gabung Program
                                        </Button>
                                    )}

                                    <a
                                        href={product.public_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="grid size-11 shrink-0 place-items-center rounded-[var(--radius-field)] border border-line hover:bg-surface-2"
                                        aria-label={`Lihat ${product.name}`}
                                    >
                                        <ExternalLink className="size-4" />
                                    </a>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Pagination meta={products} />
        </DashboardLayout>
    );
}
