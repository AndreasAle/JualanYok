import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock, Copy, PartyPopper, RefreshCw, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Alert, Badge, Button, ButtonLink, Card, statusTone } from '@/components/ui';
import { Logo } from '@/layouts/MarketingLayout';
import { formatIDR } from '@/lib/utils';

interface Payment {
    id: number;
    provider: string;
    method: string;
    channel: string | null;
    status: string;
    status_label: string;
    amount: number;
    instructions: Record<string, any>;
    redirect_url: string | null;
    expires_at: string | null;
    is_open: boolean;
    error: string | null;
}

export default function CheckoutStatus({
    order,
    payment,
    demo,
    memberUrl,
}: {
    order: any;
    payment: Payment | null;
    demo: boolean;
    memberUrl: string;
}) {
    const paid = order.payment_status === 'PAID';
    const [copied, setCopied] = useState<string | null>(null);

    /**
     * While a payment is open we poll the server, so a webhook that lands
     * while the buyer is staring at this page flips it to success on its own.
     */
    useEffect(() => {
        if (paid || !payment?.is_open) return;

        const timer = setInterval(() => {
            router.reload({ only: ['order', 'payment'] });
        }, 8000);

        return () => clearInterval(timer);
    }, [paid, payment?.is_open]);

    const copy = async (value: string) => {
        await navigator.clipboard.writeText(value);
        setCopied(value);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <div className="min-h-screen bg-subtle">
            <Head title={`Status ${order.number}`} />

            <header className="border-b border-line bg-app">
                <div className="mx-auto flex h-16 max-w-2xl items-center justify-between px-4 sm:px-6">
                    <Logo />
                    <Badge tone={statusTone(order.status)}>{order.status_label}</Badge>
                </div>
            </header>

            <main className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <div className="mb-6 grid grid-cols-3 gap-2" aria-label="Progres pembayaran"><div><span className="block h-1.5 rounded-full bg-emerald-500" /><p className="mt-1.5 text-[10px] font-bold text-muted">Data pembeli</p></div><div><span className="block h-1.5 rounded-full bg-emerald-500" /><p className="mt-1.5 text-[10px] font-bold text-muted">Pembayaran</p></div><div><span className={paid ? 'block h-1.5 rounded-full bg-emerald-500' : 'block h-1.5 rounded-full bg-violet-600'} /><p className="mt-1.5 text-[10px] font-extrabold text-violet-600">Selesai</p></div></div>
                {paid ? (
                    <Card className="overflow-hidden border-emerald-200 p-0 text-center dark:border-emerald-500/20">
                        <div className="bg-emerald-500 px-6 py-3 text-xs font-black uppercase tracking-[.18em] text-white">Pembayaran terkonfirmasi</div>
                        <div className="p-7 sm:p-9"><span className="mx-auto grid size-20 place-items-center rounded-full bg-emerald-100 text-emerald-600 ring-8 ring-emerald-50 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-500/5">
                            <PartyPopper className="size-8" />
                        </span>
                        <h1 className="mt-4 text-2xl font-extrabold tracking-tight">Pembayaran berhasil!</h1>
                        <p className="mt-2 text-sm text-muted">
                            Makasih ya. Struk dan link produkmu sudah dikirim ke{' '}
                            <span className="font-semibold">{order.customer_email}</span>.
                        </p>
                        <div className="mx-auto mt-5 max-w-sm rounded-xl bg-surface-2 px-4 py-3"><p className="text-[10px] font-bold uppercase tracking-wide text-muted">Nomor pesanan</p><p className="mt-1 font-mono text-sm font-black">{order.number}</p></div>

                        <div className="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                            <ButtonLink href={memberUrl} variant="gradient">
                                Buka Pembelianku
                            </ButtonLink>
                            <ButtonLink href={`/${order.store.username}`} variant="outline">
                                Kembali ke toko
                            </ButtonLink>
                        </div>
                        </div>
                    </Card>
                ) : (
                    <>
                        <div className="mb-5 text-center">
                            <h1 className="text-2xl font-extrabold tracking-tight">
                                {payment?.is_open ? 'Tinggal bayar aja' : 'Pembayaran belum selesai'}
                            </h1>
                            <p className="mt-1 text-sm text-muted">Pesanan {order.number}</p>
                        </div>

                        {payment ? (
                            <Card className="p-6">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-muted">Total tagihan</span>
                                    <span className="text-2xl font-extrabold tabular-nums">
                                        {formatIDR(payment.amount)}
                                    </span>
                                </div>

                                {payment.status === 'FAILED' && (
                                    <div className="mt-5">
                                        <Alert tone="danger" title="Tagihan gagal dibuat">
                                            <span className="text-sm">
                                                {payment.error ??
                                                    'Gateway belum berhasil membuat tagihan. Pilih ulang metode pembayaran untuk mencoba lagi.'}
                                            </span>
                                        </Alert>
                                    </div>
                                )}

                                {payment.expires_at && payment.is_open && (
                                    <p className="mt-2 flex items-center gap-1.5 text-xs text-muted">
                                        <Clock className="size-3.5" />
                                        Bayar sebelum {new Date(payment.expires_at).toLocaleString('id-ID')}
                                    </p>
                                )}

                                {/* Instructions per payment type */}
                                {payment.instructions?.type === 'va' && (
                                    <div className="mt-5 rounded-[var(--radius-field)] bg-surface-2 p-4">
                                        <p className="text-xs font-semibold text-muted">
                                            Virtual Account {payment.instructions.bank}
                                        </p>
                                        <div className="mt-1 flex items-center justify-between gap-3">
                                            <span className="font-mono text-xl font-black tracking-wide">
                                                {payment.instructions.va_number}
                                            </span>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => copy(payment.instructions.va_number)}
                                            >
                                                <Copy className="size-3.5" />
                                                {copied === payment.instructions.va_number ? 'Tersalin' : 'Salin'}
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {payment.instructions?.type === 'qris' && (
                                    <div className="mt-5 rounded-[var(--radius-field)] bg-surface-2 p-6 text-center">
                                        {payment.instructions.qr_svg ? (
                                            <>
                                                <img
                                                    src={payment.instructions.qr_svg}
                                                    alt={`Kode QRIS untuk pembayaran ${formatIDR(payment.amount)}`}
                                                    className="mx-auto w-full max-w-[240px] rounded-xl bg-white p-3"
                                                />

                                                {payment.instructions.merchant && (
                                                    <p className="mt-3 text-sm font-semibold">
                                                        Pembayaran ke{' '}
                                                        <span className="font-extrabold">
                                                            {payment.instructions.merchant}
                                                        </span>
                                                    </p>
                                                )}

                                                <div className="mt-4 rounded-[var(--radius-field)] border border-line bg-surface p-4">
                                                    <p className="text-xs font-semibold text-muted">
                                                        Bayar tepat sejumlah
                                                    </p>
                                                    <p className="mt-0.5 text-2xl font-black tabular-nums text-[var(--primary)]">
                                                        {formatIDR(payment.amount)}
                                                    </p>
                                                    {payment.instructions.unique_suffix != null && (
                                                        <p className="mt-1 text-xs text-muted">
                                                            {formatIDR(payment.instructions.base_amount)} + kode unik{' '}
                                                            <b>{payment.instructions.unique_suffix}</b>
                                                        </p>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => copy(String(Math.round(payment.amount)))}
                                                        className="mt-2 text-xs font-bold text-[var(--primary)] underline"
                                                    >
                                                        {copied === String(Math.round(payment.amount))
                                                            ? 'Nominal tersalin'
                                                            : 'Salin nominal'}
                                                    </button>
                                                </div>

                                                <Alert tone="warning">
                                                    <span className="text-sm">
                                                        Nominalnya sudah terkunci di QR. Tiga digit terakhir itu penanda
                                                        pesananmu — jangan dibulatkan, nanti pembayaranmu tidak terdeteksi.
                                                    </span>
                                                </Alert>

                                            </>
                                        ) : (
                                            <>
                                                <div className="mx-auto grid size-40 place-items-center rounded-xl bg-white p-3">
                                                    {/* Simulation only: not a scannable code. */}
                                                    <QrPattern payload={String(payment.instructions.payload ?? '')} />
                                                </div>
                                                <p className="mt-3 text-xs font-bold text-[var(--warning)]">
                                                    Contoh tampilan (provider simulasi) — kode ini tidak bisa di-scan.
                                                </p>
                                            </>
                                        )}
                                    </div>
                                )}

                                {payment.instructions?.type === 'manual' && (
                                    <div className="mt-5 space-y-2">
                                        {(payment.instructions.accounts ?? []).map((account: any, i: number) => (
                                            <div key={i} className="rounded-[var(--radius-field)] bg-surface-2 p-4">
                                                <p className="text-xs font-semibold text-muted">{account.bank}</p>
                                                <p className="font-mono text-lg font-black">{account.number}</p>
                                                <p className="text-xs text-muted">a.n. {account.holder}</p>
                                            </div>
                                        ))}
                                        {payment.instructions.unique_code && (
                                            <Alert tone="warning">
                                                Transfer tepat sampai 3 digit terakhir: kode unik{' '}
                                                <strong>{payment.instructions.unique_code}</strong>.
                                            </Alert>
                                        )}
                                    </div>
                                )}

                                {payment.redirect_url && (
                                    <a
                                        href={payment.redirect_url}
                                        className="mt-5 block rounded-[var(--radius-field)] gradient-brand px-5 py-3 text-center font-bold text-white"
                                    >
                                        Lanjut ke Halaman Pembayaran
                                    </a>
                                )}

                                {(payment.instructions?.steps ?? []).length > 0 && (
                                    <ol className="mt-5 space-y-2">
                                        {payment.instructions.steps.map((step: string, i: number) => (
                                            <li key={i} className="flex gap-2.5 text-sm text-muted">
                                                <span className="grid size-5 shrink-0 place-items-center rounded-full bg-surface-2 text-[11px] font-bold">
                                                    {i + 1}
                                                </span>
                                                {step}
                                            </li>
                                        ))}
                                    </ol>
                                )}

                                <div className="mt-6 flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => router.reload({ only: ['order', 'payment'] })}
                                    >
                                        <RefreshCw className="size-4" />
                                        Cek Status
                                    </Button>

                                    {order.is_payable && (
                                        <ButtonLink href={`/checkout/${order.number}`} variant="ghost">
                                            Ganti Metode
                                        </ButtonLink>
                                    )}
                                </div>

                                {/* Development-only settlement shortcut */}
                                {demo && payment.provider === 'mock' && payment.is_open && (
                                    <div className="mt-6 rounded-[var(--radius-field)] border border-dashed border-line p-4">
                                        <p className="text-xs font-bold uppercase tracking-wide text-muted">
                                            Mode demo
                                        </p>
                                        <p className="mt-1 text-sm text-muted">
                                            Simulasikan callback dari payment gateway.
                                        </p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Button
                                                variant="success"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(`/pay/simulate/${payment.id}`, { outcome: 'paid' })
                                                }
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Simulasi Bayar Sukses
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(`/pay/simulate/${payment.id}`, { outcome: 'failed' })
                                                }
                                            >
                                                <XCircle className="size-4" />
                                                Simulasi Gagal
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Card>
                        ) : (
                            <Card className="p-8 text-center">
                                <p className="text-sm text-muted">Belum ada tagihan yang dibuat untuk pesanan ini.</p>
                                <ButtonLink href={`/checkout/${order.number}`} variant="gradient" className="mt-4">
                                    Pilih Metode Bayar
                                </ButtonLink>
                            </Card>
                        )}
                    </>
                )}

                <p className="mt-6 text-center text-xs text-muted">
                    Butuh bantuan?{' '}
                    <Link href="/contact" className="font-semibold text-[var(--primary)] hover:underline">
                        Hubungi support
                    </Link>
                </p>
            </main>
        </div>
    );
}

/** Renders a stable pseudo-QR from the mock payload so the demo looks real. */
function QrPattern({ payload }: { payload: string }) {
    const cells = 21;
    const seed = Array.from(payload).reduce((acc, c) => acc + c.charCodeAt(0), 0) || 1;

    return (
        <svg viewBox={`0 0 ${cells} ${cells}`} className="size-full" role="img" aria-label="Kode QRIS">
            <rect width={cells} height={cells} fill="white" />
            {Array.from({ length: cells * cells }).map((_, i) => {
                const x = i % cells;
                const y = Math.floor(i / cells);
                const on = ((x * 31 + y * 17 + seed) % 7) < 3;

                return on ? <rect key={i} x={x} y={y} width="1" height="1" fill="black" /> : null;
            })}
            {[[0, 0], [cells - 7, 0], [0, cells - 7]].map(([x, y], i) => (
                <g key={i}>
                    <rect x={x} y={y} width="7" height="7" fill="black" />
                    <rect x={x + 1} y={y + 1} width="5" height="5" fill="white" />
                    <rect x={x + 2} y={y + 2} width="3" height="3" fill="black" />
                </g>
            ))}
        </svg>
    );
}
