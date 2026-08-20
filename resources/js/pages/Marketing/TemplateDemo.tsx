import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Eye, Sparkles } from 'lucide-react';
import { StorefrontView, type StorefrontStore } from '@/components/storefront/MarketplaceStorefrontView';
import type { StorefrontBlock, StorefrontProduct } from '@/types';

interface Template {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    use_case: string | null;
    is_premium: boolean;
    theme: Record<string, any>;
}

const COPY: Record<string, { brand: string; handle: string; tagline: string; bio: string; products: [string, string, number][] }> = {
    'creator-digital': { brand: 'RuangKarya', handle: 'ruangkarya', tagline: '30 hari konten, selesai satu sore.', bio: 'Toolkit praktis untuk kreator yang ingin konsisten tanpa kehabisan ide.', products: [['Content Plan 30 Hari', 'E-book & worksheet siap isi', 129000], ['Canva Launch Kit', '80+ aset kampanye', 179000], ['Notion Creator OS', 'Kelola ide sampai publikasi', 149000]] },
    'freelancer-jasa': { brand: 'Studio Arunika', handle: 'studioarunika', tagline: 'Identitas yang bikin brand lebih dipercaya.', bio: 'Studio desain independen untuk bisnis yang siap naik kelas.', products: [['Brand Starter', 'Logo, warna, dan panduan mini', 1750000], ['Social Media Kit', '30 desain siap unggah', 950000], ['Konsultasi Brand', 'Sesi strategi 90 menit', 475000]] },
    'kelas-online': { brand: 'NaikKelas', handle: 'naikkelas', tagline: 'Belajar singkat. Praktik lebih cepat.', bio: 'Kelas terarah untuk membangun skill yang benar-benar terpakai.', products: [['Kelas Canva Bisnis', '12 modul + studi kasus', 349000], ['Copywriting Dasar', 'Tulis pesan yang menjual', 279000], ['Bundel UMKM', '3 kelas + komunitas', 699000]] },
    'fashion-fisik': { brand: 'SORA GOODS', handle: 'soragoods', tagline: 'Daily essentials, thoughtfully made.', bio: 'Koleksi pakaian nyaman dalam jumlah terbatas.', products: [['Everyday Overshirt', 'Cotton twill premium', 389000], ['Essential Tee', '240 gsm, relaxed fit', 189000], ['Canvas Tote', 'Tebal dan tahan lama', 149000]] },
    'affiliate-creator': { brand: 'PilihBagus', handle: 'pilihbagus', tagline: 'Barang yang benar-benar layak dibeli.', bio: 'Rekomendasi terkurasi, ringkas, dan transparan.', products: [['Mic Creator M2', 'Audio jernih untuk konten', 549000], ['Desk Light Pro', 'Cahaya lembut dan stabil', 329000], ['Tripod Pocket', 'Ringkas untuk mobile creator', 189000]] },
    'food-beverage': { brand: 'Dapur Sore', handle: 'dapursore', tagline: 'Comfort food, freshly made.', bio: 'Menu rumahan segar untuk nemenin hari sibukmu.', products: [['Rice Bowl Sambal Matah', 'Ayam juicy dan sambal segar', 32000], ['Pasta Creamy Pedas', 'Gurih dengan level pilihan', 38000], ['Kopi Susu Aren', 'Manis seimbang, 250 ml', 22000]] },
    'minimal-link': { brand: 'Nara Putri', handle: 'naraputri', tagline: 'Designer, educator, and curious maker.', bio: 'Karya, layanan, dan produk digital dalam satu tempat.', products: [['UI Audit Express', 'Review produk 45 menit', 450000], ['Portfolio Template', 'Framer template siap pakai', 229000], ['Design Notes', 'Catatan mingguan gratis', 0]] },
};

export default function TemplateDemo({ template }: { template: Template }) {
    const demo = COPY[template.slug] ?? COPY['creator-digital'];
    const products: StorefrontProduct[] = demo.products.map(([name, description, price], index) => ({ id: index + 1, slug: `produk-${index + 1}`, type: template.slug === 'freelancer-jasa' ? 'SERVICE' : template.slug === 'fashion-fisik' || template.slug === 'food-beverage' ? 'PHYSICAL' : 'DIGITAL', type_label: template.use_case ?? 'Produk', name, short_description: description, thumbnail_url: null, price, compare_at_price: index === 0 && price > 0 ? Math.round(price * 1.25) : null, discount_percent: index === 0 ? 20 : 0, is_pay_what_you_want: false, minimum_price: null, external_url: null, is_buyable: true, sales_count: 48 - index * 11 }));
    const store: StorefrontStore = { id: 0, username: demo.handle, name: demo.brand, tagline: demo.tagline, bio: demo.bio, avatar_url: null, cover_url: null, socials: {}, whatsapp: '628123456789', show_branding: true, public_url: '#', template_slug: template.slug, theme: template.theme };
    const blocks: StorefrontBlock[] = [
        { id: 1, type: 'PROMO_BANNER', title: null, content: { text: 'Gratis ongkir / bonus khusus minggu ini', button_text: 'Lihat koleksi', button_url: '#store-content' }, style: {}, visible_mobile: true, visible_desktop: true, animation: 'fade-up' },
        { id: 2, type: 'FEATURED_PRODUCTS', title: 'Produk unggulan', content: { products }, style: {}, visible_mobile: true, visible_desktop: true, animation: 'fade-up' },
        { id: 3, type: 'TESTIMONIAL', title: 'Dipilih pelanggan', content: { quote: 'Produknya rapi, jelas, dan langsung bisa dipakai. Pengalaman belanjanya juga nyaman.', name: 'Dinda Prameswari', role: 'Pelanggan terverifikasi' }, style: {}, visible_mobile: true, visible_desktop: true, animation: 'fade-up' },
        { id: 4, type: 'FAQ', title: 'Pertanyaan umum', content: { items: [{ question: 'Bagaimana produk dikirim?', answer: 'Instruksi dan akses diterima otomatis setelah pembayaran berhasil.' }, { question: 'Bisa tanya sebelum membeli?', answer: 'Bisa. Hubungi toko melalui tombol WhatsApp.' }] }, style: {}, visible_mobile: true, visible_desktop: true, animation: null },
    ];

    return <div className="min-h-screen bg-[#ececf2] pb-24">
        <Head title={`Demo ${template.name}`} />
        <div className="sticky top-0 z-[70] border-b border-black/10 bg-white/95 backdrop-blur-xl">
            <div className="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <Link href="/templates" className="inline-flex items-center gap-2 text-sm font-extrabold text-neutral-700"><ArrowLeft className="size-4" /> Template</Link>
                <span className="hidden h-6 w-px bg-neutral-200 sm:block" />
                <div className="hidden min-w-0 flex-1 sm:block"><p className="truncate text-sm font-black text-neutral-950">{template.name}</p><p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-[.14em] text-violet-600"><Eye className="size-3" /> Demo interaktif</p></div>
                <Link href={`/register?template=${template.slug}`} className="ml-auto inline-flex h-10 items-center gap-2 rounded-full bg-[#171722] px-4 text-[11px] font-extrabold text-white shadow-lg sm:px-5 sm:text-xs">Gunakan template <ArrowRight className="size-4" /></Link>
            </div>
        </div>
        <div className="mx-auto max-w-[1380px] px-0 py-0 sm:px-5 sm:py-6"><div className="overflow-hidden bg-white shadow-2xl sm:rounded-[2rem] sm:border sm:border-white"><StorefrontView store={store} blocks={blocks} isPreview onBuy={() => {}} /></div></div>
        <div className="fixed inset-x-3 bottom-3 z-[75] mx-auto flex max-w-lg items-center gap-3 rounded-2xl border border-white/60 bg-[#171722]/95 p-3 text-white shadow-2xl backdrop-blur sm:hidden"><span className="grid size-9 shrink-0 place-items-center rounded-xl bg-violet-500"><Sparkles className="size-4" /></span><p className="min-w-0 flex-1 text-xs font-bold">Cocok dengan bisnismu?</p><Link href={`/register?template=${template.slug}`} className="inline-flex h-9 items-center gap-1 rounded-xl bg-white px-3 text-[11px] font-black text-neutral-950">Pilih <Check className="size-3.5" /></Link></div>
    </div>;
}
