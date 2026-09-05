import {
    ArrowRight, BarChart3, Blocks, Check, CreditCard, Handshake, Layers3, LockKeyhole,
    Package, ShieldCheck, ShoppingBag, TrendingUp, Users, Wallet, Webhook, Zap,
} from 'lucide-react';
import { PageCta, PageHero, Reveal } from '@/components/marketing-page';
import MarketingLayout from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';

const GROUPS = [
    { title: 'Storefront sesukamu', icon: Blocks, tone: 'bg-[#eee7ff]', summary: 'Bangun brand, bukan sekadar link-in-bio.', items: ['Editor block dengan preview langsung', '20+ block untuk produk, konten, leads, dan CTA', 'Tema, warna, font, tombol, dan kartu fleksibel', 'SEO, Open Graph, dan custom domain'] },
    { title: 'Jual produk apa pun', icon: Package, tone: 'bg-[#fff0df]', summary: 'Satu katalog untuk semua model bisnismu.', items: ['Digital, fisik, kelas, event, jasa, dan membership', 'Varian, stok anti-oversell, kuota, dan booking', 'Pay-what-you-want dan donasi', 'Koleksi serta produk unggulan'] },
    { title: 'Checkout & pembayaran', icon: CreditCard, tone: 'bg-[#e4f4f0]', summary: 'Pendek untuk pembeli, lengkap untuk seller.', items: ['Checkout mobile-first tanpa wajib daftar', 'QRIS, VA, e-wallet, dan transfer bank', 'Kupon dan custom field', 'Status serta retry pembayaran'] },
    { title: 'Fulfillment otomatis', icon: Zap, tone: 'bg-[#fff7d9]', summary: 'Pembayaran masuk, pesanan langsung bergerak.', items: ['Akses digital otomatis dibuat', 'Struk dan link download lewat email', 'Member area untuk order, kelas, dan tiket', 'Login pembeli dengan OTP'] },
    { title: 'Saldo & ledger', icon: Wallet, tone: 'bg-[#e8edff]', summary: 'Setiap rupiah punya jejak yang jelas.', items: ['Ledger append-only dan rekonsiliasi', 'Saldo pending, available, held, withdrawn', 'Withdrawal ke rekening terverifikasi', 'Clawback otomatis saat refund'] },
    { title: 'Affiliate dua arah', icon: Handshake, tone: 'bg-[#f8e7f1]', summary: 'Buka programmu atau promosikan produk kreator lain.', items: ['Atur komisi dan durasi atribusi', 'Marketplace affiliate', 'Campaign dan sub-ID tracking', 'Komisi aman melewati masa refund'] },
    { title: 'Analitik & marketing', icon: BarChart3, tone: 'bg-[#e6f6ef]', summary: 'Lihat yang bekerja, perbaiki yang belum.', items: ['Traffic, funnel, konversi, dan produk terlaris', 'UTM dan sumber pengunjung', 'Leads dengan consent', 'Export data pelanggan'] },
    { title: 'Integrasi', icon: Webhook, tone: 'bg-[#f3e8ff]', summary: 'Hubungkan data ke tool yang sudah kamu pakai.', items: ['Meta Pixel, TikTok Pixel, GA4, dan GTM', 'Webhook bertanda tangan HMAC', 'Log delivery dan retry otomatis', 'Custom domain'] },
    { title: 'Pelanggan & akses', icon: Users, tone: 'bg-[#fff0e7]', summary: 'Kenali pembeli tanpa mengorbankan privasi.', items: ['Riwayat order dan lifetime value', 'Consent, catatan, dan segmentasi', 'Role creator, support, finance, dan admin', 'Member area milik pembeli'] },
    { title: 'Keamanan serius', icon: LockKeyhole, tone: 'bg-[#e7f4f5]', summary: 'Proteksi ada di sistem, bukan hanya tampilan.', items: ['Otorisasi server-side', 'Signed URL dan batas download', 'Enkripsi rekening dan secret', 'Audit log, rate limit, CSRF, security headers'] },
];

export default function Features() {
    return (
        <MarketingLayout title="Fitur" description="Storefront, checkout, fulfillment, affiliate, ledger, dan analitik dalam satu platform creator commerce.">
            <PageHero
                eyebrow="Platform creator commerce lengkap"
                title={<>Semua yang dibutuhkan untuk <span className="gradient-text">jualan serius.</span></>}
                description="Mulai dari halaman pertama yang dilihat pembeli sampai saldo masuk rekening—setiap bagian dirancang supaya bisnismu terasa ringan dijalankan."
            >
                <FeatureHeroVisual />
            </PageHero>

            <section className="mx-auto max-w-6xl px-5 py-20 sm:px-6 lg:py-28">
                <Reveal>
                    <div className="mx-auto max-w-3xl text-center">
                        <p className="section-kicker">SATU SISTEM, BUKAN TUMPUKAN TOOL</p>
                        <h2 className="section-title">Bekerja bersama dari kunjungan pertama sampai pencairan.</h2>
                        <p className="section-body mx-auto">Tidak perlu menyambung form, payment link, file delivery, spreadsheet, dan dashboard yang berbeda-beda.</p>
                    </div>
                </Reveal>

                <div className="mt-14 grid gap-4 md:grid-cols-2">
                    {GROUPS.map((group, index) => (
                        <Reveal key={group.title} delay={(index % 2) * 80}>
                            <article className={cn('group h-full overflow-hidden rounded-[1.65rem] p-6 transition duration-500 hover:-translate-y-1 sm:p-7', group.tone)}>
                                <div className="flex items-start justify-between gap-5">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-white/80 text-neutral-900 shadow-sm"><group.icon className="size-5" /></span>
                                    <span className="text-[10px] font-black tracking-[.18em] text-neutral-400">{String(index + 1).padStart(2, '0')}</span>
                                </div>
                                <h3 className="mt-6 text-xl font-extrabold tracking-tight text-neutral-900">{group.title}</h3>
                                <p className="mt-2 text-sm font-semibold text-neutral-600">{group.summary}</p>
                                <ul className="mt-6 grid gap-3 sm:grid-cols-2">
                                    {group.items.map((item) => <li key={item} className="flex items-start gap-2 text-xs leading-5 text-neutral-600"><span className="mt-0.5 grid size-4 shrink-0 place-items-center rounded-full bg-white/80"><Check className="size-2.5 text-violet-700" /></span>{item}</li>)}
                                </ul>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </section>

            <FeatureFlow />
            <PageCta title="Fitur lengkap, titik mulainya tetap sederhana." description="Daftar gratis, pilih template, masukkan produk pertama, dan toko kamu siap menerima pesanan." secondaryHref="/pricing" secondaryLabel="Lihat harga" />
        </MarketingLayout>
    );
}

function FeatureHeroVisual() {
    return (
        <div className="relative mx-auto min-h-[390px] max-w-xl">
            <div className="absolute inset-x-0 top-5 rounded-[1.6rem] border border-white/80 bg-white/85 p-4 shadow-[0_28px_70px_rgba(66,39,119,.18)] backdrop-blur-xl">
                <div className="flex items-center justify-between"><div><p className="text-[9px] font-bold text-neutral-400">JualanYok workspace</p><p className="mt-1 text-sm font-extrabold text-neutral-900">Semua alur, satu dashboard.</p></div><img src="/images/jualanyok-mark.png" alt="" className="size-9" /></div>
                <div className="mt-5 grid grid-cols-3 gap-2">{[[ShoppingBag, 'Pesanan', '148'], [Wallet, 'Saldo', 'Rp8,2 jt'], [TrendingUp, 'Konversi', '8,4%']].map(([Icon, label, value]) => { const StatIcon = Icon as typeof ShoppingBag; return <div key={label as string} className="rounded-xl bg-[#f6f4f9] p-3"><StatIcon className="size-3.5 text-violet-500" /><p className="mt-4 text-[8px] text-neutral-400">{label as string}</p><p className="mt-1 text-xs font-extrabold text-neutral-900">{value as string}</p></div>; })}</div>
                <div className="mt-3 grid gap-2 sm:grid-cols-[1.35fr_.65fr]"><div className="rounded-xl bg-[#f6f4f9] p-3"><p className="text-[9px] font-bold text-neutral-600">Penjualan</p><div className="mt-4 flex h-20 items-end gap-1.5">{[35, 48, 42, 65, 56, 82, 71, 96].map((h, i) => <span key={i} className="flex-1 rounded-t bg-gradient-to-t from-violet-600 to-fuchsia-300" style={{ height: `${h}%` }} />)}</div></div><div className="rounded-xl bg-[#171722] p-3 text-white"><ShieldCheck className="size-4 text-emerald-400" /><p className="mt-4 text-[10px] font-bold">Pembayaran aman</p><p className="mt-1 text-[8px] leading-4 text-white/45">Webhook verified dan ledger tercatat.</p></div></div>
            </div>
            <div className="jy-float absolute -bottom-1 -left-2 flex items-center gap-2 rounded-full bg-white px-4 py-2 text-[9px] font-extrabold text-neutral-800 shadow-xl"><Zap className="size-3.5 text-amber-500" /> Fulfillment otomatis</div>
            <div className="jy-float-delayed absolute bottom-7 -right-2 flex items-center gap-2 rounded-full bg-white px-4 py-2 text-[9px] font-extrabold text-neutral-800 shadow-xl"><Layers3 className="size-3.5 text-violet-500" /> 20+ block fleksibel</div>
        </div>
    );
}

function FeatureFlow() {
    const flow = [['01', 'Datang', 'Storefront & traffic'], ['02', 'Memilih', 'Produk & checkout'], ['03', 'Membayar', 'Gateway & webhook'], ['04', 'Menerima', 'Fulfillment & member'], ['05', 'Bertumbuh', 'Analytics & affiliate']];
    return <section className="bg-[#171722] py-20 text-white lg:py-24"><div className="mx-auto max-w-6xl px-5 sm:px-6"><Reveal><div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div className="max-w-2xl"><p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-violet-300">ALUR YANG UTUH</p><h2 className="mt-4 text-balance text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">Satu data mengalir ke langkah berikutnya.</h2></div><a href="/register" className="inline-flex items-center gap-1 text-sm font-extrabold text-violet-300">Coba sekarang <ArrowRight className="size-4" /></a></div></Reveal><div className="mt-12 grid gap-px overflow-hidden rounded-[1.5rem] bg-white/10 sm:grid-cols-5">{flow.map(([number, title, detail], index) => <Reveal key={number} delay={index * 70}><div className="relative min-h-40 bg-[#211e2b] p-5"><p className="text-[9px] font-black tracking-[.18em] text-white/25">{number}</p><p className="mt-10 text-sm font-extrabold">{title}</p><p className="mt-1 text-[10px] text-white/40">{detail}</p>{index < flow.length - 1 && <ArrowRight className="absolute right-3 top-1/2 hidden size-3.5 text-white/20 sm:block" />}</div></Reveal>)}</div></div></section>;
}
