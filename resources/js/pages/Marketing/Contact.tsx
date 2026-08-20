import { Link, useForm } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock3, Mail, MessageCircle, ReceiptText, Send, ShieldQuestion, Sparkles } from 'lucide-react';
import type { FormEvent } from 'react';
import { PageHero, Reveal } from '@/components/marketing-page';
import { Button, Field, Input, Textarea } from '@/components/ui';
import MarketingLayout from '@/layouts/MarketingLayout';

export default function Contact() {
    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({ name: '', email: '', subject: '', message: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/contact', { onSuccess: () => reset() });
    };

    return (
        <MarketingLayout title="Bantuan" description="Hubungi tim JualanYok untuk pertanyaan toko, pembayaran, pesanan, dan kerja sama.">
            <PageHero
                eyebrow="Bantuan manusia, bukan jalan buntu"
                title={<>Ceritakan kendalanya. Kami bantu cari <span className="gradient-text">jalan keluarnya.</span></>}
                description="Kirim detail yang kamu punya. Pesanmu langsung menjadi tiket agar konteksnya tidak hilang dan progresnya bisa ditelusuri."
            >
                <SupportHeroVisual />
            </PageHero>

            <section className="mx-auto max-w-6xl px-5 py-20 sm:px-6 lg:py-28">
                <div className="grid gap-12 lg:grid-cols-[.78fr_1.22fr] lg:gap-20">
                    <Reveal>
                        <div>
                            <p className="section-kicker">SEBELUM KIRIM PESAN</p>
                            <h2 className="section-title">Sedikit konteks bikin bantuan jauh lebih cepat.</h2>
                            <p className="section-body">Sertakan email akun, URL toko, dan nomor pesanan jika masalahnya berkaitan dengan transaksi.</p>

                            <div className="mt-8 space-y-3">
                                <ContactPoint icon={<Mail />} title="Balasan melalui email" detail="Gunakan email yang aktif agar update tiket tidak terlewat." />
                                <ContactPoint icon={<Clock3 />} title="Respons pada hari kerja" detail="Biasanya kami merespons dalam 1×24 jam kerja." />
                                <ContactPoint icon={<ReceiptText />} title="Masalah pembayaran" detail="Nomor pesanan JY-xxxx membantu kami melacak lebih cepat." />
                            </div>

                            <div className="mt-8 rounded-[1.35rem] bg-[#f5f5f6] p-5 dark:bg-subtle">
                                <p className="text-xs font-extrabold">Mencari jawaban cepat?</p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {[['Lihat fitur', '/features'], ['Bandingkan harga', '/pricing'], ['Kebijakan refund', '/refund-policy']].map(([label, href]) => <Link key={href} href={href} className="inline-flex items-center gap-1 rounded-full border border-line bg-surface px-3 py-2 text-[10px] font-bold text-muted transition hover:text-violet-600">{label}<ArrowRight className="size-3" /></Link>)}
                                </div>
                            </div>
                        </div>
                    </Reveal>

                    <Reveal delay={110}>
                        <div className="relative overflow-hidden rounded-[1.8rem] border border-line bg-surface p-6 shadow-[0_24px_70px_rgba(38,28,56,.1)] sm:p-8">
                            <div className="absolute right-0 top-0 size-40 translate-x-1/3 -translate-y-1/3 rounded-full bg-violet-200/35 blur-3xl" />
                            <div className="relative z-10">
                                <div className="flex items-start justify-between gap-5 border-b border-line pb-6"><div><p className="text-[10px] font-extrabold uppercase tracking-[.16em] text-violet-600">Buat tiket baru</p><h2 className="mt-2 text-2xl font-extrabold tracking-tight">Apa yang bisa kami bantu?</h2><p className="mt-2 text-xs text-muted">Semua kolom bertanda wajib perlu diisi.</p></div><span className="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600"><MessageCircle className="size-5" /></span></div>

                                <form onSubmit={submit} className="mt-6 space-y-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <Field label="Nama" required error={errors.name} htmlFor="name"><Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} invalid={!!errors.name} autoComplete="name" placeholder="Nama lengkap" required /></Field>
                                        <Field label="Email" required error={errors.email} htmlFor="email"><Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} invalid={!!errors.email} autoComplete="email" placeholder="nama@email.com" required /></Field>
                                    </div>
                                    <Field label="Subjek" required error={errors.subject} htmlFor="subject"><Input id="subject" value={data.subject} onChange={(e) => setData('subject', e.target.value)} invalid={!!errors.subject} placeholder="Contoh: pembayaran pesanan belum terkonfirmasi" required /></Field>
                                    <Field label="Pesan" required error={errors.message} hint="Jelaskan langkah yang sudah dicoba dan hasil yang muncul." htmlFor="message"><Textarea id="message" rows={7} value={data.message} onChange={(e) => setData('message', e.target.value)} invalid={!!errors.message} placeholder="Ceritakan detail kendala kamu..." required /></Field>

                                    <Button type="submit" block loading={processing} className="h-12 rounded-full bg-[#171722] text-white hover:bg-black">
                                        <Send className="size-4" /> Kirim pesan
                                    </Button>

                                    {recentlySuccessful && <div className="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-800"><CheckCircle2 className="mt-0.5 size-4 shrink-0" /><div><p className="text-xs font-extrabold">Pesan berhasil dikirim.</p><p className="mt-1 text-[10px]">Cek email kamu untuk nomor tiket dan update berikutnya.</p></div></div>}
                                </form>
                            </div>
                        </div>
                    </Reveal>
                </div>
            </section>

            <ContactTopics />
        </MarketingLayout>
    );
}

function SupportHeroVisual() {
    return (
        <div className="relative mx-auto min-h-[360px] max-w-lg">
            <div className="absolute inset-x-5 top-6 rounded-[1.6rem] border border-white/80 bg-white/88 p-5 shadow-[0_28px_70px_rgba(66,39,119,.18)] backdrop-blur-xl sm:inset-x-10">
                <div className="flex items-center justify-between"><div><p className="text-[9px] font-bold uppercase tracking-wide text-violet-500">Tiket bantuan</p><p className="mt-1 text-sm font-extrabold text-neutral-900">#JY-SUP-2841</p></div><span className="rounded-full bg-amber-100 px-3 py-1 text-[8px] font-extrabold text-amber-700">Dalam antrean</span></div>
                <div className="mt-6 rounded-xl bg-[#f7f6f9] p-4"><div className="flex items-start gap-3"><span className="grid size-8 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-600"><ShieldQuestion className="size-4" /></span><div><p className="text-[10px] font-extrabold text-neutral-800">Tim JualanYok</p><p className="mt-1 text-[9px] leading-5 text-neutral-500">Halo! Pesanmu sudah kami terima. Kami sedang mengecek detail transaksi yang kamu kirim.</p></div></div></div>
                <div className="mt-3 rounded-xl border border-neutral-100 p-4"><div className="h-2 w-1/3 rounded-full bg-neutral-200" /><div className="mt-2 h-2 w-4/5 rounded-full bg-neutral-100" /><div className="mt-2 h-2 w-3/5 rounded-full bg-neutral-100" /></div>
                <div className="mt-3 flex items-center justify-between text-[8px] font-semibold text-neutral-400"><span>Update dikirim ke email</span><span className="text-emerald-600">Tercatat otomatis</span></div>
            </div>
            <div className="jy-float absolute bottom-2 left-0 flex items-center gap-2 rounded-full bg-white px-4 py-2 text-[9px] font-extrabold text-neutral-800 shadow-xl"><Mail className="size-3.5 text-violet-500" /> Balasan tidak tercecer</div>
            <div className="jy-float-delayed absolute bottom-10 right-0 flex items-center gap-2 rounded-full bg-[#171722] px-4 py-2 text-[9px] font-extrabold text-white shadow-xl"><Sparkles className="size-3.5 text-amber-300" /> Konteks tetap utuh</div>
        </div>
    );
}

function ContactPoint({ icon, title, detail }: { icon: React.ReactNode; title: string; detail: string }) {
    return <div className="flex items-start gap-4 rounded-[1.15rem] border border-line bg-surface p-4"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600 [&>svg]:size-4.5">{icon}</span><div><p className="text-sm font-extrabold">{title}</p><p className="mt-1 text-xs leading-5 text-muted">{detail}</p></div></div>;
}

function ContactTopics() {
    const topics = [
        ['Akun & toko', 'Login, onboarding, domain, dan pengaturan storefront.'],
        ['Pesanan & pembayaran', 'Status transaksi, callback, refund, dan checkout.'],
        ['Saldo & pencairan', 'Ledger, rekening, saldo tertahan, dan withdrawal.'],
        ['Kerja sama', 'Partnership, enterprise, atau kebutuhan khusus timmu.'],
    ];
    return <section className="bg-[#f5f5f6] py-20 dark:bg-subtle"><div className="mx-auto max-w-6xl px-5 sm:px-6"><Reveal><div className="mx-auto max-w-2xl text-center"><p className="section-kicker">TOPIK YANG BISA KAMI BANTU</p><h2 className="section-title">Kirim ke satu tempat, kami arahkan ke tim yang tepat.</h2></div></Reveal><div className="mt-10 grid gap-px overflow-hidden rounded-[1.5rem] border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">{topics.map(([title, detail], index) => <Reveal key={title} delay={index * 70}><div className="h-full bg-surface p-6"><span className="text-[9px] font-black tracking-[.18em] text-violet-400">{String(index + 1).padStart(2, '0')}</span><h3 className="mt-7 text-sm font-extrabold">{title}</h3><p className="mt-2 text-xs leading-6 text-muted">{detail}</p></div></Reveal>)}</div></div></section>;
}
