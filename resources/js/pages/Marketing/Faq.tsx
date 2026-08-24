import { Link } from '@inertiajs/react';
import { ChevronDown, CircleHelp, CreditCard, LifeBuoy, PackageCheck, ShieldCheck, Store } from 'lucide-react';
import { PageHero, Reveal } from '@/components/marketing-page';
import MarketingLayout from '@/layouts/MarketingLayout';

const GROUPS = [
    {
        title: 'Akun & toko',
        icon: Store,
        items: [
            ['Apa itu JualanYok?', 'JualanYok adalah platform untuk membuat etalase online, menampilkan produk, menerima pesanan, dan memantau transaksi dari satu dashboard.'],
            ['Apakah pembeli wajib membuat akun?', 'Tidak untuk memulai checkout. Pembeli mengisi data yang diperlukan saat membeli, lalu dapat menggunakan email yang sama untuk melihat akses dan riwayat pembelian.'],
            ['Kapan toko bisa diakses publik?', 'Toko dapat diakses publik setelah kreator melengkapi profil, menambahkan minimal satu produk atau block, meninjau pratinjau, lalu menekan Publikasikan.'],
        ],
    },
    {
        title: 'Produk & pemenuhan',
        icon: PackageCheck,
        items: [
            ['Produk apa saja yang dapat dijual?', 'Kreator dapat menjual produk digital, kelas, jasa, produk fisik, dan menampilkan produk affiliate selama tidak melanggar hukum serta kebijakan platform.'],
            ['Bagaimana produk digital diterima?', 'Setelah pembayaran terkonfirmasi, akses atau tautan unduhan dikirim melalui email dan tersedia di Member Area pembeli.'],
            ['Bagaimana status produk fisik atau jasa?', 'Penjual bertanggung jawab memproses pengiriman atau jadwal layanan. Pembeli dapat menggunakan nomor pesanan saat menghubungi dukungan.'],
        ],
    },
    {
        title: 'Pembayaran & refund',
        icon: CreditCard,
        items: [
            ['Bagaimana pembayaran diproses?', 'Pembayaran diproses melalui penyedia payment gateway yang terhubung dengan JualanYok. Metode yang tersedia ditampilkan pada halaman pembayaran.'],
            ['Mengapa status pembayaran belum berubah?', 'Konfirmasi dapat membutuhkan beberapa saat. Jangan membayar dua kali. Simpan bukti pembayaran dan hubungi dukungan dengan menyertakan nomor pesanan jika status belum berubah.'],
            ['Bagaimana cara meminta refund?', 'Buka detail pembelian di Member Area atau hubungi dukungan dengan nomor pesanan, alasan, dan bukti pendukung. Ketentuannya dijelaskan lengkap di halaman Kebijakan Refund.'],
        ],
    },
    {
        title: 'Keamanan & bantuan',
        icon: ShieldCheck,
        items: [
            ['Apakah data pembayaran disimpan JualanYok?', 'Data sensitif instrumen pembayaran diproses oleh payment gateway. JualanYok hanya menyimpan informasi transaksi yang diperlukan untuk pesanan, rekonsiliasi, dan dukungan.'],
            ['Bagaimana melaporkan toko atau produk bermasalah?', 'Kirim laporan melalui halaman Kontak. Sertakan URL toko atau produk, alasan laporan, dan bukti yang relevan agar tim dapat meninjau dengan tepat.'],
            ['Kapan tim dukungan membalas?', 'Pesan dicatat sebagai tiket dan umumnya ditanggapi dalam 1×24 jam kerja. Kasus transaksi dapat membutuhkan pemeriksaan tambahan.'],
        ],
    },
] as const;

export default function Faq() {
    return (
        <MarketingLayout title="FAQ" description="Jawaban atas pertanyaan umum tentang akun, toko, produk, pembayaran, refund, dan keamanan JualanYok.">
            <PageHero
                eyebrow="Pusat jawaban JualanYok"
                title={<>Pertanyaan penting, dijawab <span className="gradient-text">tanpa berputar-putar.</span></>}
                description="Temukan penjelasan singkat tentang cara kerja toko, pembayaran, produk, refund, dan bantuan di JualanYok."
            >
                <div className="mx-auto max-w-md rounded-[1.75rem] border border-white/80 bg-white/85 p-5 shadow-[0_28px_70px_rgba(66,39,119,.16)] backdrop-blur-xl">
                    <div className="flex items-center gap-4"><span className="grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700"><CircleHelp className="size-6" /></span><div><p className="text-sm font-extrabold text-neutral-900">12 jawaban utama</p><p className="mt-1 text-xs text-neutral-500">Dikelompokkan supaya cepat ditemukan.</p></div></div>
                    <div className="mt-5 grid grid-cols-2 gap-2">{GROUPS.map(({ title, icon: Icon }) => <span key={title} className="flex items-center gap-2 rounded-xl bg-neutral-50 px-3 py-2.5 text-[10px] font-bold text-neutral-600"><Icon className="size-3.5 text-violet-500" />{title}</span>)}</div>
                </div>
            </PageHero>

            <section className="mx-auto max-w-6xl px-5 py-20 sm:px-6 lg:py-28">
                <div className="grid gap-8 lg:grid-cols-[250px_1fr] lg:gap-14">
                    <Reveal>
                        <aside className="lg:sticky lg:top-28">
                            <p className="section-kicker">JAWABAN BERDASARKAN TOPIK</p>
                            <h2 className="mt-3 text-2xl font-extrabold tracking-[-.04em]">Mulai dari yang paling dekat dengan kebutuhanmu.</h2>
                            <p className="mt-4 text-sm leading-7 text-muted">Belum menemukan jawaban? Tim kami siap membantu lewat tiket dukungan.</p>
                            <Link href="/contact" className="mt-6 inline-flex items-center gap-2 rounded-full bg-[#171722] px-5 py-3 text-xs font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-black"><LifeBuoy className="size-4" /> Hubungi kami</Link>
                        </aside>
                    </Reveal>

                    <div className="space-y-8">
                        {GROUPS.map(({ title, icon: Icon, items }, groupIndex) => (
                            <Reveal key={title} delay={groupIndex * 70}>
                                <section className="overflow-hidden rounded-[1.6rem] border border-line bg-surface shadow-[0_14px_45px_rgba(38,28,56,.06)]">
                                    <div className="flex items-center gap-3 border-b border-line px-5 py-5 sm:px-7"><span className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-700"><Icon className="size-4.5" /></span><div><p className="text-[9px] font-black uppercase tracking-[.16em] text-violet-500">Topik {String(groupIndex + 1).padStart(2, '0')}</p><h2 className="mt-1 text-lg font-extrabold">{title}</h2></div></div>
                                    <div className="divide-y divide-line">
                                        {items.map(([question, answer]) => (
                                            <details key={question} className="group px-5 py-1 sm:px-7">
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-5 py-5 text-sm font-extrabold marker:hidden"><span>{question}</span><span className="grid size-8 shrink-0 place-items-center rounded-full bg-subtle text-muted transition group-open:rotate-180 group-open:bg-violet-100 group-open:text-violet-700"><ChevronDown className="size-4" /></span></summary>
                                                <p className="max-w-2xl pb-6 pr-10 text-sm leading-7 text-muted">{answer}</p>
                                            </details>
                                        ))}
                                    </div>
                                </section>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </section>
        </MarketingLayout>
    );
}
