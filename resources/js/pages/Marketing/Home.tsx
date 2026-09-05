import { Link } from '@inertiajs/react';
import {
    ArrowRight, BookOpen, CalendarDays, Check, CheckCircle2, ChevronRight, CircleDollarSign,
    CreditCard, Download, Globe2, HeartHandshake, Layers3, Link2, LockKeyhole,
    MousePointerClick, PackageCheck, Palette, Play, Quote, Search, ShoppingBag, Sparkles, Star,
    TrendingUp, Users, WalletCards, Zap,
} from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { TemplateLivePreview, type TemplateBlueprintBlock } from '@/components/TemplateLivePreview';
import MarketplaceFront, { type MarketplaceHomeData } from '@/components/marketplace/MarketplaceFront';
import { Badge, ButtonLink } from '@/components/ui';
import MarketingLayout from '@/layouts/MarketingLayout';
import { cn, formatIDR } from '@/lib/utils';

interface Plan {
    slug: string;
    name: string;
    tagline: string | null;
    price_monthly: number;
    transaction_fee_percent: number;
    highlights: string[];
}

interface Showcase {
    username: string;
    name: string;
    tagline: string | null;
    bio: string | null;
    avatar_url: string | null;
    cover_url?: string | null;
    products_count: number;
    primary_color: string | null;
}

interface TemplateCard {
    slug: string;
    name: string;
    tagline: string | null;
    use_case: string | null;
    block_count: number;
    theme: Record<string, unknown> | null;
    /** The real blueprint, rendered as the preview. */
    blocks?: TemplateBlueprintBlock[];
}

export default function Home({ plans, showcase, templates, marketplace }: { plans: Plan[]; showcase: Showcase[]; templates: TemplateCard[]; marketplace: MarketplaceHomeData }) {
    return (
        <MarketingLayout
            title="Satu link untuk semua jualanmu"
            description="Bikin toko online, terima pembayaran, kirim produk otomatis, dan kelola bisnis kreator dari satu tempat."
        >
            <Hero banners={marketplace.banners} />
            <MarketplaceFront data={marketplace} />
            <TrustRail />
            <SellAnything />
            <CommerceFlow />
            <FeatureCards />
            <AnalyticsStory />
            <TemplateGallery templates={templates} />
            <StoreShowcase stores={showcase} />
            <Testimonials />
            <Pricing plans={plans} />
            <Faq />
            <FinalCta />
        </MarketingLayout>
    );
}

function Hero({ banners }: { banners: MarketplaceHomeData['banners'] }) {
    const [active, setActive] = useState(0);
    const banner = banners[active];

    useEffect(() => {
        if (banners.length < 2) return;

        const timer = window.setInterval(() => setActive((value) => (value + 1) % banners.length), 9000);
        return () => window.clearInterval(timer);
    }, [banners.length]);

    return (
        <section className="px-3 pt-3 sm:px-5 sm:pt-5">
            <div className="jy-hero relative mx-auto min-h-[760px] max-w-[1500px] overflow-hidden rounded-[2rem] border border-white/70 px-4 pt-16 shadow-[0_28px_100px_rgba(102,64,180,.16)] sm:px-8 lg:min-h-[880px] lg:pt-20">
                {(banner?.desktop_image_url || banner?.mobile_image_url) && (
                    <picture className="pointer-events-none absolute inset-0 opacity-20 mix-blend-multiply">
                        {banner.mobile_image_url && <source media="(max-width: 639px)" srcSet={banner.mobile_image_url} />}
                        <img src={banner.desktop_image_url ?? banner.mobile_image_url ?? ''} alt="" className="size-full object-cover" fetchPriority="high" />
                    </picture>
                )}
                <div className="jy-orb jy-orb-one" aria-hidden="true" />
                <div className="jy-orb jy-orb-two" aria-hidden="true" />
                <div className="jy-cloud jy-cloud-left" aria-hidden="true" />
                <div className="jy-cloud jy-cloud-right" aria-hidden="true" />

                <div className="relative z-10 mx-auto max-w-4xl text-center">
                    <Reveal>
                        <div className="mb-6 inline-flex items-center gap-3 text-[11px] font-extrabold uppercase tracking-[.18em] text-neutral-700">
                            <span className="h-px w-8 bg-neutral-500/50" />
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/80 bg-white/55 px-3 py-1.5 shadow-sm backdrop-blur-xl">
                                <Sparkles className="size-3.5 text-violet-600" /> {banner?.eyebrow ?? 'Dibuat untuk kreator Indonesia'}
                            </span>
                            <span className="h-px w-8 bg-neutral-500/50" />
                        </div>
                    </Reveal>
                    <Reveal delay={90}>
                        <h1 className="mx-auto max-w-4xl text-balance text-[2.75rem] font-extrabold leading-[1.02] tracking-[-.055em] text-[#111119] sm:text-6xl lg:text-[5.15rem]">
                            {banner?.title ?? <>Temukan karya creator.<span className="block">Belanja langsung dari <span className="gradient-text">orangnya.</span></span></>}
                        </h1>
                    </Reveal>
                    <Reveal delay={170}>
                        <p className="mx-auto mt-6 max-w-2xl text-balance text-base leading-7 text-neutral-600 sm:text-lg">
                            {banner?.description ?? 'Jelajahi produk digital, kelas, jasa, event, dan karya pilihan dari creator Indonesia. Atau buka tokomu sendiri dalam satu link.'}
                        </p>
                    </Reveal>
                    <Reveal delay={240}>
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <ButtonLink href={banner?.cta_url ?? '/explore'} size="lg" className="group rounded-full bg-[#111119] px-7 text-white shadow-[0_14px_30px_rgba(17,17,25,.22)] hover:bg-black">
                                {banner?.cta_label ?? 'Jelajahi produk'} <ArrowRight className="transition-transform group-hover:translate-x-1" />
                            </ButtonLink>
                            <Link href="/register" className="inline-flex h-13 items-center gap-2 rounded-full border border-black/10 bg-white/55 px-6 text-sm font-bold text-neutral-800 backdrop-blur-xl transition hover:bg-white">
                                <Play className="size-4 fill-current" /> Mulai jualan
                            </Link>
                        </div>
                        <form action="/explore" method="get" role="search" className="mx-auto mt-5 flex max-w-xl items-center gap-2 rounded-2xl border border-white/80 bg-white/75 p-2 shadow-[0_12px_35px_rgba(55,34,100,.09)] backdrop-blur-xl">
                            <Search className="ml-2 size-5 shrink-0 text-neutral-400" aria-hidden="true" />
                            <input name="q" aria-label="Cari produk marketplace" className="h-10 min-w-0 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-neutral-400" placeholder="Cari produk, creator, kategori, atau tag" />
                            <button className="h-10 rounded-xl bg-[#171722] px-5 text-xs font-extrabold text-white transition hover:bg-violet-700">Cari</button>
                        </form>
                        <p className="mt-4 text-xs font-semibold text-neutral-500">Gratis untuk mulai · Tanpa kartu kredit · Siap dibagikan hari ini</p>
                        {banners.length > 1 && <div className="mt-5 flex justify-center gap-2" aria-label="Pilih campaign banner">{banners.map((item, index) => <button key={item.id} type="button" onClick={() => setActive(index)} aria-label={`Tampilkan banner ${index + 1}`} aria-current={index === active} className={cn('h-1.5 rounded-full transition-all', index === active ? 'w-8 bg-[#171722]' : 'w-2 bg-[#171722]/25 hover:bg-[#171722]/45')} />)}</div>}
                    </Reveal>
                </div>
                <Reveal delay={320} className="relative z-10 mx-auto mt-14 max-w-5xl lg:mt-16"><HeroProduct /></Reveal>
            </div>
        </section>
    );
}

function HeroProduct() {
    const sales = [
        { name: 'Naya', product: 'Template Konten 30 Hari', amount: 129000 },
        { name: 'Ardi', product: 'Kelas Reels dari Nol', amount: 349000 },
        { name: 'Mira', product: 'Sesi Konsultasi Brand', amount: 275000 },
    ];
    const [sale, setSale] = useState(0);
    useEffect(() => {
        const timer = window.setInterval(() => setSale((value) => (value + 1) % sales.length), 3200);
        return () => window.clearInterval(timer);
    }, [sales.length]);

    return (
        <div className="relative mx-auto h-[400px] max-w-4xl sm:h-[470px] lg:h-[540px]">
            <div className="absolute inset-x-2 top-8 overflow-hidden rounded-[1.65rem] border border-white/75 bg-white/88 p-3 shadow-[0_35px_90px_rgba(67,38,120,.2)] backdrop-blur-2xl sm:inset-x-10 sm:p-4 lg:inset-x-16">
                <div className="flex h-9 items-center gap-2 border-b border-neutral-100 px-2 pb-3">
                    <span className="size-2.5 rounded-full bg-[#ff6b6b]" /><span className="size-2.5 rounded-full bg-[#ffd166]" /><span className="size-2.5 rounded-full bg-[#72d6a8]" />
                    <div className="mx-auto flex h-6 w-44 items-center justify-center rounded-full bg-neutral-100 text-[9px] font-semibold text-neutral-400">jualanyok.id/kreatorkita</div>
                </div>
                <div className="grid gap-3 p-2 pt-4 md:grid-cols-[160px_1fr]">
                    <div className="hidden rounded-2xl bg-[#171722] p-4 text-white md:block">
                        <img src="/images/jualanyok-mark.png" alt="" className="size-8" />
                        <div className="mt-7 space-y-2">
                            {['Ringkasan', 'Toko', 'Produk', 'Pesanan'].map((item, index) => <div key={item} className={cn('rounded-lg px-3 py-2 text-[10px] font-semibold', index === 0 ? 'bg-white/12 text-white' : 'text-white/55')}>{item}</div>)}
                        </div>
                    </div>
                    <div className="rounded-2xl bg-[#f8f7fb] p-3 sm:p-5">
                        <div className="flex items-center justify-between">
                            <div><p className="text-[10px] font-semibold text-neutral-400">Selamat datang, KreatorKita</p><p className="mt-0.5 text-sm font-extrabold text-neutral-900 sm:text-base">Bisnismu hari ini</p></div>
                            <div className="rounded-full bg-white px-3 py-1.5 text-[9px] font-bold text-neutral-600 shadow-sm">30 hari terakhir</div>
                        </div>
                        <div className="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                            {[
                                { label: 'Penjualan', value: 'Rp12,8 jt', tone: 'bg-[#f0e8ff]' },
                                { label: 'Pesanan', value: '148', tone: 'bg-[#e7f6f1]' },
                                { label: 'Konversi', value: '8,4%', tone: 'bg-[#fff0e6]' },
                            ].map((item) => <div key={item.label} className={cn('rounded-xl p-2.5 sm:p-3.5', item.tone)}><p className="text-[8px] font-semibold text-neutral-500 sm:text-[10px]">{item.label}</p><p className="mt-1 text-xs font-extrabold text-neutral-900 sm:text-lg">{item.value}</p></div>)}
                        </div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-[1.55fr_1fr]">
                            <div className="rounded-xl bg-white p-3.5 shadow-sm">
                                <div className="flex items-center justify-between"><p className="text-[10px] font-bold text-neutral-700">Performa penjualan</p><TrendingUp className="size-3.5 text-emerald-500" /></div>
                                <div className="mt-5 flex h-20 items-end gap-1.5 sm:h-28">{[28, 44, 35, 56, 48, 72, 60, 83, 65, 92, 78, 100].map((height, index) => <span key={index} className="flex-1 rounded-t bg-gradient-to-t from-violet-600 to-fuchsia-300" style={{ height: `${height}%`, opacity: 0.55 + index * 0.035 }} />)}</div>
                            </div>
                            <div className="rounded-xl bg-white p-3.5 shadow-sm">
                                <p className="text-[10px] font-bold text-neutral-700">Produk terlaris</p>
                                <div className="mt-3 space-y-2.5">{[['Template Konten', '63%'], ['Kelas Reels', '24%'], ['Konsultasi', '13%']].map(([name, value], index) => <div key={name}><div className="flex justify-between text-[8px] font-semibold text-neutral-500 sm:text-[9px]"><span>{name}</span><span>{value}</span></div><div className="mt-1 h-1 rounded-full bg-neutral-100"><div className="h-full rounded-full bg-violet-500" style={{ width: value, opacity: 1 - index * 0.18 }} /></div></div>)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="jy-float absolute -left-1 top-1 rounded-2xl border border-white/80 bg-white/92 p-3 shadow-xl backdrop-blur sm:left-0 sm:top-14 sm:p-4">
                <div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><CircleDollarSign className="size-4.5" /></span><div><p className="text-[9px] font-bold uppercase tracking-wide text-emerald-600">Pembayaran berhasil</p><p className="mt-0.5 text-xs font-extrabold text-neutral-900">+{formatIDR(sales[sale].amount)}</p><p className="max-w-36 truncate text-[9px] text-neutral-500">{sales[sale].name} · {sales[sale].product}</p></div></div>
            </div>
            <div className="jy-float-delayed absolute -right-1 bottom-14 hidden rounded-2xl border border-white/80 bg-white/92 p-4 shadow-xl backdrop-blur sm:block lg:right-2 lg:bottom-24">
                <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-700"><PackageCheck className="size-5" /></span><div><p className="text-xs font-extrabold text-neutral-900">Produk terkirim otomatis</p><p className="mt-0.5 text-[10px] text-neutral-500">Link download aman sudah dikirim</p></div></div>
            </div>
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-36 bg-gradient-to-t from-white via-white/90 to-transparent" />
        </div>
    );
}

function TrustRail() {
    const items = [[BookOpen, 'Produk digital'], [CalendarDays, 'Kelas & event'], [HeartHandshake, 'Jasa konsultasi'], [ShoppingBag, 'Produk fisik'], [Users, 'Membership'], [Link2, 'Affiliate']] as const;
    return <section className="border-b border-neutral-200/70 bg-white py-9 dark:border-line dark:bg-app"><div className="mx-auto max-w-6xl px-5 text-center"><p className="text-xs font-bold uppercase tracking-[.18em] text-neutral-400">Satu rumah untuk semua model jualanmu</p><div className="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-6">{items.map(([Icon, label]) => <div key={label} className="flex items-center justify-center gap-2 text-xs font-bold text-neutral-500 dark:text-muted"><Icon className="size-4 text-violet-500" /> {label}</div>)}</div></div></section>;
}

function SellAnything() {
    return <SectionShell className="pt-24 lg:pt-32"><div className="grid items-center gap-14 lg:grid-cols-2 lg:gap-24"><Reveal><SectionCopy kicker="TOKO MILIKMU" title="Brand kamu di depan. Teknologi kami bekerja di belakang." body="Susun halaman toko dengan block yang fleksibel, pilih warna sendiri, sambungkan domain, lalu publikasikan. Tanpa template yang terasa generik." points={['Drag, susun, dan publish dalam hitungan menit', 'Tampil mulus di mobile, tablet, dan desktop', 'SEO, pixel, dan custom domain siap dipakai']} /></Reveal><Reveal delay={120}><StoreBuilderVisual /></Reveal></div></SectionShell>;
}

function StoreBuilderVisual() {
    return (
        <div className="relative overflow-hidden rounded-[2rem] bg-[#e7f7f3] p-5 shadow-[0_25px_80px_rgba(29,107,90,.12)] sm:p-8 dark:bg-emerald-950/30">
            <div className="absolute -right-16 -top-16 size-52 rounded-full bg-white/55 blur-3xl" />
            <div className="relative grid min-h-[430px] gap-4 rounded-2xl border border-white/80 bg-white/70 p-3 backdrop-blur sm:grid-cols-[120px_1fr]">
                <div className="hidden rounded-xl bg-white p-3 shadow-sm sm:block"><p className="text-[9px] font-extrabold text-neutral-800">Tambah block</p><div className="mt-3 space-y-2">{[['Hero', Palette], ['Produk', ShoppingBag], ['Testimoni', Quote], ['FAQ', Layers3]].map(([label, Icon]) => { const BlockIcon = Icon as typeof Palette; return <div key={label as string} className="flex items-center gap-2 rounded-lg border border-neutral-100 p-2 text-[8px] font-semibold text-neutral-500"><BlockIcon className="size-3.5 text-violet-500" />{label as string}</div>; })}</div></div>
                <div className="overflow-hidden rounded-xl bg-[#fbf7ff] shadow-sm"><div className="relative h-32 bg-gradient-to-br from-[#8b5cf6] via-[#c95dc8] to-[#ff8a6b] p-5 text-white"><span className="rounded-full bg-white/20 px-2 py-1 text-[7px] font-bold backdrop-blur">KREATOR PILIHAN</span><p className="mt-3 max-w-48 text-xl font-black leading-tight">Bikin konten yang orang tunggu.</p><span className="mt-3 inline-flex rounded-full bg-white px-3 py-1.5 text-[8px] font-bold text-violet-700">Lihat produknya</span></div><div className="grid grid-cols-2 gap-2 p-3">{['Content Plan 30 Hari', 'Kelas Reels Viral'].map((name, index) => <div key={name} className="rounded-lg bg-white p-2.5 shadow-sm"><div className={cn('h-16 rounded-md', index ? 'bg-[#fff0e6]' : 'bg-[#eee6ff]')} /><p className="mt-2 text-[8px] font-extrabold text-neutral-800">{name}</p><p className="mt-0.5 text-[8px] font-bold text-violet-600">{index ? 'Rp349.000' : 'Rp129.000'}</p></div>)}</div></div>
            </div>
            <div className="absolute bottom-8 right-8 flex items-center gap-2 rounded-full bg-[#171722] px-4 py-2 text-[9px] font-bold text-white shadow-lg"><CheckCircle2 className="size-3.5 text-emerald-400" /> Perubahan tersimpan</div>
        </div>
    );
}

function CommerceFlow() {
    const items = [
        { step: '01', icon: MousePointerClick, title: 'Pembeli pilih produk', body: 'Produk, varian, kupon, dan stok dihitung langsung dari data toko.', tone: 'bg-[#fff2d8]' },
        { step: '02', icon: CreditCard, title: 'Bayar dengan nyaman', body: 'QRIS, virtual account, e-wallet, atau transfer—statusnya terpantau otomatis.', tone: 'bg-[#e7f7f3]' },
        { step: '03', icon: Zap, title: 'Pesanan langsung jalan', body: 'Akses digital, kelas, tiket, dan notifikasi terkirim tanpa kerja manual.', tone: 'bg-[#eee7ff]' },
    ];
    return <SectionShell className="py-16 sm:py-20 lg:py-28"><div className="mx-auto max-w-3xl text-center"><Reveal><p className="section-kicker">ALUR YANG RINGKAS</p><h2 className="section-title">Dari “klik link” sampai produk diterima, semuanya nyambung.</h2><p className="section-body mx-auto">Pengalaman checkout yang pendek untuk pembeli. Sistem operasional yang lengkap untuk kamu.</p></Reveal></div><div className="mt-9 grid gap-3 sm:mt-12 sm:gap-4 lg:mt-14 lg:grid-cols-3">{items.map((item, index) => <Reveal key={item.step} delay={index * 100}><div className={cn('group relative min-h-[210px] overflow-hidden rounded-[1.5rem] p-5 transition duration-500 hover:-translate-y-1 sm:min-h-64 sm:rounded-[1.75rem] sm:p-6 lg:min-h-72 lg:p-7', item.tone)}><span className="text-[10px] font-black tracking-[.18em] text-neutral-400 sm:text-xs">{item.step}</span><item.icon className="mt-6 size-7 text-neutral-900 sm:mt-8 sm:size-8 lg:mt-10" strokeWidth={1.7} /><h3 className="mt-4 text-lg font-extrabold tracking-tight text-neutral-900 sm:mt-5 sm:text-xl">{item.title}</h3><p className="mt-2 text-[13px] leading-5 text-neutral-600 sm:mt-3 sm:text-sm sm:leading-6">{item.body}</p><span className="absolute -bottom-14 -right-12 size-32 rounded-full border-[22px] border-white/35 transition-transform duration-700 group-hover:scale-110 sm:-bottom-12 sm:-right-10 sm:size-36 sm:border-[24px]" /></div></Reveal>)}</div></SectionShell>;
}

function FeatureCards() {
    const cards = [
        { icon: LockKeyhole, title: 'File digital tetap aman', body: 'Link bertanda tangan, masa berlaku, batas unduhan, dan akses yang bisa dicabut kapan saja.', visual: <DownloadVisual /> },
        { icon: HeartHandshake, title: 'Affiliate yang transparan', body: 'Klik, konversi, dan komisi tercatat otomatis. Kamu tentukan produk dan besar komisinya.', visual: <AffiliateVisual /> },
        { icon: WalletCards, title: 'Saldo yang bisa dilacak', body: 'Setiap rupiah tercatat di ledger. Lihat saldo tertahan, tersedia, dan riwayat pencairan.', visual: <WalletVisual /> },
    ];
    return <SectionShell className="py-16 sm:py-20 lg:py-24"><div className="mx-auto max-w-3xl text-center"><Reveal><p className="section-kicker">OPERASIONAL TANPA DRAMA</p><h2 className="section-title">Terlihat sederhana di depan. Serius dan aman di belakang.</h2></Reveal></div><div className="mt-9 grid gap-3 sm:mt-12 sm:gap-4 lg:mt-14 lg:grid-cols-3">{cards.map((card, index) => <Reveal key={card.title} delay={index * 90}><article className="flex min-h-0 flex-col overflow-hidden rounded-[1.5rem] border border-neutral-200/80 bg-[#f7f7f8] p-5 dark:border-line dark:bg-surface sm:min-h-[420px] sm:rounded-[1.75rem] sm:p-6 lg:min-h-[460px]"><span className="grid size-9 place-items-center rounded-xl bg-white text-violet-600 shadow-sm dark:bg-surface-2 sm:size-10"><card.icon className="size-4.5 sm:size-5" /></span><h3 className="mt-4 text-lg font-extrabold tracking-tight sm:mt-5 sm:text-xl">{card.title}</h3><p className="mt-2 text-[13px] leading-5 text-muted sm:text-sm sm:leading-6">{card.body}</p><div className="mt-5 sm:mt-auto sm:pt-7">{card.visual}</div></article></Reveal>)}</div></SectionShell>;
}

function DownloadVisual() { return <div className="rounded-2xl bg-white p-4 shadow-sm dark:bg-surface-2"><div className="flex items-center gap-3"><span className="grid size-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><BookOpen className="size-5" /></span><div className="min-w-0 flex-1"><p className="truncate text-xs font-bold">Content-Plan-30-Hari.pdf</p><p className="mt-1 text-[10px] text-muted">Akses aktif · 2 dari 5 unduhan</p></div><Download className="size-4 text-violet-600" /></div><div className="mt-4 h-1.5 rounded-full bg-neutral-100 dark:bg-white/10"><div className="h-full w-2/5 rounded-full bg-violet-500" /></div></div>; }
function AffiliateVisual() { return <div className="rounded-2xl bg-white p-4 shadow-sm dark:bg-surface-2"><div className="flex items-center justify-between"><div><p className="text-[10px] text-muted">Komisi bulan ini</p><p className="mt-1 text-xl font-extrabold">Rp2.840.000</p></div><TrendingUp className="size-5 text-emerald-500" /></div><div className="mt-5 flex h-16 items-end gap-2">{[30, 48, 38, 62, 54, 80, 72].map((height, i) => <span key={i} className="flex-1 rounded-t bg-emerald-300" style={{ height: `${height}%`, opacity: .45 + i * .07 }} />)}</div></div>; }
function WalletVisual() { return <div className="rounded-2xl bg-[#1b1824] p-4 text-white shadow-sm"><p className="text-[10px] text-white/55">Saldo tersedia</p><p className="mt-1 text-2xl font-extrabold">Rp8.240.500</p><div className="mt-5 flex items-center justify-between border-t border-white/10 pt-3"><span className="text-[10px] text-white/55">Pencairan berikutnya</span><span className="rounded-full bg-white/10 px-2.5 py-1 text-[9px] font-bold">Tarik saldo</span></div></div>; }

function AnalyticsStory() {
    return <SectionShell><div className="grid items-center gap-14 lg:grid-cols-2 lg:gap-24"><Reveal><AnalyticsVisual /></Reveal><Reveal delay={120}><SectionCopy kicker="KEPUTUSAN BERBASIS DATA" title="Tahu apa yang laku, siapa yang datang, dan dari mana mereka menemukanmu." body="Dashboard menyatukan trafik, klik block, conversion rate, produk terlaris, pelanggan, dan nilai penjualan. Angkanya jelas, tindakannya juga." points={['Ringkasan performa tanpa spreadsheet manual', 'UTM dan sumber traffic ikut tercatat', 'Data pelanggan dan leads siap diekspor']} /></Reveal></div></SectionShell>;
}

function AnalyticsVisual() {
    return <div className="relative overflow-hidden rounded-[2rem] bg-[#f3e9fb] p-6 sm:p-9 dark:bg-violet-950/30"><div className="rounded-2xl bg-white p-5 shadow-[0_20px_60px_rgba(84,52,122,.12)] dark:bg-surface"><div className="flex items-center justify-between"><div><p className="text-[10px] font-bold uppercase tracking-wide text-muted">Total penjualan</p><p className="mt-1 text-2xl font-extrabold">Rp24,6 juta</p></div><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-600">+18,4%</span></div><div className="mt-7 flex h-44 items-end gap-2">{[22, 35, 31, 48, 41, 56, 52, 69, 63, 82, 72, 95].map((height, index) => <span key={index} className="flex-1 rounded-t-md bg-gradient-to-t from-violet-600 to-fuchsia-300" style={{ height: `${height}%`, opacity: .48 + index * .035 }} />)}</div><div className="mt-3 flex justify-between text-[8px] font-semibold text-neutral-400"><span>1 Agu</span><span>7 Agu</span><span>14 Agu</span><span>21 Agu</span><span>30 Agu</span></div></div><div className="absolute bottom-5 right-4 rounded-xl border border-white/80 bg-white/90 p-3 shadow-lg backdrop-blur dark:bg-surface/90"><div className="flex items-center gap-2"><Globe2 className="size-4 text-violet-500" /><div><p className="text-[9px] text-muted">Sumber teratas</p><p className="text-xs font-extrabold">Instagram · 46%</p></div></div></div></div>;
}

function TemplateGallery({ templates }: { templates: TemplateCard[] }) {
    if (!templates.length) return null;
    const themes: Record<string, [string, string]> = {
        'creator-digital': ['#6D28D9', '#A855F7'],
        'freelancer-jasa': ['#1E293B', '#3B82F6'],
        'kelas-online': ['#047857', '#10B981'],
        'fashion-fisik': ['#DB2777', '#F472B6'],
        'affiliate-creator': ['#EA580C', '#FB923C'],
        'food-beverage': ['#B45309', '#F59E0B'],
        'minimal-link': ['#111827', '#4B5563'],
    };

    return (
        <section className="bg-[#f5f5f6] py-24 dark:bg-subtle lg:py-32">
            <div className="mx-auto max-w-6xl px-5 sm:px-6">
                <Reveal>
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-2xl">
                            <p className="section-kicker">TEMPLATE PILIHAN</p>
                            <h2 className="section-title">Mulai dari layout yang sudah punya arah.</h2>
                            <p className="section-body">Lihat langsung contoh brand, produk, harga, dan alur jualannya. Pilih yang paling dekat dengan bisnismu, lalu jadikan sepenuhnya milikmu.</p>
                        </div>
                        <Link href="/templates" className="inline-flex items-center gap-1 text-sm font-extrabold text-violet-600">Semua template <ArrowRight className="size-4" /></Link>
                    </div>
                </Reveal>

                <div className="-mx-5 mt-10 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-5 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4">
                    {templates.map((template, index) => {
                        const [primary, accent] = themes[template.slug] ?? ['#6D28D9', '#FB7185'];
                        return (
                            <Reveal key={template.slug} delay={index * 80} className="h-full w-[84vw] max-w-[340px] shrink-0 snap-start sm:w-auto sm:max-w-none">
                                <Link href={`/templates/${template.slug}/demo`} className="group flex h-full flex-col rounded-[1.5rem] border border-neutral-200/80 bg-white p-3 transition duration-500 hover:-translate-y-1 hover:shadow-xl dark:border-line dark:bg-surface">
                                    <div className="relative overflow-hidden rounded-[1.05rem] bg-neutral-100">
                                        <TemplateLivePreview blueprint={template.blocks ?? []} theme={template.theme ?? {}} storeName={template.name} tagline={template.tagline} templateSlug={template.slug} className="aspect-[4/5] overflow-hidden bg-white transition duration-700 group-hover:scale-[1.025]" />
                                        <div className="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-black/12 to-transparent" />
                                        <span className="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full border border-white/80 bg-white/90 px-2 py-1 text-[9px] font-extrabold text-neutral-800 shadow-sm backdrop-blur">Lihat demo <ArrowRight className="size-2.5" /></span>
                                    </div>
                                    <div className="flex flex-1 items-start justify-between gap-3 px-1 pb-1 pt-4">
                                        <div>
                                            <p className="font-extrabold">{template.name}</p>
                                            <p className="mt-1 line-clamp-2 text-xs leading-5 text-muted">{template.tagline ?? template.use_case}</p>
                                        </div>
                                        <ChevronRight className="mt-1 size-4 shrink-0 text-neutral-400 transition-transform group-hover:translate-x-1" />
                                    </div>
                                </Link>
                            </Reveal>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function StoreShowcase({ stores }: { stores: Showcase[] }) {
    if (!stores.length) return null;
    return <SectionShell><div className="mx-auto max-w-3xl text-center"><Reveal><p className="section-kicker">TOKO YANG SUDAH LIVE</p><h2 className="section-title">Bukan sekadar mockup. Ini yang bisa kamu publikasikan.</h2></Reveal></div><div className="mt-12 grid gap-4 md:grid-cols-3">{stores.map((store, index) => <Reveal key={store.username} delay={index * 80}><a href={`/${store.username}`} className="group block overflow-hidden rounded-[1.5rem] border border-line bg-surface shadow-soft transition duration-500 hover:-translate-y-1 hover:shadow-lift"><div className="h-40 bg-gradient-to-br from-violet-500 to-rose-400" style={store.cover_url ? { backgroundImage: `url(${store.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : store.primary_color ? { background: `linear-gradient(135deg, ${store.primary_color}, #ff7868)` } : undefined} /><div className="p-5"><div className="-mt-11 mb-4 size-16 overflow-hidden rounded-2xl border-4 border-white bg-white shadow-md dark:border-surface">{store.avatar_url ? <img src={store.avatar_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center text-lg font-black text-violet-600">{store.name[0]}</span>}</div><div className="flex items-start justify-between gap-3"><div><h3 className="font-extrabold">{store.name}</h3><p className="mt-1 text-xs text-muted">@{store.username} · {store.products_count} produk</p></div><ArrowRight className="size-4 text-muted transition-transform group-hover:translate-x-1" /></div><p className="mt-3 line-clamp-2 text-sm leading-6 text-muted">{store.tagline ?? store.bio ?? 'Temukan produk pilihan dari kreator ini.'}</p></div></a></Reveal>)}</div></SectionShell>;
}

function Testimonials() {
    const stories = [
        { name: 'Nadia', role: 'Content creator', text: 'Dulu order masuk lewat DM dan sering tercecer. Sekarang pembeli tinggal pilih, bayar, lalu file terkirim sendiri.', metric: 'Waktu admin lebih hemat' },
        { name: 'Raka', role: 'Desainer freelance', text: 'Halaman jualannya kelihatan seperti brand sendiri. Yang paling terasa: klien lebih yakin sebelum booking.', metric: 'Alur booking lebih rapi' },
        { name: 'Vina', role: 'Affiliate creator', text: 'Klik dan komisi bisa dicek kapan saja. Jadi tahu konten mana yang benar-benar menghasilkan penjualan.', metric: 'Performa mudah dilacak' },
    ];
    return <section className="bg-[#f5f5f6] py-24 dark:bg-subtle lg:py-32"><div className="mx-auto max-w-6xl px-5 sm:px-6"><Reveal><div className="mx-auto max-w-2xl text-center"><p className="section-kicker">CERITA PENGGUNA DEMO</p><h2 className="section-title">Lebih sedikit urusan admin. Lebih banyak waktu untuk berkarya.</h2><p className="mt-4 text-xs text-muted">Cerita berikut merupakan contoh persona untuk menggambarkan pengalaman penggunaan.</p></div></Reveal><div className="mt-12 grid gap-4 lg:grid-cols-3">{stories.map((story, index) => <Reveal key={story.name} delay={index * 90}><article className="h-full rounded-[1.5rem] border border-neutral-200/80 bg-white p-6 dark:border-line dark:bg-surface"><div className="flex gap-1 text-amber-400">{Array.from({ length: 5 }).map((_, i) => <Star key={i} className="size-3.5 fill-current" />)}</div><Quote className="mt-7 size-6 text-violet-200" /><p className="mt-3 text-sm leading-7">“{story.text}”</p><div className="mt-8 flex items-center justify-between border-t border-line pt-5"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-full bg-violet-100 text-xs font-black text-violet-700">{story.name[0]}</span><div><p className="text-xs font-extrabold">{story.name}</p><p className="text-[10px] text-muted">{story.role}</p></div></div><span className="text-right text-[9px] font-bold text-emerald-600">{story.metric}</span></div></article></Reveal>)}</div></div></section>;
}

function Pricing({ plans }: { plans: Plan[] }) {
    return <SectionShell className="py-16 sm:py-20 lg:py-28"><Reveal><div className="mx-auto max-w-2xl text-center"><p className="section-kicker">HARGA YANG TUMBUH BERSAMAMU</p><h2 className="section-title">Mulai gratis. Upgrade saat bisnismu siap.</h2><p className="section-body mx-auto">Tanpa biaya setup dan tanpa kontrak panjang. Pilih paket sesuai fase jualanmu.</p></div></Reveal><div className={cn('mt-9 grid gap-3 sm:mt-12 sm:gap-4', plans.length >= 4 ? 'md:grid-cols-2 xl:grid-cols-4' : 'md:grid-cols-3')}>{plans.map((plan, index) => { const featured = index === Math.min(2, plans.length - 1); const tones = ['bg-[#f6e8f1]', 'bg-[#e4f4f0]', 'bg-[#e9e0ff]', 'bg-[#fff0dd]']; return <Reveal key={plan.slug} delay={index * 80}><article className={cn('relative flex h-full flex-col rounded-[1.35rem] border p-5 sm:rounded-[1.5rem] sm:p-6', tones[index % tones.length], featured ? 'border-violet-400 shadow-[0_20px_55px_rgba(124,58,237,.14)]' : 'border-transparent')}>{featured && <span className="absolute -top-3 left-5 rounded-full bg-[#171722] px-3 py-1 text-[9px] font-bold uppercase tracking-wide text-white sm:left-6">Paling populer</span>}<p className="text-[13px] font-extrabold text-neutral-900 sm:text-sm">{plan.name}</p><p className="mt-1.5 min-h-0 text-[11px] leading-5 text-neutral-600 sm:mt-2 sm:min-h-10 sm:text-xs">{plan.tagline ?? 'Paket yang pas untuk mulai membangun bisnis.'}</p><p className="mt-5 text-[1.65rem] font-black tracking-tight text-neutral-900 sm:mt-7 sm:text-3xl">{plan.price_monthly === 0 ? 'Gratis' : formatIDR(plan.price_monthly)}{plan.price_monthly > 0 && <span className="text-[9px] font-semibold text-neutral-500 sm:text-[10px]"> /bulan</span>}</p><p className="mt-0.5 text-[9px] text-neutral-500 sm:mt-1 sm:text-[10px]">Biaya transaksi {plan.transaction_fee_percent}%</p><Link href="/register" className={cn('mt-4 inline-flex h-9 items-center justify-center rounded-full text-[11px] font-extrabold transition sm:mt-6 sm:h-10 sm:text-xs', featured ? 'bg-[#171722] text-white hover:bg-black' : 'border border-black/10 bg-white/70 text-neutral-900 hover:bg-white')}>Pilih paket {plan.name}</Link><ul className="mt-5 space-y-2 sm:mt-6 sm:space-y-3">{(plan.highlights ?? []).slice(0, 5).map((highlight) => <li key={highlight} className="flex items-start gap-2 text-[10px] leading-[1.15rem] text-neutral-700 sm:text-[11px] sm:leading-5"><Check className="mt-0.5 size-3 shrink-0 sm:size-3.5" />{highlight}</li>)}</ul></article></Reveal>; })}</div><div className="mt-6 text-center sm:mt-8"><Link href="/pricing" className="inline-flex items-center gap-1.5 text-sm font-extrabold text-violet-600">Bandingkan semua fitur <ArrowRight className="size-4" /></Link></div></SectionShell>;
}

function Faq() {
    const items = [
        ['Apakah benar-benar bisa mulai gratis?', 'Bisa. Paket gratis tidak membutuhkan kartu kredit dan cukup untuk membuat toko, menambahkan produk, lalu mulai menerima pesanan.'],
        ['Produk apa saja yang bisa dijual?', 'Produk digital, kelas, jasa dan konsultasi, event, produk fisik, membership, donasi, serta produk affiliate.'],
        ['Bagaimana keamanan file digital?', 'File tidak diekspos langsung. Pembeli menerima link bertanda tangan dengan masa berlaku dan batas unduhan yang bisa kamu atur.'],
        ['Kapan saldo bisa dicairkan?', 'Setelah melewati masa tahan dan refund, saldo otomatis menjadi tersedia lalu dapat ditarik ke rekening yang sudah diverifikasi.'],
        ['Apakah bisa menggunakan domain sendiri?', 'Bisa pada paket yang mendukung custom domain. Toko tetap dapat dipakai dengan alamat JualanYok sebelum domain disambungkan.'],
        ['Apakah saya harus paham coding?', 'Tidak. Storefront dibangun dengan block visual. Produk, pembayaran, pengiriman, dan analitik dikelola dari dashboard.'],
    ];
    return <SectionShell className="pt-6 lg:pt-12"><div className="grid gap-12 lg:grid-cols-[.72fr_1.28fr] lg:gap-24"><Reveal><div><p className="section-kicker">PERTANYAAN UMUM</p><h2 className="section-title">Yang biasanya ingin kamu tahu sebelum mulai.</h2><p className="section-body">Masih ada yang belum jelas? Tim kami siap bantu membahas kebutuhan tokomu.</p><Link href="/contact" className="mt-6 inline-flex items-center gap-1.5 text-sm font-extrabold text-violet-600">Hubungi kami <ArrowRight className="size-4" /></Link></div></Reveal><Reveal delay={100}><div className="divide-y divide-line border-y border-line">{items.map(([question, answer], index) => <details key={question} className="group py-5" open={index === 0}><summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-extrabold marker:hidden sm:text-base">{question}<span className="grid size-7 shrink-0 place-items-center rounded-full bg-surface-2 text-lg font-light transition-transform group-open:rotate-45">+</span></summary><p className="max-w-2xl pb-1 pr-10 pt-3 text-sm leading-7 text-muted">{answer}</p></details>)}</div></Reveal></div></SectionShell>;
}

function FinalCta() {
    return <section className="px-3 pb-4 pt-8 sm:px-5 lg:pt-16"><Reveal><div className="relative mx-auto min-h-[430px] max-w-[1500px] overflow-hidden rounded-[2rem] bg-[#6c2ee8] px-6 py-16 text-white sm:px-12 lg:px-20"><div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,.2),transparent_30%),radial-gradient(circle_at_80%_80%,rgba(255,112,112,.28),transparent_34%)]" /><div className="relative z-10 grid items-center gap-12 lg:grid-cols-[.85fr_1.15fr]"><div><Badge className="border border-white/20 bg-white/10 text-white">Siap untuk mulai?</Badge><h2 className="mt-5 max-w-lg text-balance text-4xl font-extrabold leading-[1.08] tracking-[-.04em] sm:text-5xl">Bikin tokomu hidup hari ini.</h2><p className="mt-5 max-w-md text-sm leading-7 text-white/75 sm:text-base">Mulai dari template, isi produk pertamamu, lalu bagikan satu link yang siap bekerja 24 jam.</p><ButtonLink href="/register" size="lg" className="mt-8 rounded-full bg-white px-7 text-violet-700 shadow-xl hover:bg-white/90">Mulai jualan gratis <ArrowRight /></ButtonLink></div><div className="relative h-64 lg:h-72"><div className="absolute left-4 right-0 top-0 rounded-2xl border border-white/20 bg-[#191622]/95 p-4 shadow-2xl sm:left-16"><div className="flex items-center justify-between"><div><p className="text-[9px] text-white/45">Dashboard JualanYok</p><p className="mt-1 text-sm font-extrabold">Semua terkendali.</p></div><img src="/images/jualanyok-mark.png" alt="" className="size-8" /></div><div className="mt-5 grid grid-cols-3 gap-2">{[['Saldo', 'Rp8,2 jt'], ['Order', '148'], ['Konversi', '8,4%']].map(([label, value]) => <div key={label} className="rounded-xl bg-white/7 p-3"><p className="text-[8px] text-white/40">{label}</p><p className="mt-1 text-xs font-bold">{value}</p></div>)}</div><div className="mt-3 flex h-20 items-end gap-2 rounded-xl bg-white/5 p-3">{[25, 45, 35, 62, 52, 78, 68, 95].map((height, i) => <span key={i} className="flex-1 rounded-t bg-gradient-to-t from-violet-400 to-rose-300" style={{ height: `${height}%` }} />)}</div></div></div></div></div></Reveal></section>;
}

function SectionShell({ children, className }: { children: ReactNode; className?: string }) { return <section className={cn('mx-auto max-w-6xl px-5 py-24 sm:px-6 lg:py-32', className)}>{children}</section>; }
function SectionCopy({ kicker, title, body, points }: { kicker: string; title: string; body: string; points: string[] }) { return <div><p className="section-kicker">{kicker}</p><h2 className="section-title">{title}</h2><p className="section-body">{body}</p><ul className="mt-7 space-y-3">{points.map((point) => <li key={point} className="flex items-start gap-3 text-sm font-semibold"><span className="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-700"><Check className="size-3" /></span>{point}</li>)}</ul><Link href="/features" className="mt-8 inline-flex items-center gap-1.5 text-sm font-extrabold text-violet-600">Pelajari selengkapnya <ArrowRight className="size-4" /></Link></div>; }

function Reveal({ children, delay = 0, className }: { children: ReactNode; delay?: number; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);
    useEffect(() => {
        const node = ref.current;
        if (!node) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { setVisible(true); return; }
        const observer = new IntersectionObserver(([entry]) => { if (entry.isIntersecting) { setVisible(true); observer.disconnect(); } }, { threshold: 0.12, rootMargin: '0px 0px -40px' });
        observer.observe(node);
        return () => observer.disconnect();
    }, []);
    return <div ref={ref} className={cn('jy-reveal', visible && 'is-visible', className)} style={{ transitionDelay: `${delay}ms` }}>{children}</div>;
}
