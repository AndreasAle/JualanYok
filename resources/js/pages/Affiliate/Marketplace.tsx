import { Link, router } from '@inertiajs/react';
import { Boxes, Check, Info, Search, TrendingUp, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Pagination } from '@/components/shared';
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

const SORTS = [
    { key: '', label: 'Terbaru' },
    { key: 'popular', label: 'Paling laris' },
    { key: 'price', label: 'Harga tertinggi' },
];

/**
 * A product with no photo still has to be picked out of a grid.
 *
 * The name is hashed to a fixed hue so the same product keeps the same colour
 * on every visit — a placeholder that shuffles is worse than no placeholder,
 * because it stops being a way to recognise the thing.
 */
function hue(name: string): number {
    let total = 0;
    for (let i = 0; i < name.length; i += 1) {
        total = (total * 31 + name.charCodeAt(i)) % 360;
    }

    return total;
}

/**
 * The affiliate marketplace.
 *
 * This is the one screen in the workspace that is a shop rather than a tool.
 * Someone here is browsing for something to promote, and browsing is done with
 * the eyes: pictures at full width, the number that matters — what they earn —
 * printed on the card, and everything else small. It gets its own dark canvas
 * for that reason, deliberately unlike the pale, dense admin screens either
 * side of it.
 */
export default function Marketplace({
    products,
    filters,
    categories,
    hasStore,
}: {
    products: Paginated<MarketProduct>;
    filters: { q?: string; category?: string; sort?: string };
    categories: { id: number; name: string }[];
    hasStore: boolean;
}) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [hint, setHint] = useState(false);

    const go = (next: Record<string, string | undefined>) => {
        router.get('/affiliate/marketplace', { ...filters, ...next }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    // Search as you type, but only after typing stops — a request per
    // keystroke would reorder the grid under the reader's eyes.
    useEffect(() => {
        if ((filters.q ?? '') === query) {
            return;
        }

        const timer = window.setTimeout(() => go({ q: query || undefined }), 350);

        return () => window.clearTimeout(timer);
    }, [query]);

    const activeSort = filters.sort ?? '';
    const activeCategory = filters.category ?? '';

    return (
        <DashboardLayout title="Marketplace Affiliate" area="affiliate">
            {/* Breaks out of the workspace padding to take the whole canvas. */}
            <div className="-mx-4 -mt-6 min-h-[calc(100vh-3.5rem)] bg-[#0e0d14] text-white sm:-mx-6 lg:-mx-8">
                <div className="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-[-.02em]">Cari produk buat dipromosiin</h1>
                            <p className="mt-1.5 text-[0.8125rem] text-white/50">
                                Ambil linknya, sebarkan ke audiensmu, dapat komisi tiap ada yang beli.
                            </p>
                        </div>

                        {hasStore && (
                            <button
                                type="button"
                                onClick={() => setHint((open) => !open)}
                                className="inline-flex items-center gap-1.5 rounded-full border border-white/15 px-3 py-1.5 text-xs font-medium text-white/70 transition hover:bg-white/10 hover:text-white"
                            >
                                <Info className="size-3.5" />
                                Produkku kok nggak ada di sini?
                            </button>
                        )}
                    </div>

                    {/* The two-screen setup is the single most common confusion
                        here, so the answer lives on the page that prompts it. */}
                    {hint && (
                        <div className="mt-4 rounded-2xl border border-white/10 bg-white/[.04] p-4">
                            <div className="flex items-start justify-between gap-3">
                                <p className="text-sm font-medium">Produkmu masuk ke sini kalau tiga hal ini terpenuhi</p>
                                <button
                                    type="button"
                                    onClick={() => setHint(false)}
                                    className="grid size-6 shrink-0 place-items-center rounded-md text-white/40 hover:bg-white/10 hover:text-white"
                                    aria-label="Tutup"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </div>
                            <ol className="mt-3 space-y-2 text-[0.8125rem] text-white/60">
                                <li className="flex gap-2.5">
                                    <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-white/10 text-[0.6875rem] font-semibold text-white">1</span>
                                    <span>
                                        Program affiliate tokomu <strong className="font-medium text-white">aktif</strong> — atur komisi dan
                                        masa tracking di{' '}
                                        <Link href="/dashboard/affiliate" className="font-medium text-white underline underline-offset-2">
                                            Dashboard → Affiliate
                                        </Link>
                                        .
                                    </span>
                                </li>
                                <li className="flex gap-2.5">
                                    <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-white/10 text-[0.6875rem] font-semibold text-white">2</span>
                                    <span>
                                        Di tiap produk, nyalakan <strong className="font-medium text-white">Izinkan affiliate JualanYok</strong>{' '}
                                        (Produk → edit → tab Lanjutan). Produk tipe link/affiliate nggak bisa.
                                    </span>
                                </li>
                                <li className="flex gap-2.5">
                                    <span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-white/10 text-[0.6875rem] font-semibold text-white">3</span>
                                    <span>Produknya aktif dan tokomu sudah diterbitkan.</span>
                                </li>
                            </ol>
                            <p className="mt-3 text-xs text-white/35">
                                Produk milikmu sendiri sengaja nggak ditampilkan di halaman ini — kamu nggak bisa jadi affiliate produk sendiri.
                            </p>
                        </div>
                    )}

                    {/* Filters */}
                    <div className="sticky top-14 z-10 -mx-4 mt-5 bg-[#0e0d14]/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                            <label className="relative w-full lg:max-w-sm">
                                <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-white/35" />
                                <input
                                    type="search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari produk atau toko"
                                    className="h-10 w-full rounded-full border border-white/10 bg-white/[.06] pl-10 pr-4 text-[0.8125rem] text-white outline-none transition placeholder:text-white/35 focus:border-white/30"
                                />
                            </label>

                            <div className="flex gap-1.5 overflow-x-auto [scrollbar-width:none]">
                                {SORTS.map((sort) => (
                                    <button
                                        key={sort.key}
                                        type="button"
                                        onClick={() => go({ sort: sort.key || undefined })}
                                        className={
                                            activeSort === sort.key
                                                ? 'shrink-0 rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold text-[#0e0d14]'
                                                : 'shrink-0 rounded-full border border-white/10 px-3.5 py-1.5 text-xs font-medium text-white/60 transition hover:bg-white/10 hover:text-white'
                                        }
                                    >
                                        {sort.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="mt-2.5 flex gap-1.5 overflow-x-auto pb-0.5 [scrollbar-width:none]">
                            <CategoryChip active={activeCategory === ''} onClick={() => go({ category: undefined })}>
                                Semua
                            </CategoryChip>
                            {categories.map((category) => (
                                <CategoryChip
                                    key={category.id}
                                    active={activeCategory === String(category.id)}
                                    onClick={() => go({ category: String(category.id) })}
                                >
                                    {category.name}
                                </CategoryChip>
                            ))}
                        </div>
                    </div>

                    {products.data.length === 0 ? (
                        <div className="mt-16 flex flex-col items-center text-center">
                            <span className="grid size-12 place-items-center rounded-2xl bg-white/[.06] text-white/40">
                                <Boxes className="size-6" />
                            </span>
                            <p className="mt-4 font-medium">Nggak ada produk yang cocok</p>
                            <p className="mt-1 max-w-sm text-[0.8125rem] text-white/45">
                                Coba hapus filternya, atau cek lagi nanti — creator baru terus membuka program affiliate.
                            </p>
                        </div>
                    ) : (
                        <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    )}

                    <div className="[&_a]:text-white/70 [&_span]:text-white/40">
                        <Pagination meta={products} />
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

function CategoryChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={
                active
                    ? 'shrink-0 rounded-full bg-white/[.14] px-3 py-1.5 text-xs font-semibold text-white'
                    : 'shrink-0 rounded-full px-3 py-1.5 text-xs font-medium text-white/45 transition hover:bg-white/[.07] hover:text-white/80'
            }
        >
            {children}
        </button>
    );
}

function ProductCard({ product }: { product: MarketProduct }) {
    const [joining, setJoining] = useState(false);

    return (
        <article className="group flex flex-col overflow-hidden rounded-2xl bg-white/[.045] transition-colors hover:bg-white/[.075]">
            <a
                href={product.public_url}
                target="_blank"
                rel="noopener noreferrer"
                className="relative block aspect-square overflow-hidden"
            >
                {product.thumbnail_url ? (
                    <img
                        src={product.thumbnail_url}
                        alt=""
                        loading="lazy"
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                    />
                ) : (
                    <span
                        className="grid size-full place-items-center p-4 text-center text-sm font-semibold leading-snug text-white/85"
                        style={{
                            background: `linear-gradient(140deg, hsl(${hue(product.name)} 62% 42%), hsl(${(hue(product.name) + 40) % 360} 58% 28%))`,
                        }}
                    >
                        {product.name}
                    </span>
                )}

                {/* The earning rate is what someone is scanning for, so it goes
                    on the picture rather than below the fold of the card. */}
                <span className="absolute left-2 top-2 rounded-full bg-[#0e0d14]/80 px-2 py-1 text-[0.6875rem] font-semibold text-emerald-300 backdrop-blur">
                    Komisi {product.commission_label}
                </span>

                {product.sales_count > 0 && (
                    <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-[#0e0d14]/75 px-2 py-1 text-[0.6875rem] font-medium text-white/80 backdrop-blur">
                        <TrendingUp className="size-3" />
                        {formatNumber(product.sales_count)} terjual
                    </span>
                )}
            </a>

            <div className="flex flex-1 flex-col p-3">
                <p className="line-clamp-2 text-[0.8125rem] font-medium leading-5">{product.name}</p>

                <Link
                    href={`/${product.store_username}`}
                    className="mt-1 truncate text-[0.6875rem] text-white/40 hover:text-white/70"
                >
                    {product.store}
                </Link>

                <div className="mt-2.5 flex items-baseline gap-2">
                    <span className="jy-num text-[0.9375rem] font-semibold">{formatIDR(product.price)}</span>
                    <span className="text-[0.6875rem] text-white/35">{product.type_label}</span>
                </div>

                <p className="mt-1 text-[0.75rem] font-medium text-emerald-300">
                    Kamu dapat {formatIDR(product.commission_amount)}
                    {product.cookie_days ? (
                        <span className="font-normal text-white/35"> · tracking {product.cookie_days} hari</span>
                    ) : null}
                </p>

                <div className="mt-3 flex-1" />

                {product.joined ? (
                    <Link
                        href="/affiliate/link"
                        className="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-full border border-white/15 text-xs font-semibold text-white/75 transition hover:bg-white/10 hover:text-white"
                    >
                        <Check className="size-3.5" /> Lihat linkku
                    </Link>
                ) : (
                    <button
                        type="button"
                        disabled={joining}
                        onClick={() => {
                            setJoining(true);
                            router.post(
                                `/affiliate/marketplace/${product.id}/gabung`,
                                {},
                                { preserveScroll: true, onFinish: () => setJoining(false) },
                            );
                        }}
                        className="inline-flex h-9 w-full items-center justify-center rounded-full bg-white text-xs font-semibold text-[#0e0d14] transition hover:bg-white/90 disabled:opacity-60"
                    >
                        {joining ? 'Memproses…' : 'Ambil link'}
                    </button>
                )}
            </div>
        </article>
    );
}
