import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight, BadgeCheck, BookOpen, Check, Copy, Download, FileText, Infinity as InfinityIcon,
    AlertTriangle, Link2, Lock, MapPin, MessageCircle, PackageCheck, ShieldCheck, Sparkles, Store, Truck,
} from 'lucide-react';
import { useState } from 'react';
import { formatIDR } from '@/lib/utils';

interface DownloadItem {
    id: number;
    name: string;
    version: string;
    size: number;
    is_external: boolean;
    remaining: number | null;
    limit: number | null;
    used: number;
    expires_at: string | null;
    available: boolean;
    blocked_reason: string | null;
    url: string;
}

interface OrderItem {
    name: string;
    variant_name: string | null;
    quantity: number;
    total: number;
    thumbnail_url: string | null;
    type_label: string | null;
    post_purchase_message: string | null;
}

function formatBytes(bytes: number): string {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** power;

    return `${value >= 10 || power === 0 ? Math.round(value) : value.toFixed(1)} ${units[power]}`;
}

export default function OrderAccess({
    order,
    store,
    downloads,
    needsAccount,
    canClaim,
    isClaimed,
}: {
    order: {
        number: string;
        tracking_code: string;
        tracking_url: string;
        token: string;
        status: string;
        status_label: string;
        is_paid: boolean;
        customer_name: string;
        customer_email: string;
        grand_total: number;
        paid_at: string | null;
        requires_shipping: boolean;
        fulfillment_label: string;
        tracking_number: string | null;
        can_confirm_receipt: boolean;
        can_open_dispute: boolean;
        complaint_deadline_at: string | null;
        shipment: null | {
            courier: string | null;
            waybill_id: string | null;
            tracking_url: string | null;
            status: string;
            status_label: string;
            events: Array<{ status: string; description: string | null; location: string | null; event_at: string }>;
        };
        open_dispute: null | { number: string; status_label: string };
        items: OrderItem[];
    };
    store: {
        name: string;
        username: string;
        avatar_url: string | null;
        url: string;
        whatsapp: string | null;
    };
    downloads: DownloadItem[];
    needsAccount: boolean;
    canClaim: boolean;
    isClaimed: boolean;
}) {
    const [copied, setCopied] = useState(false);
    const [started, setStarted] = useState<number | null>(null);
    const [disputeOpen, setDisputeOpen] = useState(false);
    const [disputeType, setDisputeType] = useState('not_received');
    const [disputeDescription, setDisputeDescription] = useState('');

    const pageUrl = typeof window !== 'undefined' ? window.location.href : '';
    const ready = downloads.filter((file) => file.available);

    const copyLink = async () => {
        await navigator.clipboard.writeText(pageUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2400);
    };

    // A plain navigation, so the browser's own download manager takes over.
    const start = (file: DownloadItem) => {
        setStarted(file.id);
        window.location.href = file.url;
        setTimeout(() => setStarted(null), 4000);
    };

    return (
        <div className="min-h-screen bg-[#f6f7fb] text-[#12131a] dark:bg-[#0c0d12] dark:text-[#f2f3f7]">
            <Head title={`Pesanan ${order.number}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            {/* Aurora header — the moment of arrival should feel like something. */}
            <header className="relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-br from-violet-600 via-fuchsia-500 to-orange-400" />
                <div className="pointer-events-none absolute -left-24 -top-24 size-96 rounded-full bg-white/25 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 right-0 size-96 rounded-full bg-black/20 blur-3xl" />

                <div className="relative mx-auto max-w-3xl px-5 pb-24 pt-14 text-white sm:px-6 sm:pb-28 sm:pt-20">
                    <Link
                        href={store.url}
                        className="inline-flex items-center gap-2.5 rounded-full bg-white/15 py-1.5 pl-1.5 pr-4 text-sm font-bold backdrop-blur-md transition hover:bg-white/25"
                    >
                        <span className="size-7 shrink-0 overflow-hidden rounded-full bg-white/90">
                            {store.avatar_url ? (
                                <img src={store.avatar_url} alt="" className="size-full object-cover" />
                            ) : (
                                <span className="grid size-full place-items-center text-xs font-black text-violet-600">
                                    {store.name[0]}
                                </span>
                            )}
                        </span>
                        {store.name}
                    </Link>

                    {order.is_paid ? (
                        <>
                            <div className="mt-7 inline-flex items-center gap-2 rounded-full bg-white/20 px-3.5 py-1.5 text-xs font-black uppercase tracking-[.14em] backdrop-blur-md">
                                <BadgeCheck className="size-4" />
                                Pembayaran diterima
                            </div>
                            <h1 className="mt-4 text-3xl font-black leading-[1.1] tracking-[-.035em] sm:text-5xl">
                                Pesananmu siap,
                                <br />
                                {order.customer_name.split(' ')[0]}.
                            </h1>
                            <p className="mt-3 max-w-md text-[15px] leading-relaxed text-white/80">
                                {ready.length > 0
                                    ? 'Semua file di bawah ini milikmu. Halaman ini permanen — simpan emailnya, buka kapan saja.'
                                    : 'Terima kasih! Detail pesananmu ada di bawah.'}
                            </p>
                        </>
                    ) : (
                        <>
                            <div className="mt-7 inline-flex items-center gap-2 rounded-full bg-white/20 px-3.5 py-1.5 text-xs font-black uppercase tracking-[.14em] backdrop-blur-md">
                                {order.status_label}
                            </div>
                            <h1 className="mt-4 text-3xl font-black leading-[1.1] tracking-[-.035em] sm:text-4xl">
                                Menunggu pembayaran
                            </h1>
                            <p className="mt-3 max-w-md text-[15px] leading-relaxed text-white/80">
                                File akan muncul di halaman ini otomatis begitu pembayaranmu dikonfirmasi.
                            </p>
                        </>
                    )}
                </div>
            </header>

            <main className="relative mx-auto -mt-16 max-w-3xl px-5 pb-20 sm:px-6">
                {order.requires_shipping && (
                    <section className="rounded-[1.75rem] border border-black/5 bg-white p-5 shadow-[0_24px_60px_-24px_rgba(24,24,40,.35)] sm:p-7 dark:border-white/10 dark:bg-[#15161e]">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-[11px] font-black uppercase tracking-[.16em] text-violet-600">Perjalanan paket</p>
                                <h2 className="mt-1 text-xl font-black">{order.shipment?.status_label ?? order.fulfillment_label}</h2>
                                <p className="mt-1 text-sm text-black/50 dark:text-white/50">
                                    {order.shipment?.courier || 'Kurir belum dipilih'}
                                    {order.shipment?.waybill_id ? ` · ${order.shipment.waybill_id}` : ''}
                                </p>
                            </div>
                            {order.shipment?.tracking_url && (
                                <a href={order.shipment.tracking_url} target="_blank" rel="noreferrer" className="inline-flex h-10 items-center gap-2 rounded-xl border border-black/10 px-4 text-sm font-bold hover:border-violet-400 hover:text-violet-600 dark:border-white/15">
                                    <Truck className="size-4" /> Lacak resmi
                                </a>
                            )}
                        </div>

                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-violet-50 p-4 dark:bg-violet-950/30"><div><p className="text-[10px] font-black uppercase tracking-wider text-violet-600">ID pembelian</p><code className="mt-1 block text-xs font-black">{order.tracking_code}</code></div><a href={order.tracking_url} className="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-4 text-xs font-black text-white"><Truck className="size-4" /> Buka tracking publik</a></div>

                        {order.shipment?.events?.length ? (
                            <ol className="mt-6 space-y-0">
                                {order.shipment.events.map((event, index) => (
                                    <li key={`${event.event_at}-${index}`} className="relative flex gap-4 pb-5 last:pb-0">
                                        {index < order.shipment!.events.length - 1 && <span className="absolute left-[9px] top-5 h-full w-px bg-black/10 dark:bg-white/10" />}
                                        <span className="relative mt-1 size-[19px] shrink-0 rounded-full border-4 border-violet-100 bg-violet-600 dark:border-violet-950" />
                                        <div>
                                            <p className="text-sm font-bold">{event.description || event.status}</p>
                                            <p className="mt-0.5 flex flex-wrap gap-2 text-xs text-black/45 dark:text-white/45">
                                                {event.location && <span className="inline-flex items-center gap-1"><MapPin className="size-3" />{event.location}</span>}
                                                <span>{new Date(event.event_at).toLocaleString('id-ID')}</span>
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="mt-5 rounded-2xl bg-[#f7f7fb] p-4 text-sm text-black/55 dark:bg-white/[.04] dark:text-white/55">Riwayat perjalanan akan tampil setelah kurir menerima paket.</div>
                        )}

                        {order.open_dispute ? (
                            <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                                <p className="font-black">Komplain {order.open_dispute.number}</p>
                                <p className="mt-1">{order.open_dispute.status_label}. Dana penjual tetap ditahan selama peninjauan.</p>
                            </div>
                        ) : (
                            <div className="mt-5 flex flex-wrap gap-2 border-t border-black/5 pt-5 dark:border-white/10">
                                {order.can_confirm_receipt && (
                                    <button type="button" onClick={() => router.post(`/pesanan/${order.token}/diterima`, {}, { preserveScroll: true })} className="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-black text-white hover:bg-emerald-700">
                                        <PackageCheck className="size-4" /> Pesanan sudah diterima
                                    </button>
                                )}
                                {order.can_open_dispute && (
                                    <button type="button" onClick={() => setDisputeOpen((value) => !value)} className="inline-flex h-11 items-center gap-2 rounded-xl border border-red-200 px-4 text-sm font-bold text-red-600 hover:bg-red-50">
                                        <AlertTriangle className="size-4" /> Ada masalah
                                    </button>
                                )}
                            </div>
                        )}

                        {disputeOpen && !order.open_dispute && (
                            <form className="mt-4 space-y-3 rounded-2xl border border-red-100 bg-red-50/60 p-4" onSubmit={(event) => {
                                event.preventDefault();
                                router.post(`/pesanan/${order.token}/komplain`, { type: disputeType, description: disputeDescription }, { preserveScroll: true, onSuccess: () => setDisputeOpen(false) });
                            }}>
                                <p className="font-black">Ceritakan masalah pesanan</p>
                                <select value={disputeType} onChange={(event) => setDisputeType(event.target.value)} className="h-11 w-full rounded-xl border border-black/10 bg-white px-3 text-sm">
                                    <option value="not_received">Barang belum diterima</option>
                                    <option value="damaged">Barang rusak</option>
                                    <option value="wrong_item">Barang tidak sesuai</option>
                                    <option value="incomplete">Barang kurang</option>
                                    <option value="other">Masalah lainnya</option>
                                </select>
                                <textarea value={disputeDescription} onChange={(event) => setDisputeDescription(event.target.value)} minLength={20} required rows={4} placeholder="Jelaskan kronologi dengan lengkap (minimal 20 karakter)." className="w-full rounded-xl border border-black/10 bg-white p-3 text-sm" />
                                <button type="submit" className="h-11 rounded-xl bg-red-600 px-5 text-sm font-black text-white">Kirim komplain</button>
                            </form>
                        )}
                    </section>
                )}

                {/* Downloads — the reason this page exists, so it leads. */}
                {downloads.length > 0 && (
                    <section className="rounded-[1.75rem] border border-black/5 bg-white p-5 shadow-[0_24px_60px_-24px_rgba(24,24,40,.35)] sm:p-7 dark:border-white/10 dark:bg-[#15161e]">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="flex items-center gap-2 text-lg font-black tracking-tight">
                                <Download className="size-5 text-violet-600" />
                                File kamu
                            </h2>
                            <span className="text-xs font-bold text-black/40 dark:text-white/40">
                                {downloads.length} file
                            </span>
                        </div>

                        <ul className="mt-5 space-y-3">
                            {downloads.map((file) => (
                                <li
                                    key={file.id}
                                    className="group relative overflow-hidden rounded-2xl border border-black/[.07] bg-[#fbfbfe] p-4 transition hover:border-violet-300 dark:border-white/10 dark:bg-white/[.03]"
                                >
                                    <div className="flex items-center gap-4">
                                        <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/25">
                                            {file.is_external ? <Link2 className="size-5" /> : <FileText className="size-5" />}
                                        </span>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-bold leading-snug">{file.name}</p>
                                            <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-black/50 dark:text-white/50">
                                                <span>v{file.version}</span>
                                                {file.size > 0 && (
                                                    <>
                                                        <span aria-hidden>·</span>
                                                        <span>{formatBytes(file.size)}</span>
                                                    </>
                                                )}
                                                <span aria-hidden>·</span>
                                                {file.limit === null ? (
                                                    <span className="inline-flex items-center gap-1 font-semibold text-emerald-600">
                                                        <InfinityIcon className="size-3.5" />
                                                        unduh tanpa batas
                                                    </span>
                                                ) : (
                                                    <span className={file.remaining && file.remaining <= 1 ? 'font-bold text-amber-600' : ''}>
                                                        sisa {file.remaining} dari {file.limit} unduhan
                                                    </span>
                                                )}
                                            </p>
                                        </div>

                                        {file.available ? (
                                            <button
                                                type="button"
                                                onClick={() => start(file)}
                                                className="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-[#12131a] px-5 text-sm font-bold text-white transition hover:bg-violet-600 active:scale-[.97] dark:bg-white dark:text-[#12131a] dark:hover:bg-violet-500 dark:hover:text-white"
                                            >
                                                <Download className="size-4" />
                                                {started === file.id ? 'Menyiapkan…' : 'Unduh'}
                                            </button>
                                        ) : (
                                            <span className="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-black/5 px-4 text-xs font-bold text-black/45 dark:bg-white/10 dark:text-white/45">
                                                <Lock className="size-3.5" />
                                                {file.blocked_reason}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <p className="mt-5 flex items-start gap-2 text-xs leading-relaxed text-black/45 dark:text-white/45">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-500" />
                            File tersimpan di penyimpanan privat. Kalau penjual merilis versi baru, file di sini ikut
                            diperbarui — tanpa perlu beli lagi.
                        </p>
                    </section>
                )}

                {needsAccount && (
                    <section className="mt-4 rounded-[1.5rem] border border-violet-200 bg-violet-50 p-5 sm:p-6 dark:border-violet-900 dark:bg-violet-950/30">
                        <h2 className="flex items-center gap-2 font-black">
                            <BookOpen className="size-5 text-violet-600" />
                            Kelas kamu butuh akun
                        </h2>
                        <p className="mt-1.5 text-sm leading-relaxed text-black/60 dark:text-white/60">
                            Materi kelas menyimpan progres belajarmu, jadi perlu akun. Daftar pakai{' '}
                            <b>{order.customer_email}</b> — kelasnya otomatis menempel di sana.
                        </p>
                        <Link
                            href="/register"
                            className="mt-4 inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700"
                        >
                            Buat akun gratis
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </section>
                )}

                {/* Order summary */}
                <section className="mt-4 rounded-[1.5rem] border border-black/5 bg-white p-5 sm:p-6 dark:border-white/10 dark:bg-[#15161e]">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-black tracking-tight">Rincian pesanan</h2>
                        <span className="font-mono text-xs text-black/40 dark:text-white/40">{order.number}</span>
                    </div>

                    <ul className="mt-4 space-y-3">
                        {order.items.map((item, index) => (
                            <li key={index} className="flex items-center gap-3">
                                <span className="size-11 shrink-0 overflow-hidden rounded-lg bg-black/5 dark:bg-white/10">
                                    {item.thumbnail_url && (
                                        <img src={item.thumbnail_url} alt="" className="size-full object-cover" />
                                    )}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-bold">{item.name}</p>
                                    <p className="text-xs text-black/45 dark:text-white/45">
                                        {item.variant_name ? `${item.variant_name} · ` : ''}
                                        {item.quantity}×
                                    </p>
                                </div>
                                <span className="shrink-0 text-sm font-bold tabular-nums">{formatIDR(item.total)}</span>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-4 flex items-center justify-between border-t border-black/5 pt-4 dark:border-white/10">
                        <span className="text-sm text-black/50 dark:text-white/50">Total dibayar</span>
                        <span className="text-lg font-black tabular-nums">{formatIDR(order.grand_total)}</span>
                    </div>

                    {order.items.some((item) => item.post_purchase_message) && (
                        <div className="mt-4 rounded-xl bg-[#fbfbfe] p-4 dark:bg-white/[.03]">
                            <p className="flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-violet-600">
                                <Sparkles className="size-3.5" />
                                Pesan dari penjual
                            </p>
                            {order.items
                                .filter((item) => item.post_purchase_message)
                                .map((item, index) => (
                                    <p key={index} className="mt-1.5 text-sm leading-relaxed">
                                        {item.post_purchase_message}
                                    </p>
                                ))}
                        </div>
                    )}
                </section>

                {/* Keep this page */}
                <section className="mt-4 rounded-[1.5rem] border border-black/5 bg-white p-5 sm:p-6 dark:border-white/10 dark:bg-[#15161e]">
                    <h2 className="font-black tracking-tight">Simpan halaman ini</h2>
                    <p className="mt-1.5 text-sm leading-relaxed text-black/55 dark:text-white/55">
                        Tautannya permanen dan cuma kamu yang punya. Kami juga sudah mengirimnya ke{' '}
                        <b>{order.customer_email}</b>.
                    </p>

                    <div className="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={copyLink}
                            className="inline-flex h-11 items-center gap-2 rounded-xl border border-black/10 px-4 text-sm font-bold transition hover:border-violet-400 hover:text-violet-600 dark:border-white/15"
                        >
                            {copied ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                            {copied ? 'Tautan tersalin' : 'Salin tautan'}
                        </button>

                        {canClaim && (
                            <button
                                type="button"
                                onClick={() => router.post(`/pesanan/${order.token}/simpan`, {}, { preserveScroll: true })}
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700"
                            >
                                Simpan ke akunku
                            </button>
                        )}

                        {isClaimed && (
                            <Link
                                href="/member/pembelian"
                                className="inline-flex h-11 items-center gap-2 rounded-xl border border-black/10 px-4 text-sm font-bold transition hover:border-violet-400 hover:text-violet-600 dark:border-white/15"
                            >
                                Lihat di akunku
                                <ArrowUpRight className="size-4" />
                            </Link>
                        )}
                    </div>
                </section>

                {/* Help */}
                <section className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[1.5rem] border border-black/5 bg-white p-5 sm:p-6 dark:border-white/10 dark:bg-[#15161e]">
                    <div>
                        <h2 className="font-black tracking-tight">Ada kendala?</h2>
                        <p className="mt-1 text-sm text-black/55 dark:text-white/55">
                            Hubungi {store.name} langsung, mereka yang paling paham produknya.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        {store.whatsapp && (
                            <a
                                href={`https://wa.me/${store.whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(
                                    `Halo, saya mau tanya soal pesanan ${order.number}`,
                                )}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-500 px-4 text-sm font-bold text-white transition hover:bg-emerald-600"
                            >
                                <MessageCircle className="size-4" />
                                WhatsApp
                            </a>
                        )}
                        <Link
                            href={store.url}
                            className="inline-flex h-11 items-center gap-2 rounded-xl border border-black/10 px-4 text-sm font-bold transition hover:border-violet-400 hover:text-violet-600 dark:border-white/15"
                        >
                            <Store className="size-4" />
                            Kunjungi toko
                        </Link>
                    </div>
                </section>

                <p className="mt-8 text-center text-xs text-black/35 dark:text-white/35">
                    Transaksi diproses aman lewat <b className="text-violet-600">JualanYok</b>
                </p>
            </main>
        </div>
    );
}
