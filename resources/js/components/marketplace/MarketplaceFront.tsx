import { Link } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, BookOpen, Boxes, CalendarDays, GraduationCap, Palette, Search, ShoppingBag, Sparkles, Store, Users } from 'lucide-react';
import ProductCard, { type MarketplaceProduct } from '@/components/marketplace/ProductCard';

interface Category { id: number; slug: string; name: string; products_count: number }
interface Creator { name: string; username: string; tagline?: string | null; category?: string | null; is_verified: boolean; avatar_url?: string | null; cover_url?: string | null; products_count: number; url: string }
export interface MarketplaceHomeData {
    banners: {
        id: number;
        eyebrow?: string | null;
        title: string;
        description?: string | null;
        desktop_image_url?: string | null;
        mobile_image_url?: string | null;
        cta_label?: string | null;
        cta_url?: string | null;
        tone?: string | null;
    }[];
    categories: Category[];
    sections: { key: string; title: string; subtitle?: string | null; products: MarketplaceProduct[] }[];
    creators: Creator[];
    popular_searches: string[];
}

const categoryIcons = [BookOpen, Palette, GraduationCap, Sparkles, Boxes, CalendarDays, Users, ShoppingBag];

export default function MarketplaceFront({ data }: { data: MarketplaceHomeData }) {
    return <div className="mx-auto max-w-7xl px-4 sm:px-6">
        <section className="py-14 sm:py-20">
            <div className="flex items-end justify-between gap-4"><div><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-700">Mulai dari sini</p><h2 className="mt-2 text-3xl font-black tracking-[-.04em] sm:text-4xl">Kategori populer</h2></div><Link href="/explore" className="hidden items-center gap-1 text-xs font-extrabold text-violet-700 sm:flex">Lihat semuanya <ArrowRight className="size-4" /></Link></div>
            <div className="mt-7 flex gap-3 overflow-x-auto pb-3 [scrollbar-width:none] lg:grid lg:grid-cols-6">{data.categories.slice(0, 12).map((category, index) => { const Icon = categoryIcons[index % categoryIcons.length]; return <Link key={category.id} href={`/categories/${category.slug}`} className="group min-w-[145px] rounded-[1.25rem] border border-black/[.07] bg-white p-4 shadow-[0_8px_25px_rgba(42,24,76,.05)] transition hover:-translate-y-1 hover:border-violet-200"><span className="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-700 group-hover:bg-violet-600 group-hover:text-white"><Icon className="size-5" /></span><h3 className="mt-4 line-clamp-2 text-sm font-extrabold">{category.name}</h3><p className="mt-1 text-[10px] font-semibold text-neutral-400">{category.products_count.toLocaleString('id-ID')} produk</p></Link>; })}</div>
        </section>

        {data.popular_searches.length > 0 && <section className="-mt-5 pb-8"><div className="flex flex-wrap items-center gap-2"><span className="mr-1 flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-neutral-400"><Search className="size-3.5" /> Sering dicari</span>{data.popular_searches.map((term) => <Link key={term} href={`/explore?q=${encodeURIComponent(term)}`} className="rounded-full border border-line bg-white px-3 py-1.5 text-[10px] font-bold hover:border-violet-300 hover:text-violet-700">{term}</Link>)}</div></section>}

        {data.sections.map((section) => section.products.length > 0 && <section key={section.key} className="py-12 sm:py-16"><div className="flex items-end justify-between gap-5"><div><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-700">Kurasi JualanYok</p><h2 className="mt-2 text-2xl font-black tracking-[-.035em] sm:text-4xl">{section.title}</h2>{section.subtitle && <p className="mt-2 text-sm text-neutral-500">{section.subtitle}</p>}</div><Link href="/explore" className="shrink-0 text-xs font-extrabold text-violet-700">Lihat semua</Link></div><div className="mt-7 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">{section.products.slice(0, 8).map((product) => <ProductCard key={product.id} product={product} />)}</div></section>)}

        {data.creators.length > 0 && <section className="py-14 sm:py-20"><div className="text-center"><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-700">Creator unggulan</p><h2 className="mt-2 text-3xl font-black tracking-[-.04em] sm:text-4xl">Kenal creator di balik karyanya.</h2></div><div className="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">{data.creators.map((creator) => <article key={creator.username} className="overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[0_10px_35px_rgba(42,24,76,.06)]"><div className="h-24 bg-[linear-gradient(120deg,#ede3ff,#ffe8ed)]">{creator.cover_url && <img src={creator.cover_url} alt="" className="size-full object-cover" />}</div><div className="p-5"><div className="-mt-12 flex items-end justify-between"><span className="grid size-16 place-items-center overflow-hidden rounded-2xl border-4 border-white bg-violet-100 text-xl font-black text-violet-700">{creator.avatar_url ? <img src={creator.avatar_url} alt="" className="size-full object-cover" /> : creator.name.charAt(0)}</span><span className="rounded-full bg-neutral-100 px-3 py-1 text-[9px] font-bold text-neutral-500">{creator.products_count} produk</span></div><h3 className="mt-3 flex items-center gap-1 text-lg font-black">{creator.name}{creator.is_verified && <BadgeCheck className="size-4 fill-violet-600 text-white" />}</h3><p className="text-[11px] text-neutral-500">@{creator.username}{creator.category ? ` · ${creator.category}` : ''}</p>{creator.tagline && <p className="mt-3 line-clamp-2 text-xs leading-5 text-neutral-600">{creator.tagline}</p>}<Link href={creator.url} className="mt-5 flex h-10 items-center justify-center rounded-full border border-[#171722] text-[11px] font-extrabold hover:bg-[#171722] hover:text-white">Kunjungi toko</Link></div></article>)}</div></section>}

        <section className="my-14 overflow-hidden rounded-[2rem] bg-[#171722] px-6 py-12 text-white sm:px-12 sm:py-16"><div className="grid items-center gap-8 md:grid-cols-[1fr_auto]"><div><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-300">Creator commerce</p><h2 className="mt-3 max-w-2xl text-3xl font-black tracking-[-.04em] sm:text-5xl">Punya karya atau keahlian? Buka tokomu sendiri.</h2><p className="mt-4 max-w-2xl text-sm leading-7 text-white/65">Storefront personal, pembayaran resmi, delivery produk digital, analytics, affiliate, dan katalog yang bisa ditemukan audiens baru.</p></div><Link href="/register" className="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-white px-6 text-xs font-extrabold text-[#171722]">Buka toko gratis <ArrowRight className="size-4" /></Link></div></section>

        <section className="grid gap-3 pb-16 sm:grid-cols-2 lg:grid-cols-4">{[['Pembayaran resmi', 'Transaksi melewati provider dan verifikasi yang sudah terhubung.'], ['Delivery otomatis', 'Produk digital diterima setelah pembayaran dinyatakan valid.'], ['Creator teridentifikasi', 'Identitas toko dan status verifikasi ditampilkan apa adanya.'], ['Bantuan transaksi', 'Refund, dispute, dan bantuan tersedia melalui alur resmi.']].map(([title, copy], index) => <div key={title} className="rounded-2xl border border-line bg-white p-5"><span className="grid size-9 place-items-center rounded-xl bg-violet-50 text-violet-700">{index === 0 ? <ShoppingBag className="size-4" /> : index === 1 ? <Boxes className="size-4" /> : index === 2 ? <Store className="size-4" /> : <Users className="size-4" />}</span><h3 className="mt-4 text-sm font-extrabold">{title}</h3><p className="mt-2 text-xs leading-5 text-neutral-500">{copy}</p></div>)}</section>
    </div>;
}
