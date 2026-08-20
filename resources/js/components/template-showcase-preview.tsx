import {
    ArrowRight,
    BadgeCheck,
    BookOpen,
    BriefcaseBusiness,
    Camera,
    Check,
    ChevronRight,
    CirclePlay,
    Clock3,
    Heart,
    Link2,
    MessageCircle,
    MonitorPlay,
    Quote,
    ShoppingBag,
    Sparkles,
    Star,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface TemplateShowcasePreviewProps {
    slug: string;
    primary: string;
    accent: string;
    className?: string;
    display?: 'default' | 'homepage' | 'catalog';
}

/**
 * Realistic miniature storefronts for the public template catalogue.
 *
 * These are intentionally content-led rather than blueprint wireframes: each
 * use case has its own fictional brand, offer, product names, prices, and CTA.
 */
export function TemplateShowcasePreview({ slug, primary, accent, className, display = 'default' }: TemplateShowcasePreviewProps) {
    const content = (() => {
        switch (slug) {
            case 'freelancer-jasa':
                return <FreelancerPreview primary={primary} accent={accent} />;
            case 'kelas-online':
                return <CoursePreview primary={primary} accent={accent} />;
            case 'fashion-fisik':
                return <FashionPreview primary={primary} accent={accent} />;
            case 'affiliate-creator':
                return <AffiliatePreview primary={primary} accent={accent} />;
            case 'food-beverage':
                return <FoodPreview primary={primary} accent={accent} />;
            case 'minimal-link':
                return <PersonalPreview primary={primary} accent={accent} />;
            case 'creator-digital':
            default:
                return <CreatorPreview primary={primary} accent={accent} />;
        }
    })();

    return (
        <div
            className={cn('relative overflow-hidden bg-white text-[#171722]', className)}
            style={{ ['--preview-primary' as string]: primary, ['--preview-accent' as string]: accent }}
            role="img"
            aria-label={`Contoh toko jadi untuk template ${slug}`}
        >
            <div
                className={cn(
                    'h-full w-full',
                    display === 'homepage' && 'h-2/3 w-2/3 origin-top-left scale-150 sm:h-4/5 sm:w-4/5 sm:scale-125',
                    display === 'catalog' && 'h-4/5 w-4/5 origin-top-left scale-125 sm:h-full sm:w-full sm:scale-100',
                )}
            >
                {content}
            </div>
        </div>
    );
}

function Topbar({ brand, dark = false }: { brand: string; dark?: boolean }) {
    return (
        <div className={cn('flex h-7 items-center justify-between px-2.5', dark && 'text-white')}>
            <div className="flex items-center gap-1.5">
                <span className="grid size-3.5 place-items-center rounded-[4px] bg-[var(--preview-primary)] text-[6px] font-black text-white">{brand.charAt(0)}</span>
                <span className="text-[7px] font-black tracking-[-.02em]">{brand}</span>
            </div>
            <div className="flex items-center gap-1.5">
                <span className="h-px w-2.5 bg-current opacity-50" />
                <span className="h-px w-2.5 bg-current opacity-50" />
                <ShoppingBag className="size-2.5" />
            </div>
        </div>
    );
}

function MiniButton({ children, light = false }: { children: React.ReactNode; light?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-1 text-[5px] font-black', light ? 'bg-white text-[#171722]' : 'bg-[#171722] text-white')}>
            {children}<ArrowRight className="size-1.5" />
        </span>
    );
}

function Stars({ light = false }: { light?: boolean }) {
    return <span className={cn('flex gap-px text-amber-400', light && 'text-amber-300')}>{Array.from({ length: 5 }, (_, index) => <Star key={index} className="size-1.5 fill-current" />)}</span>;
}

function CreatorPreview({ primary, accent }: { primary: string; accent: string }) {
    return (
        <div className="min-h-full bg-[#fbf9ff]">
            <Topbar brand="RuangKarya" />
            <section className="mx-1.5 overflow-hidden rounded-lg px-2.5 py-3 text-white" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>
                <div className="flex items-center gap-1 text-[4.5px] font-bold uppercase tracking-[.14em]"><Sparkles className="size-2" /> Toolkit kreator lokal</div>
                <h3 className="mt-1.5 max-w-[90%] text-[12px] font-black leading-[1.05] tracking-[-.04em]">30 hari konten, selesai satu sore.</h3>
                <p className="mt-1.5 max-w-[88%] text-[5px] leading-[1.45] text-white/80">Template siap edit untuk bikin konten yang konsisten dan tetap terasa kamu.</p>
                <div className="mt-2"><MiniButton light>Lihat toolkit</MiniButton></div>
            </section>

            <section className="px-2.5 pb-2 pt-2.5">
                <div className="flex items-end justify-between"><div><p className="text-[4px] font-black uppercase tracking-[.16em] text-violet-600">Paling laris</p><h4 className="mt-0.5 text-[8px] font-black">Koleksi kreator</h4></div><span className="text-[4.5px] font-bold">Lihat semua →</span></div>
                <div className="mt-1.5 grid grid-cols-2 gap-1.5">
                    <ProductCover title="CONTENT\nPLANNER" kicker="2026 EDITION" price="Rp129K" from={primary} to={accent} />
                    <ProductCover title="CANVA\nLAUNCH KIT" kicker="48 TEMPLATES" price="Rp89K" from="#111827" to="#475569" />
                </div>
                <div className="mt-2 rounded-md border border-violet-100 bg-white p-1.5 shadow-sm">
                    <div className="flex items-center gap-1"><Quote className="size-2 text-violet-500" /><Stars /></div>
                    <p className="mt-1 text-[4.5px] font-semibold leading-[1.45]">“Posting jadi jauh lebih terarah. Dalam dua minggu, tiga kontenku tembus explore.”</p>
                    <p className="mt-1 text-[4px] font-black">Nadya · Content creator</p>
                </div>
            </section>
        </div>
    );
}

function ProductCover({ title, kicker, price, from, to }: { title: string; kicker: string; price: string; from: string; to: string }) {
    return (
        <div className="overflow-hidden rounded-md border border-black/5 bg-white shadow-sm">
            <div className="relative aspect-[4/3] overflow-hidden p-1.5 text-white" style={{ background: `linear-gradient(145deg, ${from}, ${to})` }}>
                <span className="absolute -right-3 -top-4 size-11 rounded-full border-[7px] border-white/15" />
                <p className="text-[3.5px] font-black tracking-[.14em] text-white/70">{kicker}</p>
                <p className="mt-1 whitespace-pre-line text-[7px] font-black leading-[.95]">{title}</p>
            </div>
            <div className="flex items-center justify-between p-1.5"><span className="text-[5px] font-black">{price}</span><span className="grid size-3 place-items-center rounded-full bg-black text-white"><ArrowRight className="size-1.5" /></span></div>
        </div>
    );
}

function FreelancerPreview({ primary, accent }: { primary: string; accent: string }) {
    return (
        <div className="min-h-full bg-[#f7faff]">
            <Topbar brand="Studio Arunika" />
            <section className="mx-2 rounded-lg border border-blue-100 bg-white p-2 shadow-sm">
                <div className="flex items-start gap-2">
                    <div className="grid size-8 shrink-0 place-items-center rounded-full text-[9px] font-black text-white" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>RA</div>
                    <div className="min-w-0"><p className="flex items-center gap-0.5 text-[8px] font-black">Raka Aditya <BadgeCheck className="size-2.5 fill-blue-500 text-white" /></p><p className="text-[4.5px] font-bold text-slate-500">Brand & Web Designer</p><span className="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-1 py-0.5 text-[4px] font-bold text-emerald-700"><span className="size-1 rounded-full bg-emerald-500" /> Available Mei</span></div>
                </div>
                <h3 className="mt-2 text-[11px] font-black leading-[1.02] tracking-[-.04em]">Brand yang jelas.<br />Website yang bekerja.</h3>
                <p className="mt-1 text-[4.5px] leading-[1.4] text-slate-500">Membantu bisnis lokal tampil profesional dan lebih mudah dipercaya.</p>
            </section>

            <section className="px-2 pb-2 pt-2">
                <div className="flex items-center justify-between"><h4 className="text-[7px] font-black">Project pilihan</h4><span className="text-[4px] font-bold text-blue-600">Behance ↗</span></div>
                <div className="mt-1 grid grid-cols-[1.2fr_.8fr] gap-1">
                    <PortfolioTile name="KOPI KALA" label="Identity & packaging" background="#102c3b" accent="#f6c85f" />
                    <PortfolioTile name="NARA" label="Beauty brand" background="#f5d7dd" accent="#b72c50" />
                </div>
                <h4 className="mt-2 text-[7px] font-black">Paket jasa</h4>
                <div className="mt-1 space-y-1">
                    <ServiceRow title="Brand Starter" detail="Logo · warna · guideline" price="2,8 jt" primary={primary} />
                    <ServiceRow title="Landing Page" detail="Design + development" price="4,5 jt" primary={primary} />
                </div>
            </section>
        </div>
    );
}

function PortfolioTile({ name, label, background, accent }: { name: string; label: string; background: string; accent: string }) {
    return <div className="relative aspect-[4/3] overflow-hidden rounded-md p-1.5 text-white" style={{ background }}><div className="absolute right-1 top-1 size-7 rotate-12 rounded-full border-4 opacity-80" style={{ borderColor: accent }} /><p className="relative mt-3 text-[8px] font-black tracking-[.1em]">{name}</p><p className="relative mt-0.5 text-[3.8px] opacity-70">{label}</p></div>;
}

function ServiceRow({ title, detail, price, primary }: { title: string; detail: string; price: string; primary: string }) {
    return <div className="flex items-center rounded-md border border-slate-100 bg-white p-1.5 shadow-sm"><span className="mr-1.5 grid size-5 place-items-center rounded bg-blue-50" style={{ color: primary }}><BriefcaseBusiness className="size-2.5" /></span><span className="min-w-0 flex-1"><b className="block text-[5px]">{title}</b><small className="block text-[3.8px] text-slate-400">{detail}</small></span><span className="text-right"><b className="block text-[5px]">{price}</b><small className="text-[3.5px] text-slate-400">mulai</small></span></div>;
}

function CoursePreview({ primary, accent }: { primary: string; accent: string }) {
    return (
        <div className="min-h-full bg-[#f4fbf7]">
            <Topbar brand="Kelas Naik" />
            <section className="relative mx-1.5 overflow-hidden rounded-lg bg-[#092a25] p-2.5 text-white">
                <span className="inline-flex rounded-full bg-emerald-300 px-1.5 py-0.5 text-[4px] font-black text-emerald-950">BATCH 08 · TERBATAS</span>
                <h3 className="mt-1.5 text-[12px] font-black leading-[1.03] tracking-[-.04em]">Reels dari nol sampai closing.</h3>
                <p className="mt-1 text-[4.5px] leading-[1.45] text-white/65">Kelas praktis untuk pemilik bisnis yang mau konsisten jualan lewat video pendek.</p>
                <div className="mt-2 grid aspect-[16/8] place-items-center rounded-md" style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }}><span className="grid size-7 place-items-center rounded-full bg-white/95 text-emerald-800 shadow"><CirclePlay className="size-4" /></span></div>
            </section>
            <section className="px-2.5 py-2">
                <div className="flex items-center gap-1.5"><div className="grid size-5 place-items-center rounded-full bg-amber-100 text-[6px] font-black">DM</div><div><p className="text-[5px] font-black">Dina Mahendra</p><p className="text-[3.8px] text-slate-500">Video strategist · 120K followers</p></div><BadgeCheck className="ml-auto size-2.5 text-emerald-600" /></div>
                <div className="mt-2 grid grid-cols-3 gap-1 text-center">
                    {[['18', 'Video'], ['6', 'Template'], ['Lifetime', 'Akses']].map(([value, label]) => <div key={label} className="rounded bg-white p-1 shadow-sm"><b className="block text-[5px]">{value}</b><span className="text-[3.5px] text-slate-400">{label}</span></div>)}
                </div>
                <div className="mt-2 rounded-md bg-white p-1.5 shadow-sm"><p className="text-[5px] font-black">Yang akan kamu kuasai</p><div className="mt-1 grid grid-cols-2 gap-x-1 gap-y-0.5">{['Cari ide tanpa buntu', 'Hook 3 detik', 'Shoot pakai HP', 'CTA yang menjual'].map((item) => <span key={item} className="flex items-center gap-0.5 text-[3.8px]"><Check className="size-1.5 text-emerald-600" />{item}</span>)}</div></div>
                <div className="mt-2 flex items-center justify-between"><div><span className="text-[3.5px] text-slate-400 line-through">Rp799K</span><b className="block text-[8px]">Rp349K</b></div><MiniButton>Gabung kelas</MiniButton></div>
            </section>
        </div>
    );
}

function FashionPreview({ primary, accent }: { primary: string; accent: string }) {
    return (
        <div className="min-h-full bg-[#fffaf8]">
            <div className="py-1 text-center text-[3.8px] font-black text-white" style={{ background: primary }}>GRATIS ONGKIR · MIN. BELANJA 300K</div>
            <Topbar brand="NARA Studio" />
            <section className="mx-1.5 grid grid-cols-[1fr_.82fr] overflow-hidden rounded-lg bg-[#f5e5df]">
                <div className="p-2.5"><p className="text-[4px] font-black uppercase tracking-[.14em] text-rose-700">The Linen Edit</p><h3 className="mt-1 text-[11px] font-black leading-[1.02] tracking-[-.04em]">Ringan dipakai.<br />Mudah dipadukan.</h3><p className="mt-1 text-[4px] leading-[1.4] text-slate-600">Koleksi yang mengikuti ritmemu, dari pagi hingga akhir pekan.</p><div className="mt-2"><MiniButton>Belanja koleksi</MiniButton></div></div>
                <div className="relative overflow-hidden" style={{ background: `linear-gradient(160deg, ${accent}45, #fff)` }}><span className="absolute left-1/2 top-3 h-16 w-9 -translate-x-1/2 rounded-t-[45%] rounded-b-lg bg-[#fbf2e8] shadow-[0_8px_18px_rgba(89,38,55,.18)]" /><span className="absolute left-1/2 top-1.5 size-5 -translate-x-1/2 rounded-full bg-[#c98770]" /><span className="absolute bottom-2 right-2 rounded-full bg-white/80 px-1 py-0.5 text-[3.5px] font-black">NEW</span></div>
            </section>
            <section className="px-2.5 py-2">
                <div className="flex items-center justify-between"><h4 className="text-[7px] font-black">Baru minggu ini</h4><Heart className="size-2.5" /></div>
                <div className="mt-1 grid grid-cols-2 gap-1.5">
                    <FashionProduct name="Sora Linen Set" price="Rp329.000" tone="#ead8c8" kind="top" />
                    <FashionProduct name="Mara Mini Bag" price="Rp249.000" tone="#ead0d8" kind="bag" />
                </div>
                <div className="mt-2 flex items-center justify-around rounded-md border border-rose-100 bg-white py-1.5 text-center"><span><b className="block text-[4.5px]">7 hari</b><small className="text-[3.5px] text-slate-400">Penukaran</small></span><span><b className="block text-[4.5px]">Secure</b><small className="text-[3.5px] text-slate-400">Pembayaran</small></span><span><b className="block text-[4.5px]">4.9/5</b><small className="text-[3.5px] text-slate-400">Review</small></span></div>
            </section>
        </div>
    );
}

function FashionProduct({ name, price, tone, kind }: { name: string; price: string; tone: string; kind: 'top' | 'bag' }) {
    return <div><div className="grid aspect-square place-items-center rounded-md" style={{ background: tone }}>{kind === 'bag' ? <div className="relative h-7 w-9 rounded-b-lg rounded-t-sm bg-[#873b4f] shadow-md before:absolute before:-top-2 before:left-1/2 before:h-3 before:w-5 before:-translate-x-1/2 before:rounded-t-full before:border-2 before:border-[#873b4f]" /> : <div className="relative h-10 w-10 rounded-b-md bg-[#f9f2e8] shadow-md before:absolute before:-left-2 before:top-0 before:h-4 before:w-4 before:-rotate-12 before:bg-[#f9f2e8] after:absolute after:-right-2 after:top-0 after:h-4 after:w-4 after:rotate-12 after:bg-[#f9f2e8]" />}</div><p className="mt-1 text-[4.7px] font-black">{name}</p><p className="text-[4.5px] text-rose-700">{price}</p></div>;
}

function AffiliatePreview({ primary, accent }: { primary: string; accent: string }) {
    const products = [
        ['Lampu Meja Nori', '4.9', '89K', '💡'],
        ['Mic Mini V2', '4.8', '159K', '🎙️'],
        ['Tumbler Kiyo', '4.9', '79K', '🥤'],
    ];
    return (
        <div className="min-h-full bg-[#fff9f3]">
            <Topbar brand="Racun Rani" />
            <section className="mx-2 rounded-lg bg-[#221c18] p-2.5 text-white">
                <div className="flex items-center gap-2"><div className="grid size-8 place-items-center rounded-full text-[10px] font-black" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>RR</div><div><p className="flex items-center gap-0.5 text-[7px] font-black">Rani Reviews <BadgeCheck className="size-2 text-orange-400" /></p><p className="text-[4px] text-white/60">Home · beauty · creator gear</p></div></div>
                <h3 className="mt-2 text-[11px] font-black leading-[1.05]">Barang bagus yang sudah aku coba.</h3>
                <p className="mt-1 text-[4.5px] leading-[1.4] text-white/60">Review jujur, link gampang dicari, tidak bikin dompet nyesel.</p>
            </section>
            <section className="px-2.5 py-2">
                <div className="flex items-end justify-between"><div><p className="text-[3.8px] font-black uppercase tracking-[.12em] text-orange-600">PICKS OF THE WEEK</p><h4 className="text-[7px] font-black">Lagi sering kupakai</h4></div><span className="text-[4px] font-bold">Semua →</span></div>
                <div className="mt-1.5 space-y-1">
                    {products.map(([name, rating, price, emoji], index) => <div key={name} className="flex items-center rounded-md border border-orange-100 bg-white p-1 shadow-sm"><div className="grid size-8 place-items-center rounded-md bg-orange-50 text-[14px]">{emoji}</div><div className="ml-1.5 min-w-0 flex-1"><b className="block text-[5px]">{index + 1}. {name}</b><span className="flex items-center gap-0.5 text-[3.8px] text-slate-400"><Star className="size-1.5 fill-amber-400 text-amber-400" />{rating} · 1,2rb terjual</span><b className="text-[4.5px] text-orange-700">Rp{price}</b></div><span className="grid size-4 place-items-center rounded-full bg-[#171722] text-white"><ChevronRight className="size-2" /></span></div>)}
                </div>
                <div className="mt-2 flex items-center gap-1 rounded-md bg-orange-100 p-1.5"><MessageCircle className="size-3 text-orange-700" /><p className="text-[4px] font-bold">Mau aku review produkmu? Kirim di sini →</p></div>
            </section>
        </div>
    );
}

function FoodPreview({ primary, accent }: { primary: string; accent: string }) {
    return (
        <div className="min-h-full bg-[#fffbf2]">
            <Topbar brand="Dapur Sore" />
            <section className="mx-1.5 overflow-hidden rounded-lg text-white" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>
                <div className="grid grid-cols-[1.08fr_.92fr]">
                    <div className="p-2.5"><span className="inline-flex items-center gap-0.5 rounded-full bg-white/15 px-1 py-0.5 text-[3.5px] font-black"><Clock3 className="size-1.5" /> BUKA · 10–21</span><h3 className="mt-1.5 text-[11px] font-black leading-[1.02]">Comfort food,<br />rasa rumah.</h3><p className="mt-1 text-[4px] text-white/75">Fresh setiap hari. Siap antar sampai depan pintu.</p><div className="mt-2"><MiniButton light>Pesan sekarang</MiniButton></div></div>
                    <div className="grid place-items-center bg-white/10"><div className="relative grid size-16 place-items-center rounded-full bg-[#f7e1aa] text-[28px] shadow-xl">🍜<span className="absolute -right-1 top-1 grid size-5 place-items-center rounded-full bg-white text-[7px] font-black text-amber-700">4.9</span></div></div>
                </div>
            </section>
            <section className="px-2.5 py-2">
                <div className="flex items-center justify-between"><h4 className="text-[7px] font-black">Menu favorit</h4><span className="text-[4px] font-bold text-amber-700">Lihat menu →</span></div>
                <div className="mt-1.5 grid grid-cols-2 gap-1.5">
                    <FoodCard emoji="🍛" name="Nasi Ayam Kemangi" price="28K" tone="#f7deb2" />
                    <FoodCard emoji="🥟" name="Dimsum Mentai" price="24K" tone="#f3d4b4" />
                </div>
                <div className="mt-2 rounded-md bg-[#1d2c24] p-1.5 text-white"><div className="flex items-center gap-1"><span className="grid size-4 place-items-center rounded-full bg-[#25D366]"><MessageCircle className="size-2" /></span><div className="flex-1"><b className="block text-[4.5px]">Pesan via WhatsApp</b><span className="block text-[3.5px] text-white/55">Respons ± 3 menit</span></div><ArrowRight className="size-2.5" /></div></div>
                <div className="mt-1.5 flex justify-between text-[3.5px] font-bold text-slate-500"><span>📍 Jakarta Selatan</span><span>⭐ 2.100+ pesanan</span></div>
            </section>
        </div>
    );
}

function FoodCard({ emoji, name, price, tone }: { emoji: string; name: string; price: string; tone: string }) {
    return <div className="overflow-hidden rounded-md bg-white shadow-sm"><div className="grid aspect-[5/3] place-items-center text-[20px]" style={{ background: tone }}>{emoji}</div><div className="p-1"><b className="block text-[4.5px]">{name}</b><span className="text-[4.5px] font-black text-amber-700">Rp{price}</span></div></div>;
}

function PersonalPreview({ primary, accent }: { primary: string; accent: string }) {
    const links = [
        [<MonitorPlay className="size-2.5" />, 'Kelas Notion untuk Pemula'],
        [<BookOpen className="size-2.5" />, 'E-book: Sistem Kerja Tenang'],
        [<MessageCircle className="size-2.5" />, '1-on-1 Mentoring'],
    ];
    return (
        <div className="min-h-full bg-[#f5f5f4] px-2.5 py-3 text-center">
            <div className="mx-auto grid size-11 place-items-center rounded-full text-[13px] font-black text-white shadow-lg" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>AM</div>
            <p className="mt-1.5 flex items-center justify-center gap-0.5 text-[8px] font-black">Alya Mahesa <BadgeCheck className="size-2.5 fill-slate-900 text-white" /></p>
            <p className="mt-0.5 text-[4.5px] font-semibold text-slate-500">Productivity educator · slow living enthusiast</p>
            <div className="mt-1.5 flex justify-center gap-1"><span className="grid size-4 place-items-center rounded-full bg-white"><Camera className="size-2" /></span><span className="grid size-4 place-items-center rounded-full bg-white"><MonitorPlay className="size-2" /></span><span className="grid size-4 place-items-center rounded-full bg-white"><MessageCircle className="size-2" /></span></div>
            <div className="mt-2 space-y-1.5">
                {links.map(([icon, label]) => <div key={label as string} className="flex items-center rounded-full border border-slate-200 bg-white p-1.5 text-left shadow-sm"><span className="grid size-5 place-items-center rounded-full bg-slate-100">{icon}</span><b className="ml-1.5 flex-1 text-[4.7px]">{label}</b><ChevronRight className="size-2" /></div>)}
            </div>
            <div className="mt-2 rounded-lg bg-[#171722] p-2 text-left text-white"><div className="flex items-center gap-2"><div className="grid size-10 shrink-0 place-items-center rounded-md text-[14px]" style={{ background: `linear-gradient(145deg, ${primary}, ${accent})` }}>✓</div><div><p className="text-[3.5px] font-black uppercase tracking-[.12em] text-white/50">Produk terbaru</p><p className="mt-0.5 text-[6px] font-black">Weekly Reset Kit</p><p className="text-[4px] text-white/55">Notion template · Rp69K</p></div></div></div>
            <p className="mt-2 flex items-center justify-center gap-1 text-[3.5px] font-bold text-slate-400"><Link2 className="size-1.5" /> alyamahesa.id</p>
        </div>
    );
}
