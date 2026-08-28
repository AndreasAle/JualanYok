import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Search, SlidersHorizontal, X } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import ProductCard, { type MarketplaceProduct } from '@/components/marketplace/ProductCard';
import MarketingLayout from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Category { id: number; slug: string; name: string; description?: string | null; seo_title?: string | null; seo_description?: string | null; products_count?: number }

export default function Explore({ products, categories, filters, category }: { products: Paginated<MarketplaceProduct>; categories: Category[]; filters: Record<string, any>; category: Category | null }) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [filtersOpen, setFiltersOpen] = useState(false);

    useEffect(() => {
        if (filters.q) {
            const recent = JSON.parse(localStorage.getItem('jy_recent_searches') ?? '[]') as string[];
            localStorage.setItem('jy_recent_searches', JSON.stringify([filters.q, ...recent.filter((item) => item !== filters.q)].slice(0, 6)));
        }
    }, [filters.q]);

    const visit = (updates: Record<string, unknown>) => router.get(category ? `/categories/${category.slug}` : '/explore', { ...filters, ...updates }, { preserveState: true, preserveScroll: true, replace: true });
    const submit = (event: FormEvent) => { event.preventDefault(); visit({ q: query || undefined, page: undefined }); };

    return (
        <MarketingLayout title={category?.seo_title ?? (category ? `${category.name} — Marketplace` : 'Jelajahi karya creator Indonesia')} description={category?.seo_description ?? category?.description ?? 'Temukan produk digital, kelas, jasa, event, dan produk terkurasi dari creator Indonesia.'}>
            <Head><meta name="robots" content={filters.q ? 'noindex,follow' : 'index,follow'} /><link rel="canonical" href={category ? `/categories/${category.slug}` : '/explore'} /></Head>
            <div className="mx-auto max-w-7xl px-4 pb-24 pt-8 sm:px-6 lg:pt-12">
                <div className="rounded-[1.75rem] bg-[linear-gradient(120deg,#f0e7ff,#fff4ef)] px-5 py-8 sm:px-9 sm:py-11">
                    <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-700">Marketplace creator</p>
                    <div className="mt-2 flex flex-col justify-between gap-5 md:flex-row md:items-end">
                        <div><h1 className="max-w-3xl text-3xl font-black tracking-[-.045em] sm:text-5xl">{category?.name ?? 'Temukan karya yang pas untuk langkahmu.'}</h1><p className="mt-3 max-w-2xl text-sm leading-7 text-neutral-600">{category?.description ?? 'Produk digital, kelas, jasa, event, dan produk pilihan langsung dari creator Indonesia.'}</p></div>
                        <p className="shrink-0 text-xs font-bold text-neutral-500">{products.total.toLocaleString('id-ID')} produk layak tayang</p>
                    </div>
                    <form onSubmit={submit} className="mt-7 flex max-w-2xl gap-2 rounded-2xl border border-black/5 bg-white p-2 shadow-sm">
                        <Search className="ml-2 size-5 self-center text-neutral-400" /><input value={query} onChange={(e) => setQuery(e.target.value)} className="h-11 min-w-0 flex-1 bg-transparent px-2 text-sm outline-none" placeholder="Cari produk, creator, kategori, atau tag" /><button className="rounded-xl bg-[#171722] px-5 text-xs font-extrabold text-white">Cari</button>
                    </form>
                </div>

                <div className="mt-6 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]">
                    <Link href="/explore" className={cn('shrink-0 rounded-full border px-4 py-2 text-xs font-bold', !category && !filters.category ? 'border-[#171722] bg-[#171722] text-white' : 'border-line bg-white')}>Semua</Link>
                    {categories.map((item) => <Link key={item.id} href={`/categories/${item.slug}`} className={cn('shrink-0 rounded-full border px-4 py-2 text-xs font-bold', category?.id === item.id ? 'border-violet-700 bg-violet-700 text-white' : 'border-line bg-white hover:border-violet-300')}>{item.name}</Link>)}
                </div>

                <div className="mt-7 flex items-center justify-between gap-3 border-y border-line py-4">
                    <button onClick={() => setFiltersOpen(true)} className="inline-flex h-10 items-center gap-2 rounded-full border border-line bg-white px-4 text-xs font-extrabold"><SlidersHorizontal className="size-4" /> Filter</button>
                    <select value={filters.sort ?? 'relevance'} onChange={(e) => visit({ sort: e.target.value, page: undefined })} className="h-10 rounded-full border border-line bg-white px-4 text-xs font-bold outline-none">
                        <option value="relevance">Paling relevan</option><option value="latest">Terbaru</option><option value="bestselling">Terlaris</option><option value="price_low">Harga terendah</option><option value="price_high">Harga tertinggi</option>
                    </select>
                </div>

                {products.data.length ? <div className="mt-7 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">{products.data.map((product) => <ProductCard key={product.id} product={product} />)}</div> : <div className="mt-8 rounded-[1.5rem] border border-dashed border-violet-200 bg-violet-50/50 px-6 py-20 text-center"><Search className="mx-auto size-8 text-violet-400" /><h2 className="mt-4 text-xl font-black">Belum menemukan yang pas</h2><p className="mt-2 text-sm text-neutral-500">Coba kata yang lebih umum atau lepas beberapa filter.</p><Link href="/explore" className="mt-5 inline-flex rounded-full bg-[#171722] px-5 py-2.5 text-xs font-bold text-white">Lihat semua produk</Link></div>}

                {products.last_page > 1 && <nav className="mt-10 flex flex-wrap justify-center gap-2" aria-label="Pagination">{products.links.map((link, index) => link.url && <Link key={index} href={link.url} preserveScroll className={cn('grid min-h-10 min-w-10 place-items-center rounded-full border px-3 text-xs font-bold', link.active ? 'border-[#171722] bg-[#171722] text-white' : 'border-line bg-white')} dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>}
            </div>

            {filtersOpen && <div className="fixed inset-0 z-[70] bg-black/35 backdrop-blur-sm" onClick={() => setFiltersOpen(false)}><aside className="absolute bottom-0 left-0 right-0 max-h-[86vh] overflow-y-auto rounded-t-[2rem] bg-white p-5 shadow-2xl sm:bottom-auto sm:left-auto sm:right-5 sm:top-20 sm:w-[390px] sm:rounded-[1.5rem]" onClick={(e) => e.stopPropagation()}><div className="flex items-center justify-between"><h2 className="text-lg font-black">Saring produk</h2><button onClick={() => setFiltersOpen(false)} className="grid size-9 place-items-center rounded-full bg-neutral-100"><X className="size-4" /></button></div><FilterPanel filters={filters} apply={(value) => { visit({ ...value, page: undefined }); setFiltersOpen(false); }} /></aside></div>}
        </MarketingLayout>
    );
}

function FilterPanel({ filters, apply }: { filters: Record<string, any>; apply: (data: Record<string, any>) => void }) {
    const [data, setData] = useState({ type: filters.type ?? '', min_price: filters.min_price ?? '', max_price: filters.max_price ?? '', promo: !!filters.promo, affiliate: !!filters.affiliate, free: !!filters.free });
    return <div className="mt-6 space-y-6"><label className="block text-xs font-extrabold">Tipe produk<select value={data.type} onChange={(e) => setData({ ...data, type: e.target.value })} className="mt-2 h-12 w-full rounded-xl border border-line bg-white px-3 font-medium"><option value="">Semua tipe</option><option value="DIGITAL">Produk digital</option><option value="COURSE">Kelas online</option><option value="EVENT">Event & webinar</option><option value="SERVICE">Jasa & konsultasi</option><option value="PHYSICAL">Produk fisik</option><option value="MEMBERSHIP">Membership</option><option value="EXTERNAL">Produk affiliate</option></select></label><div><p className="text-xs font-extrabold">Rentang harga</p><div className="mt-2 grid grid-cols-2 gap-2"><input type="number" min="0" value={data.min_price} onChange={(e) => setData({ ...data, min_price: e.target.value })} placeholder="Minimum" className="h-12 min-w-0 rounded-xl border border-line px-3 text-sm" /><input type="number" min="0" value={data.max_price} onChange={(e) => setData({ ...data, max_price: e.target.value })} placeholder="Maksimum" className="h-12 min-w-0 rounded-xl border border-line px-3 text-sm" /></div></div>{[['promo', 'Sedang promo'], ['affiliate', 'Komisi affiliate tersedia'], ['free', 'Produk gratis']].map(([key, label]) => <label key={key} className="flex items-center justify-between rounded-xl border border-line p-3 text-xs font-bold">{label}<input type="checkbox" checked={!!data[key as keyof typeof data]} onChange={(e) => setData({ ...data, [key]: e.target.checked })} className="size-4 accent-violet-600" /></label>)}<div className="grid grid-cols-2 gap-2"><button onClick={() => apply({ type: undefined, min_price: undefined, max_price: undefined, promo: undefined, affiliate: undefined, free: undefined })} className="h-11 rounded-full border border-line text-xs font-bold">Reset</button><button onClick={() => apply(data)} className="h-11 rounded-full bg-[#171722] text-xs font-bold text-white">Tampilkan hasil <ArrowRight className="ml-1 inline size-3.5" /></button></div></div>;
}
