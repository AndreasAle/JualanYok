import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, CheckCircle2, Clock, ExternalLink, ImageIcon, MapPin, PlayCircle, ShieldCheck,
    Plus, ShoppingBag, Star, Truck, Users,
} from 'lucide-react';
import { useState } from 'react';
import { CartSheet } from '@/components/storefront/CartSheet';
import { CheckoutSheet } from '@/components/storefront/CheckoutSheet';
import { ProductCard } from '@/components/storefront/ProductCard';
import { buildStorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatDate, formatIDR, formatNumber } from '@/lib/utils';
import type { StorefrontStore } from '@/pages/Storefront/Show';
import type { CartPayload, StorefrontProduct } from '@/types';

interface DetailedProduct extends StorefrontProduct {
    description: string | null;
    terms: string | null;
    checkout_message: string | null;
    sales_count?: number;
    media: { url: string; alt: string | null }[];
    variants: { id: number; name: string; price: number; stock: number }[];
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

export default function StorefrontProductPage({
    store,
    product,
    related,
    cart,
}: {
    store: StorefrontStore;
    product: DetailedProduct;
    related: StorefrontProduct[];
    cart: CartPayload | null;
}) {
    const theme = buildStorefrontTheme(store.theme);
    const [checkout, setCheckout] = useState<StorefrontProduct | null>(null);
    const [cartOpen, setCartOpen] = useState(false);
    // Options must be chosen before buying: stock and price live on the variant.
    const [variantId, setVariantId] = useState<number | null>(
        product.variants.length === 1 ? product.variants[0].id : null,
    );
    const [cartCheckout, setCartCheckout] = useState(false);
    const [activeImage, setActiveImage] = useState(0);

    const images =
        product.media.length > 0
            ? product.media
            : product.thumbnail_url
              ? [{ url: product.thumbnail_url, alt: product.name }]
              : [];

    const selectedVariant = product.variants.find((v) => v.id === variantId) ?? null;
    const needsVariant = product.variants.length > 0 && !selectedVariant;

    const price = product.external_url
        ? product.price > 0 ? formatIDR(product.price) : 'Cek harga terbaru'
        : product.is_pay_what_you_want
        ? `Mulai ${formatIDR(product.minimum_price ?? 0)}`
        : formatIDR(selectedVariant?.price ?? product.price);

    const buy = () => {
        if (needsVariant) return;

        if (product.type === 'EXTERNAL') {
            if (product.external_url) window.location.assign(product.external_url);
            return;
        }

        setCheckout(product);
    };

    const addToCart = (item: StorefrontProduct, variant: number | null = null) => {
        router.post(
            `/${store.username}/keranjang`,
            { product_id: item.id, quantity: 1, ...(variant ? { variant_id: variant } : {}) },
            { preserveScroll: true, preserveState: true, onSuccess: () => setCartOpen(true) },
        );
    };

    const cartCount = cart?.item_count ?? 0;

    return (
        <div className="min-h-screen pb-24 lg:pb-0" style={theme.pageStyle}>
            <Head title={`${product.name} — ${store.name}`}>
                {product.short_description && <meta name="description" content={product.short_description} />}
                <meta property="og:title" content={product.name} />
                {product.thumbnail_url && <meta property="og:image" content={product.thumbnail_url} />}
            </Head>

            {/* Breadcrumb bar */}
            <div className={cn('border-b bg-[var(--sf-card)]', theme.line)}>
                <div className="mx-auto flex max-w-6xl items-center gap-2 px-4 py-3 text-sm sm:px-6">
                    <Link
                        href={`/${store.username}`}
                        className={cn('inline-flex items-center gap-1.5 font-semibold hover:text-[var(--sf-primary)]')}
                    >
                        <ArrowLeft className="size-4" />
                        {store.name}
                    </Link>
                    <span className={theme.muted}>/</span>
                    <span className={cn('truncate', theme.muted)}>{product.name}</span>
                </div>
            </div>

            <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-8">
                    {/* Left: gallery + details */}
                    <div className="space-y-6">
                        <div className={cn(theme.card, 'overflow-hidden')}>
                            <div className="relative aspect-square w-full sm:aspect-4/3">
                                {images.length > 0 ? (
                                    <img
                                        src={images[activeImage].url}
                                        alt={images[activeImage].alt ?? ''}
                                        className="size-full object-cover"
                                    />
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

                                {product.discount_percent > 0 && (
                                    <span className="absolute left-0 top-4 rounded-r-full bg-rose-500 py-1.5 pl-3 pr-4 text-sm font-extrabold text-white shadow">
                                        Hemat {product.discount_percent}%
                                    </span>
                                )}
                            </div>

                            {images.length > 1 && (
                                <div className="flex gap-2 overflow-x-auto p-3">
                                    {images.map((image, i) => (
                                        <button
                                            key={i}
                                            type="button"
                                            onClick={() => setActiveImage(i)}
                                            aria-label={`Gambar ${i + 1}`}
                                            className={cn(
                                                'size-16 shrink-0 overflow-hidden rounded-lg border-2 transition-all',
                                                i === activeImage
                                                    ? 'border-[var(--sf-primary)]'
                                                    : 'border-transparent opacity-60 hover:opacity-100',
                                            )}
                                        >
                                            <img src={image.url} alt="" className="size-full object-cover" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {product.description && (
                            <Section title="Deskripsi" theme={theme}>
                                <div className={cn('space-y-3 text-[15px] leading-relaxed', theme.muted)}>
                                    {product.description
                                        .split('\n')
                                        .filter(Boolean)
                                        .map((paragraph, i) => (
                                            <p key={i}>{paragraph}</p>
                                        ))}
                                </div>
                            </Section>
                        )}

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
                                            <p className="text-sm font-bold">{section.title}</p>
                                            <ul className="mt-2 space-y-1.5">
                                                {section.lessons.map((lesson, j) => (
                                                    <li
                                                        key={j}
                                                        className={cn(
                                                            'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm',
                                                            theme.muted,
                                                        )}
                                                    >
                                                        <PlayCircle className="size-4 shrink-0" />
                                                        <span className="min-w-0 flex-1 truncate">{lesson.title}</span>
                                                        {lesson.is_free_preview && (
                                                            <span className="shrink-0 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                                                PREVIEW
                                                            </span>
                                                        )}
                                                        <span className="shrink-0 text-xs">
                                                            {lesson.duration_minutes}m
                                                        </span>
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
                                        {product.event.mode === 'online'
                                            ? 'Online'
                                            : (product.event.location ?? 'Offline')}
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

                    {/* Right: sticky buy box */}
                    <aside className="lg:sticky lg:top-6">
                        <div className={cn(theme.card, 'p-5 sm:p-6')}>
                            <span className="inline-flex w-fit items-center rounded-md bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-[var(--sf-primary)]">
                                {product.type_label}
                            </span>

                            <h1 className="mt-3 text-xl font-extrabold leading-snug text-balance sm:text-2xl">
                                {product.name}
                            </h1>

                            {product.short_description && (
                                <p className={cn('mt-2 text-sm leading-relaxed', theme.muted)}>
                                    {product.short_description}
                                </p>
                            )}

                            {!product.external_url && (product.sales_count ?? 0) > 0 && (
                                <p className={cn('mt-3 flex items-center gap-1.5 text-xs', theme.muted)}>
                                    <Star className="size-3.5 fill-amber-400 text-amber-400" />
                                    {formatNumber(product.sales_count!)} terjual
                                </p>
                            )}

                            <div className={cn('my-5 border-t', theme.line)} />

                            <p className="text-3xl font-black text-[var(--sf-primary)]">{price}</p>
                            {!product.external_url && product.compare_at_price && (
                                <p className={cn('mt-0.5 text-sm line-through', theme.muted)}>
                                    {formatIDR(product.compare_at_price)}
                                </p>
                            )}

                            {product.variants.length > 0 && (
                                <fieldset className="mt-5">
                                    <legend className="mb-2 text-sm font-bold">
                                        Pilih varian
                                        <span className="ml-0.5 text-rose-500">*</span>
                                    </legend>
                                    <div className="flex flex-wrap gap-2">
                                        {product.variants.map((variant) => {
                                            const habis = variant.stock !== null && variant.stock <= 0;

                                            return (
                                                <button
                                                    key={variant.id}
                                                    type="button"
                                                    onClick={() => setVariantId(variant.id)}
                                                    disabled={habis}
                                                    aria-pressed={variantId === variant.id}
                                                    className={cn(
                                                        'rounded-xl border px-4 py-2.5 text-sm font-bold transition',
                                                        variantId === variant.id
                                                            ? 'border-[var(--sf-primary)] bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] text-[var(--sf-primary)]'
                                                            : 'border-[var(--sf-line)] hover:border-[var(--sf-primary)]',
                                                        habis && 'cursor-not-allowed line-through opacity-40',
                                                    )}
                                                >
                                                    {variant.name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {needsVariant && (
                                        <p className={cn('mt-2 text-xs', theme.muted)}>
                                            Pilih dulu variannya sebelum lanjut.
                                        </p>
                                    )}
                                </fieldset>
                            )}

                            <button
                                type="button"
                                onClick={buy}
                                disabled={(!product.external_url && !product.is_buyable) || needsVariant}
                                className={cn(theme.btnPrimary, 'mt-5 h-13 w-full px-6 text-base shadow-md')}
                            >
                                {product.external_url ? <ExternalLink className="size-5" /> : <ShoppingBag className="size-5" />}
                                {product.external_url
                                    ? product.external_cta || `Beli di ${product.external_provider || 'Marketplace'}`
                                    : product.is_buyable
                                      ? 'Beli Sekarang'
                                      : 'Stok Habis'}
                            </button>

                            {product.is_cartable && (
                                <button
                                    type="button"
                                    onClick={() => addToCart(product, variantId)}
                                    disabled={needsVariant}
                                    className="mt-2.5 flex h-13 w-full items-center justify-center gap-2 rounded-xl border border-[var(--sf-line)] px-6 text-base font-bold transition hover:border-[var(--sf-primary)] hover:text-[var(--sf-primary)] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <Plus className="size-5" />
                                    Masukkan Keranjang
                                </button>
                            )}

                            {product.external_url ? (
                                <div className={cn('mt-5 rounded-xl border p-4 text-xs leading-5', theme.line, theme.muted)}>
                                    <p className="flex items-center gap-2 font-bold text-[var(--sf-fg)]"><ExternalLink className="size-4 text-[var(--sf-primary)]" /> Pembelian dilanjutkan di {product.external_provider || 'marketplace'}.</p>
                                    <p className="mt-1">Harga, stok, pembayaran, pengiriman, dan refund mengikuti kebijakan marketplace tujuan.</p>
                                </div>
                            ) : (
                                <ul className={cn('mt-5 space-y-2.5 text-xs', theme.muted)}>
                                    <li className="flex items-center gap-2"><ShieldCheck className="size-4 shrink-0 text-emerald-500" /> Pembayaran aman lewat JualanYok</li>
                                    <li className="flex items-center gap-2"><CheckCircle2 className="size-4 shrink-0 text-emerald-500" /> Produk digital dikirim otomatis</li>
                                    <li className="flex items-center gap-2"><Truck className="size-4 shrink-0 text-emerald-500" /> Bisa ajukan refund sesuai kebijakan</li>
                                </ul>
                            )}
                        </div>

                        {/* Seller card */}
                        <Link
                            href={`/${store.username}`}
                            className={cn(theme.card, 'mt-4 flex items-center gap-3 p-4 transition-shadow hover:shadow-lg')}
                        >
                            <span className="size-11 shrink-0 overflow-hidden rounded-xl">
                                {store.avatar_url ? (
                                    <img src={store.avatar_url} alt="" className="size-full object-cover" />
                                ) : (
                                    <span
                                        className="grid size-full place-items-center font-black"
                                        style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                                    >
                                        {store.name[0]?.toUpperCase()}
                                    </span>
                                )}
                            </span>

                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-bold">{store.name}</span>
                                <span className={cn('block truncate text-xs', theme.muted)}>@{store.username}</span>
                            </span>

                            <span className="shrink-0 text-xs font-bold text-[var(--sf-primary)]">Kunjungi</span>
                        </Link>
                    </aside>
                </div>

                {related.length > 0 && (
                    <section className="mt-10">
                        <div className="mb-4 flex items-center gap-3">
                            <span className="h-5 w-1 rounded-full bg-[var(--sf-primary)]" aria-hidden="true" />
                            <h2 className="text-lg font-extrabold tracking-tight sm:text-xl">
                                Produk lain dari {store.name}
                            </h2>
                        </div>

                        <div className="@container grid grid-cols-2 gap-3 @3xl:grid-cols-3 @5xl:grid-cols-4">
                            {related.map((item) => (
                                <ProductCard
                                    key={item.id}
                                    product={item}
                                    theme={theme}
                                    onBuy={() => item.external_url ? window.open(item.external_url, '_blank', 'noopener,noreferrer') : setCheckout(item)}
                                    onAddToCart={() => addToCart(item)}
                                    onOpen={() => {
                                        if (item.external_url) {
                                            window.open(item.external_url, '_blank', 'noopener,noreferrer');
                                            return;
                                        }

                                        window.location.href = `/${store.username}/p/${item.slug}`;
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </main>

            {/* Mobile sticky buy bar */}
            <div
                className={cn(
                    'fixed inset-x-0 bottom-0 z-40 border-t bg-[var(--sf-card)] p-3 shadow-lg lg:hidden',
                    theme.line,
                )}
            >
                <div className="mx-auto flex max-w-6xl items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p className={cn('truncate text-[11px]', theme.muted)}>{product.name}</p>
                        <p className="text-lg font-extrabold text-[var(--sf-primary)]">{price}</p>
                    </div>

                    {!product.external_url && (
                        <button
                            type="button"
                            onClick={() => setCartOpen(true)}
                            aria-label={cartCount > 0 ? `Buka keranjang, ${cartCount} item` : 'Buka keranjang'}
                            className="relative grid size-12 shrink-0 place-items-center rounded-xl border border-[var(--sf-line)] transition hover:border-[var(--sf-primary)]"
                        >
                            <ShoppingBag className="size-5" />
                            {cartCount > 0 && (
                                <span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-[var(--sf-primary)] px-1 text-[10px] font-black text-[var(--sf-on-primary)]">
                                    {cartCount > 99 ? '99+' : cartCount}
                                </span>
                            )}
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={buy}
                        disabled={(!product.external_url && !product.is_buyable) || needsVariant}
                        className={cn(theme.btnPrimary, 'h-12 shrink-0 px-6 text-sm')}
                    >
                        {product.external_url ? <ExternalLink className="size-4" /> : <ShoppingBag className="size-4" />}
                        {product.external_url ? product.external_provider || 'Marketplace' : product.is_buyable ? 'Beli' : 'Habis'}
                    </button>
                </div>
            </div>

            {checkout && (
                <CheckoutSheet
                    product={checkout}
                    variantId={checkout.id === product.id ? variantId : null}
                    storeUsername={store.username}
                    isPreview={false}
                    theme={theme}
                    onClose={() => setCheckout(null)}
                />
            )}

            {cartOpen && (
                <CartSheet
                    cart={cart}
                    storeUsername={store.username}
                    theme={theme}
                    onCheckout={() => {
                        setCartOpen(false);
                        setCartCheckout(true);
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

function Section({
    title,
    theme,
    children,
}: {
    title: string;
    theme: ReturnType<typeof buildStorefrontTheme>;
    children: React.ReactNode;
}) {
    return (
        <section className={cn(theme.card, 'p-5 sm:p-6')}>
            <h2 className="mb-4 text-base font-extrabold sm:text-lg">{title}</h2>
            {children}
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
