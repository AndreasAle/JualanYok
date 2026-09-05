import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock, ExternalLink, ImageIcon,
    MapPin, MessageCircle, Minus, PlayCircle, Plus, ShieldCheck, ShoppingBag, Store as StoreIcon,
    Ticket, Truck, Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CartSheet } from '@/components/storefront/CartSheet';
import { ChatSheet } from '@/components/storefront/ChatSheet';
import { CheckoutSheet } from '@/components/storefront/CheckoutSheet';
import { ProductCard } from '@/components/storefront/ProductCard';
import { ProductReviews, Stars, type ReviewRow, type ReviewSummary } from '@/components/storefront/ProductReviews';
import { ShareProductButton } from '@/components/storefront/ShareProductButton';
import { PurchaseChoiceSheet } from '@/components/storefront/PurchaseChoiceSheet';
import { buildStorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatDate, formatIDR, formatNumber } from '@/lib/utils';
import type { StorefrontStore } from '@/pages/Storefront/Show';
import type { CartPayload, Paginated, StorefrontProduct } from '@/types';

interface Variant {
    id: number;
    name: string;
    price: number;
    stock: number;
    options?: Record<string, string> | null;
}

interface DetailedProduct extends StorefrontProduct {
    description: string | null;
    terms: string | null;
    checkout_message: string | null;
    sales_count?: number;
    sku: string | null;
    category: string | null;
    view_count: number;
    stock: number | null;
    weight_gram: number | null;
    min_quantity: number;
    max_quantity: number | null;
    media: { url: string; alt: string | null }[];
    variants: Variant[];
    course: {
        level: string;
        outcome: string | null;
        lesson_count: number;
        duration_minutes: number;
        sections: { title: string; lessons: { title: string; duration_minutes: number; is_free_preview: boolean }[] }[];
    } | null;
    event: {
        starts_at: string;
        mode: string;
        location: string | null;
        seats_left: number | null;
        tickets: { id: number; name: string; price: number }[];
    } | null;
    service: { duration_minutes: number; timezone: string } | null;
    membership_plans: { id: number; name: string; price: number; interval: string }[];
}

interface Seller {
    name: string;
    username: string;
    avatar_url: string | null;
    public_url: string;
    whatsapp: string | null;
    products_count: number;
    sales_count: number;
    /** True when the person looking at this page owns the shop. */
    is_own: boolean;
    joined_human: string | null;
    origin: string | null;
}

interface Voucher {
    code: string;
    label: string;
    min_order: number;
    ends_at: string | null;
}

type Theme = ReturnType<typeof buildStorefrontTheme>;

/**
 * Variant option groups, derived from the variants themselves.
 *
 * A seller enters variants as whole combinations ("Hitam / L"), each carrying
 * an options map. Buyers do not pick combinations — they pick a colour, then a
 * size. Turning the flat list back into named rows is what makes a product with
 * a dozen variants pickable at all, and it stays in sync because it is read
 * from the same data rather than configured a second time.
 */
function optionGroups(variants: Variant[]): { name: string; values: string[] }[] {
    const groups = new Map<string, string[]>();

    for (const variant of variants) {
        for (const [name, value] of Object.entries(variant.options ?? {})) {
            if (!value) continue;

            const values = groups.get(name) ?? [];
            if (!values.includes(value)) values.push(value);
            groups.set(name, values);
        }
    }

    return [...groups.entries()].map(([name, values]) => ({ name, values }));
}

export default function StorefrontProductPage({
    store,
    product,
    related,
    cart,
    seller,
    vouchers,
    reviewSummary,
    reviews,
    reviewFilter,
}: {
    store: StorefrontStore;
    product: DetailedProduct;
    related: StorefrontProduct[];
    cart: CartPayload | null;
    seller: Seller;
    vouchers: Voucher[];
    reviewSummary: ReviewSummary;
    reviews: Paginated<ReviewRow>;
    reviewFilter: string;
}) {
    const theme = buildStorefrontTheme(store.theme);
    const productShareUrl = product.share_url ?? `/${store.username}/p/${product.slug}`;

    const [checkout, setCheckout] = useState<StorefrontProduct | null>(null);
    const [purchaseChoice, setPurchaseChoice] = useState<StorefrontProduct | null>(null);
    const [cartOpen, setCartOpen] = useState(false);
    const [cartCheckout, setCartCheckout] = useState(false);
    const [chatOpen, setChatOpen] = useState(false);
    const [activeImage, setActiveImage] = useState(0);
    const [quantity, setQuantity] = useState(Math.max(1, product.min_quantity || 1));
    const [picked, setPicked] = useState<Record<string, string>>({});
    const [variantId, setVariantId] = useState<number | null>(
        product.variants.length === 1 ? product.variants[0].id : null,
    );

    const images = [
        ...(product.thumbnail_url ? [{ url: product.thumbnail_url, alt: product.name }] : []),
        ...(product.media ?? []),
    ].filter((image, index, items) => items.findIndex((candidate) => candidate.url === image.url) === index);

    const groups = useMemo(() => optionGroups(product.variants), [product.variants]);
    const selectedVariant = product.variants.find((v) => v.id === variantId) ?? null;
    const needsVariant = product.variants.length > 0 && !selectedVariant;

    /** Picking an option narrows the set; the variant resolves once one remains. */
    const choose = (group: string, value: string) => {
        const next = { ...picked, [group]: picked[group] === value ? '' : value };
        setPicked(next);

        const matches = product.variants.filter((variant) =>
            Object.entries(next).every(([name, chosen]) => !chosen || variant.options?.[name] === chosen),
        );

        setVariantId(matches.length === 1 ? matches[0].id : null);
    };

    /** A value with no in-stock variant behind it is not offerable. */
    const soldOut = (group: string, value: string) =>
        product.variants
            .filter((variant) => variant.options?.[group] === value)
            .every((variant) => variant.stock !== null && variant.stock <= 0);

    const stock = selectedVariant?.stock ?? product.stock;
    const maxQuantity = Math.min(product.max_quantity ?? 9999, stock && stock > 0 ? stock : 9999);

    const unitPrice = selectedVariant?.price ?? product.price;
    const price = product.external_url
        ? product.price > 0 ? formatIDR(product.price) : 'Cek harga terbaru'
        : product.is_pay_what_you_want
          ? `Mulai ${formatIDR(product.minimum_price ?? 0)}`
          : formatIDR(unitPrice);

    const buy = () => {
        if (needsVariant) return;

        if (product.type === 'EXTERNAL') {
            if (product.external_url) window.location.assign(product.external_url);
            return;
        }

        setPurchaseChoice(product);
    };

    const addToCart = (item: StorefrontProduct, variant: number | null = null, qty = 1) => {
        router.post(
            `/${store.username}/keranjang`,
            { product_id: item.id, quantity: qty, ...(variant ? { variant_id: variant } : {}) },
            { preserveScroll: true, preserveState: true, onSuccess: () => setCartOpen(true) },
        );
    };

    const cartCount = cart?.item_count ?? 0;



    return (
        <div className="min-h-screen pb-24 lg:pb-10" style={theme.pageStyle}>
            <Head title={`${product.name} — ${store.name}`}>
                {product.short_description && <meta name="description" content={product.short_description} />}
                <meta property="og:title" content={product.name} />
                {product.thumbnail_url && <meta property="og:image" content={product.thumbnail_url} />}
            </Head>

            {/* Breadcrumb */}
            <div className={cn('border-b bg-[var(--sf-card)]', theme.line)}>
                <div className="mx-auto flex max-w-6xl items-center gap-2 overflow-x-auto px-4 py-3 text-[0.8125rem] sm:px-6">
                    <Link href={`/${store.username}`} className="inline-flex shrink-0 items-center gap-1.5 font-semibold hover:text-[var(--sf-primary)]">
                        <ArrowLeft className="size-4" />
                        {store.name}
                    </Link>
                    {product.category && (
                        <>
                            <span className={theme.muted}>/</span>
                            <span className={cn('shrink-0', theme.muted)}>{product.category}</span>
                        </>
                    )}
                    <span className={theme.muted}>/</span>
                    <span className={cn('truncate', theme.muted)}>{product.name}</span>
                </div>
            </div>

            <main className="mx-auto max-w-6xl space-y-3 px-3 py-3 sm:px-6 sm:py-5">
                {/* ── Panel 1: gallery + buy box ─────────────────────────── */}
                <div className={cn(theme.card, 'grid gap-5 p-3 sm:p-5 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:gap-8')}>
                    <Gallery
                        images={images}
                        active={activeImage}
                        onChange={setActiveImage}
                        discount={product.discount_percent}
                        theme={theme}
                    />

                    <div className="min-w-0">
                        <h1 className="text-lg font-bold leading-snug text-balance sm:text-xl">{product.name}</h1>

                        <div className={cn('mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.8125rem]', theme.muted)}>
                            {reviewSummary.total > 0 && (
                                <a href="#ulasan" className="inline-flex items-center gap-1.5 hover:underline">
                                    <strong className="font-semibold text-[var(--sf-fg)]">{reviewSummary.average.toFixed(1)}</strong>
                                    <Stars rating={Math.round(reviewSummary.average)} />
                                    <span>({formatNumber(reviewSummary.total)})</span>
                                </a>
                            )}
                            {(product.sales_count ?? 0) > 0 && (
                                <span>
                                    <strong className="font-semibold text-[var(--sf-fg)]">{formatNumber(product.sales_count!)}</strong> terjual
                                </span>
                            )}
                            <span>
                                <strong className="font-semibold text-[var(--sf-fg)]">{formatNumber(product.view_count)}</strong> dilihat
                            </span>
                            <span className="rounded bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] px-1.5 py-0.5 text-[0.6875rem] font-semibold text-[var(--sf-primary)]">
                                {product.type_label}
                            </span>
                        </div>

                        {/* The price band. Shopee puts the money on its own tinted
                            shelf so it survives being scanned at speed. */}
                        <div className="mt-3 rounded-lg bg-[color-mix(in_oklab,var(--sf-primary)_7%,transparent)] px-4 py-3.5">
                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span className="text-[1.75rem] font-bold leading-none text-[var(--sf-primary)]">{price}</span>
                                {!product.external_url && product.compare_at_price && (
                                    <>
                                        <span className={cn('text-sm line-through', theme.muted)}>
                                            {formatIDR(product.compare_at_price)}
                                        </span>
                                        {product.discount_percent > 0 && (
                                            <span className="rounded bg-rose-500 px-1.5 py-0.5 text-[0.6875rem] font-bold text-white">
                                                -{product.discount_percent}%
                                            </span>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>

                        <dl className="mt-4 space-y-3.5 text-[0.8125rem]">
                            {vouchers.length > 0 && (
                                <Row label="Voucher Toko" theme={theme}>
                                    <div className="flex flex-wrap gap-1.5">
                                        {vouchers.map((voucher) => (
                                            <span
                                                key={voucher.code}
                                                title={voucher.min_order > 0 ? `Min. belanja ${formatIDR(voucher.min_order)}` : undefined}
                                                className="rounded border border-[color-mix(in_oklab,var(--sf-primary)_35%,transparent)] bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)] px-2 py-1 text-[0.6875rem] font-semibold text-[var(--sf-primary)]"
                                            >
                                                {voucher.label}
                                            </span>
                                        ))}
                                    </div>
                                </Row>
                            )}

                            {seller.origin && product.type === 'PHYSICAL' && (
                                <Row label="Pengiriman" theme={theme}>
                                    <span className="inline-flex items-center gap-1.5">
                                        <Truck className="size-4 text-[var(--sf-primary)]" />
                                        Dikirim dari {seller.origin}
                                    </span>
                                    <p className={cn('mt-0.5 text-xs', theme.muted)}>
                                        Ongkir dihitung otomatis sesuai alamatmu saat checkout.
                                    </p>
                                </Row>
                            )}

                            {/* Options, one named row each. */}
                            {groups.length > 0
                                ? groups.map((group) => (
                                      <Row key={group.name} label={group.name} theme={theme}>
                                          <div className="flex flex-wrap gap-2">
                                              {group.values.map((value) => {
                                                  const out = soldOut(group.name, value);

                                                  return (
                                                      <button
                                                          key={value}
                                                          type="button"
                                                          disabled={out}
                                                          onClick={() => choose(group.name, value)}
                                                          aria-pressed={picked[group.name] === value}
                                                          className={cn(
                                                              'rounded border px-3 py-1.5 text-[0.8125rem] transition',
                                                              picked[group.name] === value
                                                                  ? 'border-[var(--sf-primary)] bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] font-semibold text-[var(--sf-primary)]'
                                                                  : 'border-[var(--sf-line)] hover:border-[var(--sf-primary)]',
                                                              out && 'cursor-not-allowed line-through opacity-40',
                                                          )}
                                                      >
                                                          {value}
                                                      </button>
                                                  );
                                              })}
                                          </div>
                                      </Row>
                                  ))
                                : product.variants.length > 0 && (
                                      <Row label="Varian" theme={theme}>
                                          <div className="flex flex-wrap gap-2">
                                              {product.variants.map((variant) => {
                                                  const out = variant.stock !== null && variant.stock <= 0;

                                                  return (
                                                      <button
                                                          key={variant.id}
                                                          type="button"
                                                          disabled={out}
                                                          onClick={() => setVariantId(variant.id)}
                                                          aria-pressed={variantId === variant.id}
                                                          className={cn(
                                                              'rounded border px-3 py-1.5 text-[0.8125rem] transition',
                                                              variantId === variant.id
                                                                  ? 'border-[var(--sf-primary)] bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] font-semibold text-[var(--sf-primary)]'
                                                                  : 'border-[var(--sf-line)] hover:border-[var(--sf-primary)]',
                                                              out && 'cursor-not-allowed line-through opacity-40',
                                                          )}
                                                      >
                                                          {variant.name}
                                                      </button>
                                                  );
                                              })}
                                          </div>
                                      </Row>
                                  )}

                            {!product.external_url && !product.is_pay_what_you_want && (
                                <Row label="Kuantitas" theme={theme}>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="inline-flex items-center rounded border border-[var(--sf-line)]">
                                            <button
                                                type="button"
                                                onClick={() => setQuantity((q) => Math.max(product.min_quantity || 1, q - 1))}
                                                disabled={quantity <= (product.min_quantity || 1)}
                                                className="grid size-8 place-items-center disabled:opacity-30"
                                                aria-label="Kurangi"
                                            >
                                                <Minus className="size-3.5" />
                                            </button>
                                            <span className="w-10 text-center text-[0.8125rem] font-semibold tabular-nums">{quantity}</span>
                                            <button
                                                type="button"
                                                onClick={() => setQuantity((q) => Math.min(maxQuantity, q + 1))}
                                                disabled={quantity >= maxQuantity}
                                                className="grid size-8 place-items-center disabled:opacity-30"
                                                aria-label="Tambah"
                                            >
                                                <Plus className="size-3.5" />
                                            </button>
                                        </div>

                                        <span className={cn('text-xs', theme.muted)}>
                                            {stock === null
                                                ? 'Tersedia'
                                                : stock > 0
                                                  ? `Tersisa ${formatNumber(stock)}`
                                                  : 'Stok habis'}
                                        </span>
                                    </div>
                                </Row>
                            )}
                        </dl>

                        {needsVariant && (
                            <p className="mt-3 text-xs font-medium text-rose-500">Pilih dulu variannya sebelum lanjut.</p>
                        )}

                        <div className="mt-5 flex flex-wrap gap-2.5">
                            {product.is_cartable && (
                                <button
                                    type="button"
                                    onClick={() => addToCart(product, variantId, quantity)}
                                    disabled={needsVariant || !product.is_buyable}
                                    className="inline-flex h-11 flex-1 min-w-[10rem] items-center justify-center gap-2 rounded border border-[var(--sf-primary)] bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)] px-5 text-sm font-semibold text-[var(--sf-primary)] transition hover:bg-[color-mix(in_oklab,var(--sf-primary)_14%,transparent)] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <Plus className="size-4" />
                                    Masukkan Keranjang
                                </button>
                            )}

                            <button
                                type="button"
                                onClick={buy}
                                disabled={(!product.external_url && !product.is_buyable) || needsVariant}
                                className={cn(theme.btnPrimary, 'h-11 flex-1 min-w-[10rem] rounded px-5 text-sm')}
                            >
                                {product.external_url ? <ExternalLink className="size-4" /> : <ShoppingBag className="size-4" />}
                                {product.external_url
                                    ? product.external_cta || `Beli di ${product.external_provider || 'Marketplace'}`
                                    : product.is_buyable
                                      ? 'Beli Sekarang'
                                      : 'Stok Habis'}
                            </button>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2">
                            {!seller.is_own && (
                                <button
                                    type="button"
                                    onClick={() => setChatOpen(true)}
                                    className="inline-flex items-center gap-1.5 text-[0.8125rem] font-semibold text-[var(--sf-primary)] hover:underline"
                                >
                                    <MessageCircle className="size-4" /> Chat penjual
                                </button>
                            )}
                            <ShareProductButton url={productShareUrl} title={product.name} label />
                        </div>

                        {product.external_url ? (
                            <div className={cn('mt-4 rounded border p-3 text-xs leading-5', theme.line, theme.muted)}>
                                <p className="flex items-center gap-2 font-semibold text-[var(--sf-fg)]">
                                    <ExternalLink className="size-4 text-[var(--sf-primary)]" /> Pembelian dilanjutkan di {product.external_provider || 'marketplace'}.
                                </p>
                                <p className="mt-1">Harga, stok, pembayaran, pengiriman, dan refund mengikuti kebijakan marketplace tujuan.</p>
                            </div>
                        ) : (
                            <ul className={cn('mt-4 flex flex-wrap gap-x-5 gap-y-1.5 border-t pt-4 text-xs', theme.line, theme.muted)}>
                                <li className="flex items-center gap-1.5"><ShieldCheck className="size-3.5 shrink-0 text-emerald-500" /> Pembayaran aman lewat JualanYok</li>
                                <li className="flex items-center gap-1.5"><CheckCircle2 className="size-3.5 shrink-0 text-emerald-500" /> Produk digital dikirim otomatis</li>
                                <li className="flex items-center gap-1.5"><Truck className="size-3.5 shrink-0 text-emerald-500" /> Bisa ajukan refund sesuai kebijakan</li>
                            </ul>
                        )}
                    </div>
                </div>

                {/* ── Panel 2: the seller ────────────────────────────────── */}
                <div className={cn(theme.card, 'flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center')}>
                    <div className="flex min-w-0 items-center gap-3 lg:w-80">
                        <span className="size-14 shrink-0 overflow-hidden rounded-full">
                            {seller.avatar_url ? (
                                <img src={seller.avatar_url} alt="" className="size-full object-cover" />
                            ) : (
                                <span
                                    className="grid size-full place-items-center text-lg font-bold"
                                    style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                                >
                                    {seller.name[0]?.toUpperCase()}
                                </span>
                            )}
                        </span>

                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">{seller.name}</p>
                            <p className={cn('truncate text-xs', theme.muted)}>@{seller.username}</p>

                            <div className="mt-2 flex gap-2">
                                {!seller.is_own && (
                                    <button
                                        type="button"
                                        onClick={() => setChatOpen(true)}
                                        className="inline-flex h-8 items-center gap-1.5 rounded border border-[var(--sf-primary)] px-2.5 text-xs font-semibold text-[var(--sf-primary)]"
                                    >
                                        <MessageCircle className="size-3.5" /> Chat
                                    </button>
                                )}
                                <Link
                                    href={`/${seller.username}`}
                                    className="inline-flex h-8 items-center gap-1.5 rounded border border-[var(--sf-line)] px-2.5 text-xs font-semibold"
                                >
                                    <StoreIcon className="size-3.5" /> Kunjungi Toko
                                </Link>
                            </div>
                        </div>
                    </div>

                    <dl className={cn('grid flex-1 grid-cols-2 gap-x-6 gap-y-3 border-t pt-4 text-[0.8125rem] sm:grid-cols-3 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0', theme.line)}>
                        <Stat label="Produk" value={formatNumber(seller.products_count)} theme={theme} />
                        <Stat label="Total terjual" value={formatNumber(seller.sales_count)} theme={theme} />
                        {seller.joined_human && <Stat label="Bergabung" value={seller.joined_human} theme={theme} />}
                    </dl>
                </div>

                {/* ── Panel 3: specifics, description, and the store rail ── */}
                <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                    <div className="space-y-3">
                        <Section title="Spesifikasi Produk" theme={theme}>
                            <dl className="divide-y divide-[var(--sf-line)] text-[0.8125rem]">
                                <Spec label="Kategori" value={product.category} theme={theme} />
                                <Spec label="Tipe" value={product.type_label} theme={theme} />
                                <Spec label="SKU" value={product.sku} theme={theme} />
                                <Spec
                                    label="Stok"
                                    value={stock === null ? 'Tersedia' : stock > 0 ? formatNumber(stock) : 'Habis'}
                                    theme={theme}
                                />
                                {product.weight_gram ? (
                                    <Spec label="Berat" value={`${formatNumber(product.weight_gram)} gram`} theme={theme} />
                                ) : null}
                                <Spec
                                    label="Min. pembelian"
                                    value={product.min_quantity > 1 ? `${product.min_quantity} pcs` : '1 pcs'}
                                    theme={theme}
                                />
                                <Spec label="Dikirim dari" value={seller.origin} theme={theme} />
                                <Spec label="Toko" value={seller.name} theme={theme} />
                            </dl>
                        </Section>

                        {(product.description || product.short_description) && (
                            <Section title="Deskripsi Produk" theme={theme}>
                                {product.short_description && (
                                    <p className="mb-3 text-[0.875rem] font-medium leading-6">{product.short_description}</p>
                                )}
                                <div className={cn('space-y-2.5 text-[0.875rem] leading-7', theme.muted)}>
                                    {(product.description ?? '')
                                        .split('\n')
                                        .filter(Boolean)
                                        .map((paragraph, i) => (
                                            <p key={i}>{paragraph}</p>
                                        ))}
                                </div>
                            </Section>
                        )}

                        <div id="ulasan">
                            <ProductReviews
                                summary={reviewSummary}
                                reviews={reviews}
                                filter={reviewFilter}
                                storeName={seller.name}
                                theme={theme}
                            />
                        </div>

                        {product.course && (
                            <Section title="Isi kelas" theme={theme}>
                                <div className="mb-4 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                    <Meta label={`${product.course.lesson_count} materi`} />
                                    <Meta label={`${Math.round(product.course.duration_minutes / 60)} jam total`} />
                                    <Meta label={`Level ${product.course.level}`} />
                                </div>

                                {product.course.outcome && (
                                    <p className={cn('mb-4 text-sm', theme.muted)}>{product.course.outcome}</p>
                                )}

                                <div className="space-y-4">
                                    {product.course.sections.map((section, i) => (
                                        <div key={i}>
                                            <p className="text-sm font-semibold">{section.title}</p>
                                            <ul className="mt-2 space-y-1.5">
                                                {section.lessons.map((lesson, j) => (
                                                    <li key={j} className={cn('flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm', theme.muted)}>
                                                        <PlayCircle className="size-4 shrink-0" />
                                                        <span className="min-w-0 flex-1 truncate">{lesson.title}</span>
                                                        {lesson.is_free_preview && (
                                                            <span className="shrink-0 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                                                PREVIEW
                                                            </span>
                                                        )}
                                                        <span className="shrink-0 text-xs">{lesson.duration_minutes}m</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            </Section>
                        )}

                        {product.event && (
                            <Section title="Detail acara" theme={theme}>
                                <ul className={cn('space-y-2.5 text-sm', theme.muted)}>
                                    <li className="flex items-center gap-2.5">
                                        <CalendarDays className="size-4.5 text-[var(--sf-primary)]" />
                                        {formatDate(product.event.starts_at, true)} WIB
                                    </li>
                                    <li className="flex items-center gap-2.5">
                                        <MapPin className="size-4.5 text-[var(--sf-primary)]" />
                                        {product.event.mode === 'online' ? 'Online' : (product.event.location ?? 'Offline')}
                                    </li>
                                    {product.event.seats_left !== null && (
                                        <li className="flex items-center gap-2.5">
                                            <Users className="size-4.5 text-[var(--sf-primary)]" />
                                            Sisa {product.event.seats_left} kursi
                                        </li>
                                    )}
                                </ul>
                            </Section>
                        )}

                        {product.service && (
                            <Section title="Detail sesi" theme={theme}>
                                <ul className={cn('space-y-2.5 text-sm', theme.muted)}>
                                    <li className="flex items-center gap-2.5">
                                        <Clock className="size-4.5 text-[var(--sf-primary)]" />
                                        {product.service.duration_minutes} menit per sesi · {product.service.timezone}
                                    </li>
                                </ul>
                                <p className={cn('mt-3 text-sm', theme.muted)}>
                                    Setelah bayar, kamu bisa pilih jadwal dari halaman pembelian.
                                </p>
                            </Section>
                        )}

                        {product.terms && (
                            <Section title="Syarat pembelian" theme={theme}>
                                <p className={cn('text-sm leading-relaxed', theme.muted)}>{product.terms}</p>
                            </Section>
                        )}
                    </div>

                    {/* Store rail */}
                    <aside className="space-y-3">
                        {vouchers.length > 0 && (
                            <Section title="Voucher Toko" theme={theme} dense>
                                <ul className="space-y-2">
                                    {vouchers.map((voucher) => (
                                        <li
                                            key={voucher.code}
                                            className="rounded border border-dashed border-[color-mix(in_oklab,var(--sf-primary)_40%,transparent)] bg-[color-mix(in_oklab,var(--sf-primary)_6%,transparent)] p-3"
                                        >
                                            <p className="flex items-center gap-1.5 text-[0.8125rem] font-semibold text-[var(--sf-primary)]">
                                                <Ticket className="size-3.5" /> {voucher.label}
                                            </p>
                                            <p className={cn('mt-1 text-xs', theme.muted)}>
                                                {voucher.min_order > 0 ? `Min. belanja ${formatIDR(voucher.min_order)}` : 'Tanpa minimum belanja'}
                                            </p>
                                            <p className="mt-1.5 text-xs">
                                                Kode <span className="font-mono font-semibold">{voucher.code}</span>
                                                {voucher.ends_at && <span className={theme.muted}> · s.d. {voucher.ends_at}</span>}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                                <p className={cn('mt-2 text-[0.6875rem]', theme.muted)}>Masukkan kodenya saat checkout.</p>
                            </Section>
                        )}

                        {related.length > 0 && (
                            <Section title={`Produk Pilihan ${store.name}`} theme={theme} dense>
                                <ul className="space-y-1">
                                    {related.slice(0, 5).map((item) => (
                                        <li key={item.id}>
                                            <Link
                                                href={`/${store.username}/p/${item.slug}`}
                                                className="flex gap-2.5 rounded p-1.5 transition-colors hover:bg-[color-mix(in_oklab,var(--sf-primary)_6%,transparent)]"
                                            >
                                                <span className="size-14 shrink-0 overflow-hidden rounded bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)]">
                                                    {item.thumbnail_url ? (
                                                        <img src={item.thumbnail_url} alt="" loading="lazy" className="size-full object-cover" />
                                                    ) : (
                                                        <span className="grid size-full place-items-center">
                                                            <ImageIcon className="size-4 opacity-40" />
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="line-clamp-2 text-xs leading-4">{item.name}</span>
                                                    <span className="mt-1 block text-[0.8125rem] font-semibold text-[var(--sf-primary)]">
                                                        {formatIDR(item.price)}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        )}
                    </aside>
                </div>

                {related.length > 0 && (
                    <section className="pt-4">
                        <div className="mb-3 flex items-center gap-3">
                            <span className="h-5 w-1 rounded-full bg-[var(--sf-primary)]" aria-hidden="true" />
                            <h2 className="text-base font-bold tracking-tight sm:text-lg">Produk lain dari {store.name}</h2>
                        </div>

                        <div className="@container grid grid-cols-2 gap-3 @3xl:grid-cols-3 @5xl:grid-cols-4">
                            {related.map((item) => (
                                <ProductCard
                                    key={item.id}
                                    product={item}
                                    theme={theme}
                                    onBuy={() => item.external_url ? window.open(item.external_url, '_blank', 'noopener,noreferrer') : setPurchaseChoice(item)}
                                    onAddToCart={() => addToCart(item)}
                                    onOpen={() => {
                                        window.location.href = `/${store.username}/p/${item.slug}`;
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </main>

            {/* Mobile action bar — chat, cart, buy, as on the marketplaces. */}
            <div className={cn('fixed inset-x-0 bottom-0 z-40 flex border-t bg-[var(--sf-card)] lg:hidden', theme.line)}>
                {!seller.is_own && (
                    <button
                        type="button"
                        onClick={() => setChatOpen(true)}
                        className={cn('flex w-16 shrink-0 flex-col items-center justify-center gap-0.5 border-r text-[0.625rem]', theme.line, theme.muted)}
                    >
                        <MessageCircle className="size-5" />
                        Chat
                    </button>
                )}

                {!product.external_url && (
                    <button
                        type="button"
                        onClick={() => setCartOpen(true)}
                        aria-label={cartCount > 0 ? `Buka keranjang, ${cartCount} item` : 'Buka keranjang'}
                        className={cn('relative flex w-16 shrink-0 flex-col items-center justify-center gap-0.5 border-r text-[0.625rem]', theme.line, theme.muted)}
                    >
                        <ShoppingBag className="size-5" />
                        Keranjang
                        {cartCount > 0 && (
                            <span className="absolute right-2 top-1.5 grid min-w-4 place-items-center rounded-full bg-[var(--sf-primary)] px-1 text-[0.5625rem] font-bold text-[var(--sf-on-primary)]">
                                {cartCount > 99 ? '99+' : cartCount}
                            </span>
                        )}
                    </button>
                )}

                {product.is_cartable && (
                    <button
                        type="button"
                        onClick={() => addToCart(product, variantId, quantity)}
                        disabled={needsVariant || !product.is_buyable}
                        className="flex-1 bg-[color-mix(in_oklab,var(--sf-primary)_18%,transparent)] px-3 py-3.5 text-[0.8125rem] font-semibold text-[var(--sf-primary)] disabled:opacity-40"
                    >
                        + Keranjang
                    </button>
                )}

                <button
                    type="button"
                    onClick={buy}
                    disabled={(!product.external_url && !product.is_buyable) || needsVariant}
                    className="flex-1 px-3 py-3.5 text-[0.8125rem] font-semibold disabled:opacity-50"
                    style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                >
                    {product.external_url ? product.external_provider || 'Marketplace' : product.is_buyable ? 'Beli Sekarang' : 'Stok Habis'}
                </button>
            </div>

            {checkout && (
                <CheckoutSheet
                    product={checkout}
                    variantId={checkout.id === product.id ? variantId : null}
                    quantity={checkout.id === product.id ? quantity : 1}
                    storeUsername={store.username}
                    isPreview={false}
                    theme={theme}
                    onClose={() => setCheckout(null)}
                />
            )}

            {purchaseChoice && (
                <PurchaseChoiceSheet
                    product={purchaseChoice}
                    storeName={store.name}
                    whatsapp={store.whatsapp}
                    theme={theme}
                    onBuyDirect={() => {
                        setCheckout(purchaseChoice);
                        setPurchaseChoice(null);
                    }}
                    onClose={() => setPurchaseChoice(null)}
                />
            )}

            {chatOpen && (
                <ChatSheet
                    storeUsername={store.username}
                    storeName={seller.name}
                    storeAvatar={seller.avatar_url}
                    productId={product.id}
                    productName={product.name}
                    whatsapp={seller.whatsapp}
                    theme={theme}
                    onClose={() => setChatOpen(false)}
                />
            )}

            {cartOpen && (
                <CartSheet
                    cart={cart}
                    storeUsername={store.username}
                    theme={theme}
                    onCheckout={() => {
                        setCartOpen(false);
                        router.visit(`/${store.username}/keranjang`);
                    }}
                    onClose={() => setCartOpen(false)}
                />
            )}

            {cartCheckout && (
                <CheckoutSheet
                    cart={cart}
                    storeUsername={store.username}
                    isPreview={false}
                    theme={theme}
                    onClose={() => setCartCheckout(false)}
                />
            )}
        </div>
    );
}

/**
 * The gallery.
 *
 * Arrows sit on the thumbnail strip rather than the main image: on a phone the
 * strip is the thing being scrolled, and an arrow floating over the photo
 * covers the product it is meant to be showing.
 */
function Gallery({
    images,
    active,
    onChange,
    discount,
    theme,
}: {
    images: { url: string; alt: string | null }[];
    active: number;
    onChange: (index: number) => void;
    discount: number;
    theme: Theme;
}) {
    const step = (delta: number) => onChange((active + delta + images.length) % images.length);

    return (
        <div>
            <div className="relative aspect-square w-full overflow-hidden rounded">
                {images.length > 0 ? (
                    <img src={images[active].url} alt={images[active].alt ?? ''} className="size-full object-cover" />
                ) : (
                    <span
                        className="grid size-full place-items-center"
                        style={{
                            background:
                                'linear-gradient(135deg, color-mix(in oklab, var(--sf-primary) 22%, transparent), color-mix(in oklab, var(--sf-accent) 26%, transparent))',
                        }}
                    >
                        <ImageIcon className="size-12 opacity-40" />
                    </span>
                )}

                {discount > 0 && (
                    <span className="absolute right-0 top-0 bg-rose-500 px-2 py-1 text-xs font-bold text-white">
                        -{discount}%
                    </span>
                )}
            </div>

            {images.length > 1 && (
                <div className="mt-2 flex items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => step(-1)}
                        className={cn('hidden size-7 shrink-0 place-items-center rounded border sm:grid', theme.line)}
                        aria-label="Gambar sebelumnya"
                    >
                        <ChevronLeft className="size-4" />
                    </button>

                    <div className="flex flex-1 gap-1.5 overflow-x-auto [scrollbar-width:none]">
                        {images.map((image, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => onChange(i)}
                                aria-label={`Gambar ${i + 1}`}
                                aria-current={i === active}
                                className={cn(
                                    'size-14 shrink-0 overflow-hidden rounded border-2 transition',
                                    i === active ? 'border-[var(--sf-primary)]' : 'border-transparent opacity-70 hover:opacity-100',
                                )}
                            >
                                <img src={image.url} alt="" loading="lazy" className="size-full object-cover" />
                            </button>
                        ))}
                    </div>

                    <button
                        type="button"
                        onClick={() => step(1)}
                        className={cn('hidden size-7 shrink-0 place-items-center rounded border sm:grid', theme.line)}
                        aria-label="Gambar berikutnya"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                </div>
            )}
        </div>
    );
}

/** A labelled row in the buy box, label column fixed so the rows line up. */
function Row({ label, theme, children }: { label: string; theme: Theme; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-1 sm:flex-row sm:gap-4">
            <dt className={cn('shrink-0 pt-1.5 sm:w-28', theme.muted)}>{label}</dt>
            <dd className="min-w-0 flex-1">{children}</dd>
        </div>
    );
}

function Stat({ label, value, theme }: { label: string; value: string; theme: Theme }) {
    return (
        <div>
            <dt className={cn('text-xs', theme.muted)}>{label}</dt>
            <dd className="mt-0.5 font-semibold text-[var(--sf-primary)]">{value}</dd>
        </div>
    );
}

function Spec({ label, value, theme }: { label: string; value: string | null; theme: Theme }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex gap-4 py-2.5">
            <dt className={cn('w-32 shrink-0', theme.muted)}>{label}</dt>
            <dd className="min-w-0 flex-1">{value}</dd>
        </div>
    );
}

function Section({
    title,
    theme,
    dense,
    children,
}: {
    title: string;
    theme: Theme;
    dense?: boolean;
    children: React.ReactNode;
}) {
    return (
        <section className={theme.card}>
            <h2 className={cn('border-b px-4 py-3 text-[0.9375rem] font-semibold sm:px-5', theme.line)}>{title}</h2>
            <div className={dense ? 'p-3 sm:p-4' : 'p-4 sm:p-5'}>{children}</div>
        </section>
    );
}

function Meta({ label }: { label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 font-semibold">
            <CheckCircle2 className="size-4 text-[var(--sf-primary)]" />
            {label}
        </span>
    );
}
