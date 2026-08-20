import {
    BadgeCheck,
    Check,
    ChevronRight,
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
export function StorefrontView({ store, blocks, isPreview, theme, onBuy }: { store: StorefrontStore; blocks: StorefrontBlock[]; isPreview: boolean; theme?: StorefrontTheme; onBuy: (product: StorefrontProduct) => void }) {
    const t = theme ?? buildStorefrontTheme(store.theme);
    const experience = EXPERIENCES[store.template_slug ?? ''] ?? FALLBACK_EXPERIENCE;
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');

    const allProducts = useMemo(() => {
        const unique = new Map<number, StorefrontProduct>();
        blocks.forEach((block) => ((block.content?.products ?? []) as StorefrontProduct[]).forEach((product) => unique.set(product.id, product)));
        return Array.from(unique.values());
    }, [blocks]);

    const productTypes = useMemo(() => {
        const types = new Map<string, string>();
        allProducts.forEach((product) => types.set(product.type, product.type_label));
        return Array.from(types.entries());
    }, [allProducts]);

    const visibleBlocks = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase('id');
        if (!needle && category === 'all') return blocks;
        return blocks.map((block) => {
            if (!PRODUCT_BLOCKS.has(block.type)) return block;
            const products = ((block.content?.products ?? []) as StorefrontProduct[]).filter((product) => {
                const matchesCategory = category === 'all' || product.type === category;
                const haystack = `${product.name} ${product.short_description ?? ''} ${product.type_label}`.toLocaleLowerCase('id');
                return matchesCategory && (!needle || haystack.includes(needle));
            });
            return { ...block, content: { ...block.content, products } };
        });
    }, [blocks, category, query]);

    const matchingProducts = allProducts.filter((product) => {
        const needle = query.trim().toLocaleLowerCase('id');
        const haystack = `${product.name} ${product.short_description ?? ''} ${product.type_label}`.toLocaleLowerCase('id');
        return (category === 'all' || product.type === category) && (!needle || haystack.includes(needle));
    });

    return (
        <div className="@container min-h-full" style={t.pageStyle}>
            <MarketplaceBar store={store} experience={experience} query={query} onQueryChange={setQuery} />
            <StoreHeader store={store} theme={t} isPreview={isPreview} experience={experience} products={allProducts} />
            {allProducts.length > 0 && <CategoryRail categories={productTypes} active={category} onChange={setCategory} productCount={allProducts.length} />}

            <main id="store-content" className="mx-auto max-w-6xl px-4 pb-20 pt-8 sm:px-6 sm:pt-10">
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
                    <div className="space-y-10 sm:space-y-14">
                        {visibleBlocks.map((block) => <BlockRenderer key={block.id} block={block} ctx={{ storeUsername: store.username, storeName: store.name, theme: t, productLayout: store.theme.product_layout ?? 'grid', isPreview, onBuy }} />)}
                    </div>
                )}

                {store.show_branding && <footer className="mt-16 border-t border-[var(--sf-line)] pt-8 text-center"><a href="/" className="inline-flex items-center gap-2 text-xs font-semibold opacity-70 transition-opacity hover:opacity-100">Commerce powered by <span className="font-black text-[var(--sf-primary)]">JualanYok</span></a></footer>}
            </main>
        </div>
    );
}

function MarketplaceBar({ store, experience, query, onQueryChange }: { store: StorefrontStore; experience: StoreExperience; query: string; onQueryChange: (value: string) => void }) {
    return (
        <div className="sticky top-0 z-40 border-b border-[var(--sf-line)] bg-[var(--sf-card)]/90 backdrop-blur-xl">
            <div className="mx-auto max-w-6xl px-4 sm:px-6">
                <div className="flex h-16 items-center gap-3">
                    <a href="#store-top" className="flex min-w-0 items-center gap-2.5">
                        <span className="size-9 shrink-0 overflow-hidden rounded-xl border border-[var(--sf-line)] bg-white shadow-sm">{store.avatar_url ? <img src={store.avatar_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center bg-[var(--sf-primary)] text-sm font-black text-[var(--sf-on-primary)]">{store.name[0]}</span>}</span>
                        <span className="hidden min-w-0 @xl:block"><b className="block truncate text-sm">{store.name}</b><small className="block text-[10px] font-bold uppercase tracking-[.12em] text-[var(--sf-primary)]">{experience.label}</small></span>
                    </a>
                    <SearchField query={query} onQueryChange={onQueryChange} className="ml-auto hidden w-full max-w-md @xl:block" placeholder="Cari produk di toko ini" />
                    <span className="ml-auto hidden items-center gap-1.5 rounded-full bg-[color-mix(in_oklab,var(--sf-primary)_9%,transparent)] px-3 py-2 text-[11px] font-bold text-[var(--sf-primary)] @3xl:inline-flex"><ShieldCheck className="size-3.5" /> Belanja aman</span>
                    <a href="#store-content" className="grid size-10 place-items-center rounded-full border border-[var(--sf-line)] bg-[var(--sf-card)]" aria-label="Lihat katalog"><ShoppingBag className="size-4.5" /></a>
                </div>
                <SearchField query={query} onQueryChange={onQueryChange} className="block pb-3 @xl:hidden" placeholder="Cari produk, kelas, atau layanan" />
            </div>
        </div>
    );
}

function SearchField({ query, onQueryChange, className, placeholder }: { query: string; onQueryChange: (value: string) => void; className?: string; placeholder: string }) {
    return <label className={cn('relative', className)}><Search className="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[var(--sf-muted)]" /><input value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder={placeholder} className="h-10 w-full rounded-full border border-[var(--sf-line)] bg-transparent pl-10 pr-4 text-sm outline-none transition focus:border-[var(--sf-primary)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--sf-primary)_16%,transparent)]" /></label>;
}

function StoreHeader({ store, theme, isPreview, experience, products }: { store: StorefrontStore; theme: StorefrontTheme; isPreview: boolean; experience: StoreExperience; products: StorefrontProduct[] }) {
    const [copied, setCopied] = useState(false);
    const totalSales = products.reduce((total, product) => total + (product.sales_count ?? 0), 0);
    const share = async () => {
        if (isPreview) return;
        if (navigator.share) { try { await navigator.share({ title: store.name, url: store.public_url }); return; } catch { /* dismissed */ } }
        await navigator.clipboard.writeText(store.public_url); setCopied(true); setTimeout(() => setCopied(false), 2000);
    };

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

function InfoPill({ icon, label }: { icon: React.ReactNode; label: string }) { return <span className="inline-flex items-center gap-1.5 rounded-full bg-[color-mix(in_oklab,var(--sf-primary)_9%,transparent)] px-2.5 py-1.5 text-[10px] font-extrabold text-[var(--sf-primary)] [&>svg]:size-3.5">{icon}{label}</span>; }

function CategoryRail({ categories, active, onChange, productCount }: { categories: [string, string][]; active: string; onChange: (value: string) => void; productCount: number }) {
    return <div className="mx-auto mt-6 max-w-6xl px-4 sm:px-6"><div className="flex items-center gap-2 overflow-x-auto rounded-2xl border border-[var(--sf-line)] bg-[var(--sf-card)] p-2 shadow-sm [scrollbar-width:none]"><CategoryButton active={active === 'all'} onClick={() => onChange('all')}>Semua <span className="ml-1 opacity-60">{productCount}</span></CategoryButton>{categories.map(([value, label]) => <CategoryButton key={value} active={active === value} onClick={() => onChange(value)}>{label}</CategoryButton>)}<ChevronRight className="ml-auto hidden size-4 shrink-0 opacity-30 sm:block" /></div></div>;
}

function CategoryButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) { return <button type="button" onClick={onClick} className={cn('shrink-0 rounded-xl px-4 py-2.5 text-xs font-extrabold transition', active ? 'bg-[var(--sf-primary)] text-[var(--sf-on-primary)] shadow-sm' : 'hover:bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)]')}>{children}</button>; }
