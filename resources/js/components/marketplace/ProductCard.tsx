import { Link } from '@inertiajs/react';
import { ArrowUpRight, BadgeCheck, BookOpen, CalendarDays, Heart, Package, ShoppingBag, Sparkles, Star, Users } from 'lucide-react';
import { useState } from 'react';
import { cn, formatIDR } from '@/lib/utils';

export interface MarketplaceProduct {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    type: string;
    type_label: string;
    thumbnail_url: string | null;
    price: number;
    compare_at_price: number | null;
    discount_percent: number;
    sales_count: number;
    stock: number | null;
    affiliate_enabled: boolean;
    external_provider: string | null;
    rating_avg?: number | null;
    rating_count?: number;
    is_cartable: boolean;
    url: string;
    store: {
        name: string;
        username: string;
        avatar_url: string | null;
        is_verified: boolean;
        url: string;
    };
    category: { name: string; slug: string } | null;
}

const typeIcons: Record<string, typeof Package> = {
    DIGITAL: BookOpen,
    COURSE: Users,
    EVENT: CalendarDays,
    PHYSICAL: Package,
    EXTERNAL: ArrowUpRight,
};

export default function ProductCard({ product, compact = false }: { product: MarketplaceProduct; compact?: boolean }) {
    const [wished, setWished] = useState(false);
    const Icon = typeIcons[product.type] ?? Sparkles;

    return (
        <article className="group relative flex h-full min-w-0 flex-col overflow-hidden rounded-[1.35rem] border border-black/[.07] bg-white shadow-[0_8px_30px_rgba(36,25,70,.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(74,45,130,.13)]">
            <div className={cn('relative overflow-hidden bg-[#f2edf9]', compact ? 'aspect-[1.15]' : 'aspect-square')}>
                <Link href={product.url} prefetch className="block size-full" aria-label={`Lihat ${product.name}`}>
                    {product.thumbnail_url ? (
                        <img src={product.thumbnail_url} alt={product.name} loading="lazy" className="size-full object-cover transition duration-500 group-hover:scale-[1.035]" />
                    ) : (
                        <div className="grid size-full place-items-center bg-[radial-gradient(circle_at_25%_20%,#ffffff_0,#efe7ff_34%,#dbc8ff_100%)]">
                            <div className="grid size-16 place-items-center rounded-2xl border border-white/70 bg-white/75 text-violet-600 shadow-lg backdrop-blur">
                                <Icon className="size-7" />
                            </div>
                        </div>
                    )}
                </Link>
                <div className="absolute left-3 top-3 flex max-w-[75%] flex-wrap gap-1.5">
                    <span className="rounded-full border border-white/70 bg-white/90 px-2.5 py-1 text-[9px] font-extrabold text-[#272334] shadow-sm backdrop-blur">{product.type_label}</span>
                    {product.affiliate_enabled && <span className="rounded-full bg-[#171722] px-2.5 py-1 text-[9px] font-extrabold text-white">Affiliate</span>}
                    {product.discount_percent > 0 && <span className="rounded-full bg-coral-500 px-2.5 py-1 text-[9px] font-extrabold text-white">-{product.discount_percent}%</span>}
                </div>
                <button type="button" onClick={() => setWished((value) => !value)} aria-label={wished ? 'Hapus dari favorit' : 'Simpan ke favorit'} aria-pressed={wished} className="absolute right-3 top-3 grid size-9 place-items-center rounded-full border border-white/70 bg-white/90 shadow-sm backdrop-blur transition hover:scale-105">
                    <Heart className={cn('size-4', wished && 'fill-rose-500 text-rose-500')} />
                </button>
            </div>

            <div className="flex flex-1 flex-col p-4">
                <Link href={product.store.url} className="flex min-w-0 items-center gap-2 text-[10px] font-bold text-neutral-500 hover:text-violet-700">
                    <span className="grid size-6 shrink-0 place-items-center overflow-hidden rounded-full bg-violet-100 text-[9px] font-black text-violet-700">
                        {product.store.avatar_url ? <img src={product.store.avatar_url} alt="" className="size-full object-cover" /> : product.store.name.charAt(0)}
                    </span>
                    <span className="truncate">{product.store.name}</span>
                    {product.store.is_verified && <BadgeCheck className="size-3.5 shrink-0 fill-violet-600 text-white" aria-label="Creator terverifikasi" />}
                </Link>
                <Link href={product.url} prefetch className="mt-2 line-clamp-2 min-h-10 text-sm font-extrabold leading-5 tracking-[-.015em] text-[#171722] hover:text-violet-700">{product.name}</Link>
                {!compact && product.short_description && <p className="mt-1.5 line-clamp-2 text-[11px] leading-[1.15rem] text-neutral-500">{product.short_description}</p>}

                <div className="mt-auto pt-4">
                    <div className="flex items-end justify-between gap-2">
                        <div>
                            {product.compare_at_price && <p className="text-[9px] text-neutral-400 line-through">{formatIDR(product.compare_at_price)}</p>}
                            <p className="text-[15px] font-black tracking-tight text-[#171722]">{product.type === 'EXTERNAL' ? 'Cek harga' : product.price === 0 ? 'Gratis' : formatIDR(product.price)}</p>
                        </div>
                        <p className="flex shrink-0 items-center gap-1.5 text-[9px] font-semibold text-neutral-400">
                            {(product.rating_count ?? 0) > 0 && product.rating_avg != null && (
                                <span className="inline-flex items-center gap-0.5 text-neutral-700">
                                    <Star className="size-3 fill-amber-400 text-amber-400" aria-hidden="true" />
                                    {product.rating_avg.toFixed(1)}
                                </span>
                            )}
                            {product.sales_count > 0 && <span>{product.sales_count.toLocaleString('id-ID')} terjual</span>}
                        </p>
                    </div>
                    <Link href={product.url} className="mt-3 flex h-9 items-center justify-center gap-2 rounded-full bg-[#171722] px-4 text-[10px] font-extrabold text-white transition hover:bg-violet-700">
                        {product.type === 'EXTERNAL' ? `Buka ${product.external_provider ?? 'marketplace'}` : 'Lihat produk'}
                        {product.type === 'EXTERNAL' ? <ArrowUpRight className="size-3.5" /> : <ShoppingBag className="size-3.5" />}
                    </Link>
                </div>
            </div>
        </article>
    );
}

export function ProductCardSkeleton() {
    return <div className="overflow-hidden rounded-[1.35rem] border border-line bg-white"><div className="skeleton aspect-square" /><div className="space-y-3 p-4"><div className="skeleton h-5 w-3/4 rounded" /><div className="skeleton h-4 w-full rounded" /><div className="skeleton h-9 w-full rounded-full" /></div></div>;
}
