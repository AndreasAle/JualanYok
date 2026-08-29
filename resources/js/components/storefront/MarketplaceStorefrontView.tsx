import {
    ArrowDown,
    BadgeCheck,
    Check,
    ChevronRight,
    ExternalLink,
    Heart,
    PackageCheck,
    Search,
    Share2,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    Store,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BlockRenderer } from '@/components/storefront/BlockRenderer';
import { ProductCard } from '@/components/storefront/ProductCard';
import { buildStorefrontTheme, type StorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatNumber } from '@/lib/utils';
import type { StorefrontBlock, StorefrontProduct, StoreTheme } from '@/types';

export interface StorefrontStore {
    id: number;
    username: string;
    name: string;
    tagline: string | null;
    bio: string | null;
    avatar_url: string | null;
    cover_url: string | null;
    socials: Record<string, string>;
    whatsapp: string | null;
    seo_title?: string;
    seo_description?: string | null;
    show_branding: boolean;
    public_url: string;
    template_slug?: string | null;
    theme: Partial<StoreTheme>;
}

interface StoreExperience {
    label: string;
    discovery: string;
    trust: [string, string, string];
}

const EXPERIENCES: Record<string, StoreExperience> = {
    'creator-digital': { label: 'Peralatan kreator', discovery: 'Produk digital pilihan untuk bantu kamu berkarya lebih konsisten.', trust: ['Akses instan', 'File aman', 'Pembaruan produk'] },
    'freelancer-jasa': { label: 'Studio kreatif', discovery: 'Pilih layanan, cek portofolio, lalu mulai proyek dengan alur yang jelas.', trust: ['Brief terarah', 'Jadwal jelas', 'Bantuan langsung'] },
    'kelas-online': { label: 'Pusat belajar', discovery: 'Kelas praktis dengan materi terstruktur dan akses yang mudah.', trust: ['Akses selamanya', 'Materi terarah', 'Sertifikat kelas'] },
    'fashion-fisik': { label: 'Toko resmi', discovery: 'Koleksi terkurasi, varian lengkap, dan pembayaran yang nyaman.', trust: ['Stok terpantau', 'Pembayaran aman', 'Pengiriman cepat'] },
    'affiliate-creator': { label: 'Pilihan terkurasi', discovery: 'Rekomendasi jujur dan produk pilihan yang sudah dikurasi.', trust: ['Sudah dikurasi', 'Tautan transparan', 'Harga mitra'] },
    'food-beverage': { label: 'Pesan daring', discovery: 'Lihat menu favorit, promo terbaru, dan pesan tanpa antre.', trust: ['Dibuat segar', 'Pesan cepat', 'Bantuan WhatsApp'] },
    'minimal-link': { label: 'Profil kreator', discovery: 'Semua karya, produk, dan cara terhubung dalam satu tempat.', trust: ['Tautan resmi', 'Produk pilihan', 'Kontak langsung'] },
};

const FALLBACK_EXPERIENCE: StoreExperience = { label: 'Toko resmi', discovery: 'Temukan produk pilihan dan selesaikan pembelian dengan nyaman.', trust: ['Produk pilihan', 'Pembayaran aman', 'Bantuan langsung'] };
const PRODUCT_BLOCKS = new Set(['PRODUCT', 'PRODUCT_COLLECTION', 'FEATURED_PRODUCTS', 'AFFILIATE_PRODUCT']);

/** Marketplace storefront shared by the public page and the live builder preview. */
export function StorefrontView({ store, blocks, isPreview, theme, onBuy, onAddToCart, cartCount = 0, onOpenCart }: { store: StorefrontStore; blocks: StorefrontBlock[]; isPreview: boolean; theme?: StorefrontTheme; onBuy: (product: StorefrontProduct) => void; onAddToCart?: (product: StorefrontProduct) => void; cartCount?: number; onOpenCart?: () => void }) {
    const t = theme ?? buildStorefrontTheme(store.theme);
    const experience = EXPERIENCES[store.template_slug ?? ''] ?? FALLBACK_EXPERIENCE;
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');

    const allProducts = useMemo(() => {
        const unique = new Map<number, StorefrontProduct>();
        blocks.forEach((block) => ((block.content?.products ?? []) as StorefrontProduct[]).forEach((product) => unique.set(product.id, product)));
        return Array.from(unique.values());
    }, [blocks]);

    const affiliateMode = store.template_slug === 'affiliate-creator'
        || (allProducts.length > 0 && allProducts.every((product) => product.type === 'EXTERNAL'));
    const hasMixedCatalog = allProducts.some((product) => product.type === 'EXTERNAL')
        && allProducts.some((product) => product.type !== 'EXTERNAL');

    const productCategories = useMemo(() => {
        const types = new Map<string, string>();
        allProducts.forEach((product) => {
            if (affiliateMode) {
                const provider = product.external_provider || 'Marketplace';
                types.set(provider, provider);
                return;
            }

            types.set(product.type, product.type_label);
        });
        return Array.from(types.entries());
    }, [affiliateMode, allProducts]);

    const matchesCategory = (product: StorefrontProduct) => category === 'all'
        || (affiliateMode ? (product.external_provider || 'Marketplace') === category : product.type === category);

    const visibleBlocks = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase('id');
        if (!hasMixedCatalog && !needle && category === 'all') return blocks;

        return blocks
            .map((block) => {
                if (!PRODUCT_BLOCKS.has(block.type)) return block;
                const products = ((block.content?.products ?? []) as StorefrontProduct[]).filter((product) => {
                    const haystack = `${product.name} ${product.short_description ?? ''} ${product.type_label}`.toLocaleLowerCase('id');
                    const belongsInPrimaryCatalog = !hasMixedCatalog || product.type !== 'EXTERNAL';
                    return belongsInPrimaryCatalog && matchesCategory(product) && (!needle || haystack.includes(needle));
                });
                return { ...block, content: { ...block.content, products } };
            })
            .filter((block) => !PRODUCT_BLOCKS.has(block.type) || ((block.content?.products ?? []) as StorefrontProduct[]).length > 0);
    }, [affiliateMode, blocks, category, hasMixedCatalog, query]);

    const matchingProducts = allProducts.filter((product) => {
        const needle = query.trim().toLocaleLowerCase('id');
        const haystack = `${product.name} ${product.short_description ?? ''} ${product.type_label}`.toLocaleLowerCase('id');
        return matchesCategory(product) && (!needle || haystack.includes(needle));
    });
    const marketplaceProducts = matchingProducts.filter((product) => product.type === 'EXTERNAL');

    return (
        <div className="@container min-h-full" style={t.pageStyle}>
            <MarketplaceBar store={store} experience={experience} query={query} onQueryChange={setQuery} cartCount={cartCount} onOpenCart={onOpenCart} affiliateMode={affiliateMode} />
            <StoreHeader store={store} theme={t} isPreview={isPreview} experience={experience} products={allProducts} affiliateMode={affiliateMode} />
            {allProducts.length > 0 && <CategoryRail categories={productCategories} active={category} onChange={setCategory} productCount={allProducts.length} affiliateMode={affiliateMode} />}

            <main id="store-content" className={cn('mx-auto max-w-6xl px-4 pb-20 sm:px-6', affiliateMode ? 'pt-6 sm:pt-8' : 'pt-8 sm:pt-10')}>
                {(query || category !== 'all') && (
                    <div className="mb-6 flex items-center justify-between gap-4">
                        <p className={cn('text-sm', t.muted)}><span className="font-extrabold text-current">{matchingProducts.length}</span> produk ditemukan{query && <> untuk “{query}”</>}</p>
                        <button type="button" onClick={() => { setQuery(''); setCategory('all'); }} className="text-xs font-extrabold text-[var(--sf-primary)]">Reset filter</button>
                    </div>
                )}

                {blocks.length === 0 ? (
                    <div className={cn(t.card, 'p-12 text-center')}><Store className="mx-auto size-10 opacity-30" /><p className="mt-4 text-lg font-bold">Tokonya masih kosong</p><p className={cn('mx-auto mt-1.5 max-w-sm text-sm', t.muted)}>{isPreview ? 'Tambah block dari editor buat mulai mengisi halaman ini.' : 'Pemilik toko lagi menyiapkan isinya. Mampir lagi nanti ya.'}</p></div>
                ) : matchingProducts.length === 0 && (query || category !== 'all') ? (
                    <div className={cn(t.card, 'px-6 py-16 text-center')}><Search className="mx-auto size-9 opacity-25" /><p className="mt-4 font-extrabold">Produk belum ditemukan</p><p className={cn('mt-1 text-sm', t.muted)}>Coba kata kunci atau kategori lainnya.</p></div>
                ) : (
                    <div className={t.sectionSpacing}>
                        {visibleBlocks.map((block) => <BlockRenderer key={block.id} block={block} ctx={{ storeUsername: store.username, storeName: store.name, theme: t, productLayout: affiliateMode ? 'grid' : store.theme.product_layout ?? 'grid', affiliateMode, isPreview, onBuy, onAddToCart: affiliateMode ? undefined : onAddToCart }} />)}
                        {hasMixedCatalog && marketplaceProducts.length > 0 && (
                            <section className={cn(t.card, 'overflow-hidden p-4 sm:p-6')} aria-labelledby="marketplace-picks-title">
                                <div className="mb-5 flex flex-col justify-between gap-3 border-b border-[var(--sf-line)] pb-4 sm:flex-row sm:items-end">
                                    <div>
                                        <p className="text-[10px] font-black uppercase tracking-[.18em] text-[var(--sf-primary)]">Rekomendasi marketplace</p>
                                        <h2 id="marketplace-picks-title" className="mt-1 text-xl font-black tracking-tight sm:text-2xl">Pilihan dari marketplace</h2>
                                        <p className={cn('mt-1 max-w-2xl text-sm leading-6', t.muted)}>Produk affiliate dipisahkan dari produk toko. Harga, stok, pembayaran, dan pengiriman mengikuti marketplace tujuan.</p>
                                    </div>
                                    <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-extrabold text-amber-800"><ExternalLink className="size-3.5" /> Tautan affiliate</span>
                                </div>
                                <div className={cn(store.theme.product_layout === 'list' ? 'space-y-3' : 'grid grid-cols-2 gap-2.5 @2xl:gap-4 @3xl:grid-cols-3 @5xl:grid-cols-4')}>
                                    {marketplaceProducts.map((product) => (
                                        <ProductCard
                                            key={`marketplace-${product.id}`}
                                            product={product}
                                            theme={t}
                                            layout={store.theme.product_layout === 'list' ? 'list' : 'grid'}
                                            onBuy={() => onBuy(product)}
                                            onOpen={() => {
                                                if (!isPreview) window.location.assign(`/${store.username}/p/${product.slug}`);
                                            }}
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                )}

                {store.show_branding && <footer className="mt-16 border-t border-[var(--sf-line)] pt-8 text-center"><a href="/" className="inline-flex items-center gap-2 text-xs font-semibold opacity-70 transition-opacity hover:opacity-100">Commerce powered by <span className="font-black text-[var(--sf-primary)]">JualanYok</span></a></footer>}
            </main>
        </div>
    );
}

function MarketplaceBar({ store, experience, query, onQueryChange, cartCount, onOpenCart, affiliateMode }: { store: StorefrontStore; experience: StoreExperience; query: string; onQueryChange: (value: string) => void; cartCount: number; onOpenCart?: () => void; affiliateMode: boolean }) {
    return (
        <div className="sticky top-0 z-40 border-b border-[var(--sf-line)] bg-[var(--sf-card)]/90 backdrop-blur-xl">
            <div className="mx-auto max-w-6xl px-4 sm:px-6">
                <div className="flex h-[4.5rem] items-center gap-3">
                    <a href="#store-top" className={cn('flex min-w-0 items-center gap-3', affiliateMode ? 'flex-1' : 'shrink-0 max-w-[9rem] @xl:max-w-[13rem]')}>
                        <span className={cn('shrink-0 overflow-hidden border border-[var(--sf-line)] bg-white shadow-sm', affiliateMode ? 'size-11 rounded-2xl' : 'size-9 rounded-xl')}>{store.avatar_url ? <img src={store.avatar_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center bg-[var(--sf-primary)] text-sm font-black text-[var(--sf-on-primary)]">{store.name[0]}</span>}</span>
                        <span className="min-w-0"><b className={cn('block truncate leading-tight', affiliateMode ? 'text-[15px] font-black' : 'text-sm')}>{store.name}</b><small className="mt-0.5 block truncate text-[9px] font-black uppercase leading-tight tracking-[.14em] text-[var(--sf-primary)]">{affiliateMode ? 'Etalase rekomendasi' : experience.label}</small></span>
                    </a>
                    <SearchField query={query} onQueryChange={onQueryChange} className="hidden min-w-0 flex-1 @xl:block @3xl:max-w-md" placeholder={affiliateMode ? 'Cari rekomendasi produk' : 'Cari produk di toko ini'} />
                    <div className="ml-auto flex shrink-0 items-center gap-2">
                    <span className="hidden items-center gap-1.5 rounded-full bg-[var(--sf-badge)] px-3 py-2 text-[11px] font-bold text-[var(--sf-on-badge)] @3xl:inline-flex">{affiliateMode ? <Heart className="size-3.5" /> : <ShieldCheck className="size-3.5" />} {affiliateMode ? 'Dipilih kreator' : 'Belanja aman'}</span>
                    {affiliateMode ? (
                        <a href="#store-content" className="inline-flex h-10 items-center gap-1.5 rounded-full border border-[var(--sf-line)] bg-[var(--sf-card)] px-3.5 text-[11px] font-black transition hover:border-[var(--sf-primary)] hover:text-[var(--sf-primary)]" aria-label="Lihat rekomendasi produk"><Sparkles className="size-3.5" /><span className="hidden @xs:inline">Katalog</span><ArrowDown className="size-3" /></a>
                    ) : onOpenCart ? (
                        <button type="button" onClick={onOpenCart} className="relative grid size-10 shrink-0 place-items-center rounded-full border border-[var(--sf-line)] bg-[var(--sf-card)] transition hover:border-[var(--sf-primary)]" aria-label={cartCount > 0 ? `Buka keranjang, ${cartCount} item` : 'Buka keranjang'}>
                            <ShoppingBag className="size-4.5" />
                            {cartCount > 0 && <span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-[var(--sf-primary)] px-1 text-[10px] font-black text-[var(--sf-on-primary)]">{cartCount > 99 ? '99+' : cartCount}</span>}
                        </button>
                    ) : (
                        <a href="#store-content" className="grid size-10 shrink-0 place-items-center rounded-full border border-[var(--sf-line)] bg-[var(--sf-card)]" aria-label="Lihat katalog"><ShoppingBag className="size-4.5" /></a>
                    )}
                    </div>
                </div>
                <SearchField query={query} onQueryChange={onQueryChange} className="block pb-3 @xl:hidden" placeholder={affiliateMode ? 'Cari di etalase ini' : 'Cari produk, kelas, atau layanan'} />
            </div>
        </div>
    );
}

function SearchField({ query, onQueryChange, className, placeholder }: { query: string; onQueryChange: (value: string) => void; className?: string; placeholder: string }) {
    return (
        <label className={cn('block', className)}>
            <span className="relative block">
                <span className="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-11 items-center justify-center" aria-hidden="true">
                    <Search className="size-[18px] stroke-[1.8] text-[var(--sf-muted)]" />
                </span>
                <input value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder={placeholder} className="h-10 w-full rounded-full border border-[var(--sf-line)] bg-transparent pl-11 pr-4 text-sm outline-none transition placeholder:text-[var(--sf-muted)] focus:border-[var(--sf-primary)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--sf-primary)_16%,transparent)]" />
            </span>
        </label>
    );
}

function StoreHeader({ store, theme, isPreview, experience, products, affiliateMode }: { store: StorefrontStore; theme: StorefrontTheme; isPreview: boolean; experience: StoreExperience; products: StorefrontProduct[]; affiliateMode: boolean }) {
    const [copied, setCopied] = useState(false);
    const totalSales = products.reduce((total, product) => total + (product.sales_count ?? 0), 0);
    const providerCount = new Set(products.map((product) => product.external_provider).filter(Boolean)).size;
    const share = async () => {
        if (isPreview) return;
        if (navigator.share) { try { await navigator.share({ title: store.name, url: store.public_url }); return; } catch { /* dismissed */ } }
        await navigator.clipboard.writeText(store.public_url); setCopied(true); setTimeout(() => setCopied(false), 2000);
    };

    if (affiliateMode) {
        return (
            <header id="store-top" className="mx-auto max-w-6xl px-4 pt-4 sm:px-6 sm:pt-6">
                <div className={cn(theme.card, 'overflow-hidden rounded-[1.75rem] shadow-[0_20px_60px_rgba(20,18,35,.10)] sm:rounded-[2.25rem]')}>
                    <div className="relative h-36 overflow-hidden sm:h-48" style={store.cover_url ? { background: `center / cover no-repeat url(${store.cover_url})` } : theme.coverStyle}>
                        <div className="absolute inset-0 bg-black/25" />
                        <div className="absolute -right-8 -top-12 size-40 rounded-full border-[28px] border-white/10" aria-hidden="true" />
                        <span className="absolute left-5 top-5 inline-flex items-center gap-2 rounded-full border border-white/25 bg-black/25 px-3 py-1.5 text-[9px] font-black uppercase tracking-[.15em] text-white backdrop-blur"><Heart className="size-3.5 fill-white" /> Rekomendasi pilihan</span>
                        <button type="button" onClick={share} className="absolute right-5 top-5 grid size-10 place-items-center rounded-full border border-white/25 bg-black/25 text-white backdrop-blur transition hover:bg-black/40" aria-label="Bagikan etalase">{copied ? <Check className="size-4" /> : <Share2 className="size-4" />}</button>
                    </div>

                    <div className="relative px-5 pb-5 sm:px-8 sm:pb-8">
                        <div className="-mt-10 flex items-end justify-between gap-4 sm:-mt-12">
                            <span className="size-20 shrink-0 overflow-hidden rounded-[1.4rem] border-4 border-[var(--sf-card)] bg-[var(--sf-card)] shadow-xl sm:size-24">{store.avatar_url ? <img src={store.avatar_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center bg-[var(--sf-primary)] text-2xl font-black text-[var(--sf-on-primary)]">{store.name[0]?.toUpperCase()}</span>}</span>
                            <a href="#store-content" className="mb-1 inline-flex h-10 items-center gap-2 rounded-full bg-[var(--sf-primary)] px-4 text-xs font-black text-[var(--sf-on-primary)] shadow-md transition hover:-translate-y-0.5">Lihat produk <ArrowDown className="size-3.5" /></a>
                        </div>

                        <div className="mt-4 flex items-center gap-2"><h1 className="min-w-0 truncate text-2xl font-black tracking-[-.04em] sm:text-3xl">{store.name}</h1><BadgeCheck className="size-5 shrink-0 fill-[var(--sf-primary)] text-[var(--sf-card)]" aria-label="Profil terverifikasi" /></div>
                        <p className={cn('mt-0.5 text-xs font-bold', theme.muted)}>@{store.username}</p>
                        {store.tagline && <p className="mt-3 text-[15px] font-black leading-6 sm:text-lg">{store.tagline}</p>}
                        {store.bio && <p className={cn('mt-1.5 max-w-2xl text-sm leading-6', theme.muted)}>{store.bio}</p>}

                        <div className="mt-5 grid grid-cols-3 gap-2">
                            <AffiliateStat value={String(products.length)} label="rekomendasi" />
                            <AffiliateStat value={String(Math.max(providerCount, 1))} label="marketplace" />
                            <AffiliateStat value="Terpilih" label="kurasi kreator" />
                        </div>

                        <div className="mt-4 flex items-start gap-3 rounded-2xl border border-[color-mix(in_oklab,var(--sf-primary)_18%,var(--sf-line))] bg-[color-mix(in_oklab,var(--sf-primary)_7%,transparent)] p-4">
                            <ExternalLink className="mt-0.5 size-4 shrink-0 text-[var(--sf-primary)]" />
                            <p className={cn('text-[11px] leading-5', theme.muted)}><b className="text-[var(--sf-fg)]">Transparan soal tautan.</b> Beberapa rekomendasi dapat memakai link affiliate. Harga pembeli tetap mengikuti marketplace tujuan.</p>
                        </div>
                    </div>
                </div>
            </header>
        );
    }

    return (
        <header id="store-top" className="mx-auto max-w-6xl px-4 pt-4 sm:px-6 sm:pt-6">
            <div className="relative h-40 overflow-hidden rounded-[1.5rem] sm:h-56 sm:rounded-[2rem]" style={store.cover_url ? { background: `center / cover no-repeat url(${store.cover_url})` } : theme.coverStyle}>
                <div className="absolute inset-0 bg-gradient-to-r from-black/65 via-black/25 to-transparent" /><div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/45 to-transparent" />
                <div className="relative flex h-full max-w-xl flex-col justify-between p-5 text-white sm:p-8"><span className="inline-flex w-fit items-center gap-1.5 rounded-full border border-white/20 bg-black/20 px-3 py-1.5 text-[10px] font-black uppercase tracking-[.12em] backdrop-blur-md"><Sparkles className="size-3" /> {experience.label}</span><div className="hidden sm:block"><p className="text-2xl font-black leading-tight tracking-[-.03em]">Belanja langsung dari brand favoritmu.</p><p className="mt-1 max-w-md text-sm text-white/75">{experience.discovery}</p></div></div>
            </div>
            <div className={cn(theme.card, 'relative mx-2 -mt-10 rounded-[1.6rem] p-5 sm:mx-5 sm:-mt-14 sm:rounded-[2rem] sm:p-7')}>
                <div className="flex flex-col gap-5 @3xl:flex-row @3xl:items-start @3xl:gap-7">
                    <div className="size-20 shrink-0 overflow-hidden rounded-2xl border-4 border-[var(--sf-card)] bg-[var(--sf-card)] shadow-lg sm:size-24">{store.avatar_url ? <img src={store.avatar_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center bg-[var(--sf-primary)] text-3xl font-black text-[var(--sf-on-primary)]">{store.name[0]?.toUpperCase()}</span>}</div>
                    <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h1 className="text-2xl font-black tracking-[-.035em] sm:text-3xl">{store.name}</h1><BadgeCheck className="size-5 fill-[var(--sf-primary)] text-[var(--sf-card)]" aria-label="Toko terverifikasi" /></div><p className={cn('mt-0.5 text-sm font-semibold', theme.muted)}>@{store.username}</p>{store.tagline && <p className="mt-3 text-[15px] font-extrabold sm:text-base">{store.tagline}</p>}{store.bio && <p className={cn('mt-1.5 max-w-2xl text-sm leading-6', theme.muted)}>{store.bio}</p>}<div className="mt-4 flex flex-wrap gap-2"><InfoPill icon={<PackageCheck />} label={`${products.length} produk`} />{totalSales > 0 && <InfoPill icon={<ShoppingBag />} label={`${formatNumber(totalSales)} terjual`} />}<InfoPill icon={<ShieldCheck />} label="Terverifikasi" /></div></div>
                    <button type="button" onClick={share} className={cn(theme.btnPrimary, 'h-11 shrink-0 px-5 text-sm shadow-md')}>{copied ? <Check className="size-4" /> : <Share2 className="size-4" />}{copied ? 'Tersalin!' : 'Bagikan toko'}</button>
                </div>
                <div className="mt-5 grid grid-cols-3 gap-2 border-t border-[var(--sf-line)] pt-4">{experience.trust.map((item, index) => <div key={item} className="flex min-w-0 items-center justify-center gap-1.5 text-center text-[10px] font-bold sm:text-xs">{index === 0 ? <PackageCheck className="size-3.5 text-[var(--sf-primary)]" /> : index === 1 ? <ShieldCheck className="size-3.5 text-[var(--sf-primary)]" /> : <BadgeCheck className="size-3.5 text-[var(--sf-primary)]" />}<span className="truncate">{item}</span></div>)}</div>
            </div>
        </header>
    );
}

function AffiliateStat({ value, label }: { value: string; label: string }) {
    return <div className="min-w-0 rounded-2xl bg-[var(--sf-badge)] px-2 py-3 text-center"><b className="block truncate text-xs font-black text-[var(--sf-on-badge)] sm:text-sm">{value}</b><span className="mt-0.5 block truncate text-[8px] font-bold uppercase tracking-[.08em] text-[var(--sf-muted)] sm:text-[9px]">{label}</span></div>;
}

function InfoPill({ icon, label }: { icon: React.ReactNode; label: string }) { return <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--sf-badge)] px-2.5 py-1.5 text-[10px] font-extrabold text-[var(--sf-on-badge)] [&>svg]:size-3.5">{icon}{label}</span>; }

function CategoryRail({ categories, active, onChange, productCount, affiliateMode }: { categories: [string, string][]; active: string; onChange: (value: string) => void; productCount: number; affiliateMode: boolean }) {
    return <div className="mx-auto mt-5 max-w-6xl px-4 sm:mt-6 sm:px-6"><div className={cn('flex items-center gap-2 overflow-x-auto rounded-2xl border border-[var(--sf-line)] bg-[var(--sf-card)] p-2 [scrollbar-width:none]', affiliateMode && 'shadow-[0_8px_24px_rgba(16,24,40,.06)]')} role="navigation" aria-label={affiliateMode ? 'Filter marketplace' : 'Kategori produk'}><CategoryButton active={active === 'all'} onClick={() => onChange('all')}>{affiliateMode ? 'Semua pilihan' : 'Semua'} <span className="ml-1 opacity-60">{productCount}</span></CategoryButton>{categories.map(([value, label]) => <CategoryButton key={value} active={active === value} onClick={() => onChange(value)}>{affiliateMode && <ShoppingBag className="mr-1.5 inline size-3" />}{label}</CategoryButton>)}<ChevronRight className="ml-auto hidden size-4 shrink-0 opacity-30 sm:block" /></div></div>;
}

function CategoryButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) { return <button type="button" onClick={onClick} className={cn('shrink-0 rounded-xl px-4 py-2.5 text-xs font-extrabold transition', active ? 'bg-[var(--sf-primary)] text-[var(--sf-on-primary)] shadow-sm' : 'hover:bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)]')}>{children}</button>; }
