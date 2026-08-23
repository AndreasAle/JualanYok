import { ArrowUpRight, BadgeCheck, ExternalLink, ImageIcon, Plus, ShoppingBag, Star } from 'lucide-react';
import { cn, formatIDR, formatNumber } from '@/lib/utils';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import type { StorefrontProduct } from '@/types';

/**
 * Marketplace-style product tile. The visual priorities follow what shoppers
 * actually scan for: image, then price, then the deal, then social proof.
 */
export function ProductCard({
    product,
    theme,
    layout = 'grid',
    onBuy,
    onAddToCart,
    onOpen,
}: {
    product: StorefrontProduct & { sales_count?: number };
    theme: StorefrontTheme;
    layout?: 'grid' | 'list';
    onBuy: () => void;
    onAddToCart?: () => void;
    onOpen?: () => void;
}) {
    // Product type is the source of truth. The tracked URL can intentionally
    // be absent in builder preview, and must not make an affiliate item fall
    // through to the internal checkout flow.
    const external = product.type === 'EXTERNAL';
    const provider = product.external_provider || 'Marketplace';
    const soldOut = !external && !product.is_buyable;
    // Donations, pay-what-you-want, and bookings set their own terms per
    // purchase, so they keep the direct buy path only.
    const needsOptions = !!product.requires_variant;
    // A tile has no room to pick options, so those products route to the page —
    // checkout refuses a line with no variant, and stock lives on the variant.
    const cartable = !!onAddToCart && !!product.is_cartable && !soldOut && !needsOptions;
    const primaryAction = external ? onBuy : needsOptions && onOpen ? onOpen : onBuy;
    const primaryLabel = external ? (product.external_cta || `Beli di ${provider}`) : soldOut ? 'Habis' : needsOptions ? 'Pilih' : 'Beli';
    const sold = product.sales_count ?? 0;

    const price = external
        ? product.price > 0 ? formatIDR(product.price) : 'Cek harga terbaru'
        : product.is_pay_what_you_want
        ? `Mulai ${formatIDR(product.minimum_price ?? 0)}`
        : formatIDR(product.price);

    if (layout === 'list') {
        return (
            <article
                className={cn(
                    theme.card,
                    '@container group flex gap-3 overflow-hidden p-3 transition-shadow hover:shadow-lg @lg:gap-4 @lg:p-4',
                )}
            >
                <button
                    type="button"
                    onClick={onOpen}
                    className="relative size-24 shrink-0 overflow-hidden rounded-xl @lg:size-28"
                    aria-label={`Lihat ${product.name}`}
                >
                    <Thumb product={product} />
                    {product.discount_percent > 0 && <DiscountRibbon value={product.discount_percent} />}
                </button>

                <div className="flex min-w-0 flex-1 flex-col">
                    <div className="flex flex-wrap items-center gap-1.5">
                        {external ? <MarketplacePill provider={provider} /> : <TypePill label={product.type_label} />}
                        {!external && sold > 0 && <span className={cn('text-[11px]', theme.muted)}>{formatNumber(sold)} terjual</span>}
                    </div>

                    <button
                        type="button"
                        onClick={onOpen}
                        className="mt-1 text-left text-sm font-bold leading-snug hover:text-[var(--sf-primary)] @lg:text-base"
                    >
                        <span className="line-clamp-2">{product.name}</span>
                    </button>

                    {product.short_description && (
                        <p className={cn('mt-1 line-clamp-1 text-xs @lg:text-sm', theme.muted)}>
                            {product.short_description}
                        </p>
                    )}

                    <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-2">
                        <PriceBlock price={price} product={product} theme={theme} />

                        <div className="flex items-center gap-1.5">
                            {cartable && (
                                <button
                                    type="button"
                                    onClick={onAddToCart}
                                    aria-label={`Masukkan ${product.name} ke keranjang`}
                                    className="grid size-9 place-items-center rounded-xl border border-[var(--sf-line)] transition hover:border-[var(--sf-primary)] hover:text-[var(--sf-primary)]"
                                >
                                    <Plus className="size-4" />
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={primaryAction}
                                disabled={soldOut}
                                className={cn(theme.btnPrimary, 'h-9 px-4 text-sm')}
                            >
                                {external && <ExternalLink className="mr-1.5 size-3.5" />}
                                {primaryLabel}
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        );
    }

    return (
        <article
            className={cn(
                theme.card,
                '@container group flex flex-col overflow-hidden rounded-[1.35rem] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(16,24,40,.12)]',
            )}
        >
            <button
                type="button"
                onClick={onOpen}
                className="relative aspect-square w-full overflow-hidden"
                aria-label={`Lihat ${product.name}`}
            >
                <Thumb product={product} />

                {product.discount_percent > 0 && <DiscountRibbon value={product.discount_percent} />}

                <span className="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full border border-white/60 bg-white/90 px-2 py-1 text-[9px] font-extrabold text-slate-800 opacity-0 shadow-sm backdrop-blur transition-opacity group-hover:opacity-100">
                    Preview <ArrowUpRight className="size-2.5" />
                </span>

                {soldOut && (
                    <span className="absolute inset-0 grid place-items-center bg-black/55">
                        <span className="rounded-full bg-white/95 px-4 py-1.5 text-xs font-bold text-slate-900">
                            Stok Habis
                        </span>
                    </span>
                )}
            </button>

            <div className="flex flex-1 flex-col p-3 @sm:p-4">
                <div className="flex items-center justify-between gap-2">
                    {external ? <MarketplacePill provider={provider} /> : <TypePill label={product.type_label} />}
                    <span className="inline-flex items-center gap-0.5 text-[9px] font-bold text-[var(--sf-muted)]"><BadgeCheck className="size-3 text-[var(--sf-primary)]" /> {external ? 'Rekomendasi' : 'Pilihan'}</span>
                </div>

                <button
                    type="button"
                    onClick={onOpen}
                    className="mt-2 text-left text-[13px] font-extrabold leading-snug tracking-[-.01em] hover:text-[var(--sf-primary)] @sm:text-[15px]"
                >
                    <span className="line-clamp-2 min-h-[2.6em]">{product.name}</span>
                </button>

                {product.short_description && <p className={cn('mt-1 hidden line-clamp-2 text-xs leading-5 @md:block', theme.muted)}>{product.short_description}</p>}

                <div className="mt-2.5">
                    <PriceBlock price={price} product={product} theme={theme} />
                </div>

                <div className={cn('mt-1.5 flex min-h-4 items-center gap-1 text-[10px] @sm:text-[11px]', theme.muted)}>
                    <Star className="size-3 fill-amber-400 text-amber-400" />
                    <span>{external ? `Buka di ${provider}` : sold > 0 ? `${formatNumber(sold)} terjual` : 'Produk baru'}</span>
                </div>

                <div className="mt-3 flex items-center gap-1.5">
                    {cartable && (
                        <button
                            type="button"
                            onClick={onAddToCart}
                            aria-label={`Masukkan ${product.name} ke keranjang`}
                            title="Masukkan keranjang"
                            className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[var(--sf-line)] transition hover:border-[var(--sf-primary)] hover:text-[var(--sf-primary)] @sm:h-11 @sm:w-11"
                        >
                            <Plus className="size-4.5" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={primaryAction}
                        disabled={soldOut}
                        className={cn(
                            theme.btnPrimary,
                            'h-10 min-w-0 flex-1 whitespace-nowrap px-2 text-[12px] shadow-sm @xs:px-3 @xs:text-sm @sm:h-11',
                        )}
                    >
                        {external ? <ExternalLink className="size-4 shrink-0" /> : <ShoppingBag className="size-4 shrink-0" />}
                        <span className="truncate">
                            {primaryLabel}
                            {!external && <span className="hidden @xs:inline">{soldOut ? '' : needsOptions ? ' Varian' : ' Sekarang'}</span>}
                        </span>
                    </button>
                </div>
            </div>
        </article>
    );
}

/* -------------------------------------------------------------------------- */

function Thumb({ product }: { product: StorefrontProduct }) {
    if (product.thumbnail_url) {
        return (
            <img
                src={product.thumbnail_url}
                alt=""
                loading="lazy"
                className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
        );
    }

    return (
        <span
            className="grid size-full place-items-center"
            style={{
                background:
                    'linear-gradient(135deg, color-mix(in oklab, var(--sf-primary) 22%, transparent), color-mix(in oklab, var(--sf-accent) 26%, transparent))',
            }}
        >
            <ImageIcon className="size-8 opacity-40" />
        </span>
    );
}

function DiscountRibbon({ value }: { value: number }) {
    return (
        <span className="absolute left-0 top-2 rounded-r-full bg-rose-500 py-1 pl-2 pr-2.5 text-[11px] font-extrabold text-white shadow">
            -{value}%
        </span>
    );
}

function TypePill({ label }: { label: string }) {
    return (
        <span className="inline-flex w-fit items-center rounded-md bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--sf-primary)]">
            {label}
        </span>
    );
}

function MarketplacePill({ provider }: { provider: string }) {
    const color = provider === 'Shopee'
        ? '#EE4D2D'
        : provider === 'Tokopedia'
          ? '#03AC0E'
          : provider === 'TikTok Shop'
            ? '#111827'
            : '#6D28D9';

    return (
        <span className="inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-white" style={{ backgroundColor: color }}>
            <ShoppingBag className="size-2.5" /> {provider}
        </span>
    );
}

function PriceBlock({
    price,
    product,
    theme,
}: {
    price: string;
    product: StorefrontProduct;
    theme: StorefrontTheme;
}) {
    return (
        <div className="min-w-0">
            <p className="truncate text-[15px] font-black tracking-[-.02em] text-[var(--sf-primary)] @xs:text-lg">{price}</p>
            {product.compare_at_price && (
                <p className={cn('text-xs line-through', theme.muted)}>{formatIDR(product.compare_at_price)}</p>
            )}
        </div>
    );
}
