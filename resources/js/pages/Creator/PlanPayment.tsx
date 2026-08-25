import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    ExternalLink,
    QrCode,
    RefreshCw,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, PageHeader } from '@/components/shared';
import {
    Alert,
    Badge,
    Button,
    ButtonLink,
    Card,
    CardBody,
    CardHeader,
    CardTitle,
    Field,
    Textarea,
} from '@/components/ui';
import { formatIDR } from '@/lib/utils';

interface PaymentInstructions {
    type?: string;
    payload?: string | null;
    qr_svg?: string | null;
    payment_name?: string | null;
    payment_no?: string | null;
    va_number?: string | null;
    bank?: string | null;
    note?: string | null;
    steps?: string[];
}

interface Payment {
    reference: string;
    plan_name: string;
    interval: string;
    base_amount: number;
    unique_suffix: number;
    amount: number;
    status: string;
    status_label: string;
    expires_at: string;
    seconds_left: number;
    review_note: string | null;
    provider: string;
    method: string;
    channel: string | null;
    gateway_fee: number;
    instructions: PaymentInstructions;
    redirect_url: string | null;
    gateway_error: string | null;
    qr_svg: string | null;
}

function countdown(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return `${minutes}:${remainder.toString().padStart(2, '0')}`;
}

export default function PlanPayment({
    payment,
    merchant,
    windowMinutes,
    automatic,
}: {
    payment: Payment;
    merchant: string | null;
    windowMinutes: number;
    automatic: boolean;
}) {
    const [left, setLeft] = useState(payment.seconds_left);
    const [copied, setCopied] = useState<string | null>(null);
    const [syncing, setSyncing] = useState(false);
    const { data, setData, post, processing } = useForm({ note: '' });

    const isPending = payment.status === 'PENDING';
    const isAwaiting = payment.status === 'AWAITING_REVIEW';
    const isPaid = payment.status === 'PAID';
    const isFailed = payment.status === 'FAILED' || payment.status === 'REJECTED';
    const isExpired = payment.status === 'EXPIRED';
    const isOpen = isPending || isAwaiting;
    const urgent = useMemo(() => left > 0 && left < 60, [left]);
    const paymentNumber = payment.instructions.va_number ?? payment.instructions.payment_no;

    useEffect(() => {
        setLeft(payment.seconds_left);
    }, [payment.reference, payment.seconds_left]);

    useEffect(() => {
        if (!isPending) return;

        const timer = window.setInterval(() => {
            setLeft((value) => Math.max(0, value - 1));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [isPending]);

    const syncStatus = useCallback(() => {
        if (!automatic || !isPending || syncing) return;

        router.post(
            `/dashboard/langganan/bayar/${payment.reference}/cek-status`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['payment'],
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    }, [automatic, isPending, payment.reference, syncing]);

    useEffect(() => {
        if (!automatic || !isPending) return;

        const timer = window.setInterval(syncStatus, 15000);

        return () => window.clearInterval(timer);
    }, [automatic, isPending, syncStatus]);

    useEffect(() => {
        if (!automatic && isPending && left === 0) {
            router.reload({ only: ['payment'] });
        }
    }, [automatic, isPending, left]);

    const copy = async (value: string) => {
        await navigator.clipboard.writeText(value);
        setCopied(value);
        window.setTimeout(() => setCopied(null), 2000);
    };

    return (
        <DashboardLayout title="Bayar Langganan" area="creator">
            <PageHeader
                title={isPaid ? 'Paket berhasil diaktifkan' : `Bayar paket ${payment.plan_name}`}
                description={
                    automatic
                        ? 'Selesaikan pembayaran melalui iPaymu. Paket aktif otomatis setelah pembayaran diterima.'
                        : 'Scan QR dan bayar dengan nominal yang persis sama.'
                }
                breadcrumbs={[{ label: 'Langganan', href: '/dashboard/langganan' }, { label: 'Pembayaran' }]}
            />

            {isPaid && (
                <Card className="mx-auto max-w-2xl overflow-hidden border-emerald-200 p-0 text-center">
                    <div className="bg-emerald-500 px-6 py-3 text-xs font-black uppercase tracking-[.18em] text-white">
                        Pembayaran terkonfirmasi
                    </div>
                    <CardBody className="px-6 py-9 sm:px-10">
                        <span className="mx-auto grid size-20 place-items-center rounded-full bg-emerald-100 text-emerald-600 ring-8 ring-emerald-50">
                            <CheckCircle2 className="size-9" />
                        </span>
                        <h2 className="mt-5 text-2xl font-black">Paket {payment.plan_name} sudah aktif</h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted">
                            Fitur dan limit baru sudah bisa dipakai. Pembayaran tercatat dengan nomor referensi{' '}
                            <b>{payment.reference}</b>.
                        </p>
                        <ButtonLink href="/dashboard/langganan" variant="gradient" className="mt-6">
                            Lihat langganan
                        </ButtonLink>
                    </CardBody>
                </Card>
            )}

            {!isPaid && (
                <>
                    {isFailed && (
                        <div className="mb-4">
                            <Alert tone="danger" title="Tagihan tidak dapat dibuat">
                                {payment.gateway_error ??
                                    payment.review_note ??
                                    'Penyedia pembayaran menolak tagihan ini.'}
                            </Alert>
                        </div>
                    )}

                    {isExpired && (
                        <div className="mb-4">
                            <Alert tone="warning" title="Tagihan sudah kedaluwarsa">
                                Kembali ke halaman langganan untuk membuat tagihan baru.
                            </Alert>
                        </div>
                    )}

                    {isAwaiting && (
                        <div className="mb-4">
                            <Alert tone="info" title="Pembayaran sedang diperiksa">
                                Admin sedang mencocokkan pembayaranmu. Ini hanya berlaku untuk metode QRIS manual.
                            </Alert>
                        </div>
                    )}

                    <div className="grid gap-4 lg:grid-cols-[1.08fr_.92fr]">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between gap-3">
                                <div>
                                    <CardTitle>{automatic ? 'Instruksi pembayaran' : 'Scan QRIS'}</CardTitle>
                                    <p className="mt-1 text-sm text-muted">
                                        {automatic
                                            ? 'Tagihan aman diproses oleh iPaymu.'
                                            : 'Bayar dari aplikasi bank atau e-wallet.'}
                                    </p>
                                </div>
                                <Badge tone={isPending ? 'warning' : isFailed ? 'danger' : 'neutral'}>
                                    {payment.status_label}
                                </Badge>
                            </CardHeader>
                            <CardBody>
                                {isOpen && payment.qr_svg && (
                                    <div className="rounded-[1.15rem] bg-surface-2 p-5 text-center">
                                        <img
                                            src={payment.qr_svg}
                                            alt={`QRIS pembayaran ${formatIDR(payment.amount)}`}
                                            className="mx-auto w-full max-w-[280px] rounded-xl bg-white p-3 shadow-sm"
                                        />
                                        <p className="mt-4 inline-flex items-center gap-2 text-sm font-bold">
                                            <QrCode className="size-4 text-[var(--primary)]" />
                                            Scan dengan aplikasi bank atau e-wallet
                                        </p>
                                        {merchant && (
                                            <p className="mt-1 text-xs text-muted">Merchant: {merchant}</p>
                                        )}
                                    </div>
                                )}

                                {isOpen && paymentNumber && (
                                    <div className="rounded-[1.15rem] border border-line bg-surface-2 p-5">
                                        <p className="text-xs font-bold uppercase tracking-wide text-muted">
                                            {payment.instructions.bank
                                                ? `Virtual Account ${payment.instructions.bank}`
                                                : 'Nomor pembayaran'}
                                        </p>
                                        <div className="mt-2 flex items-center justify-between gap-3">
                                            <p className="break-all font-mono text-xl font-black">{paymentNumber}</p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => copy(paymentNumber)}
                                            >
                                                {copied === paymentNumber ? <Check /> : <Copy />}
                                                {copied === paymentNumber ? 'Tersalin' : 'Salin'}
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {isOpen && !payment.qr_svg && !paymentNumber && !payment.redirect_url && (
                                    <div className="rounded-[1.15rem] border border-dashed border-line p-8 text-center">
                                        <AlertTriangle className="mx-auto size-7 text-[var(--warning)]" />
                                        <p className="mt-3 text-sm font-bold">Instruksi pembayaran belum tersedia</p>
                                        <p className="mt-1 text-xs text-muted">
                                            Coba cek status atau buat tagihan baru.
                                        </p>
                                    </div>
                                )}

                                {isOpen && payment.redirect_url && (
                                    <a
                                        href={payment.redirect_url}
                                        className="mt-4 inline-flex h-12 w-full items-center justify-center gap-2 rounded-[var(--radius-field)] gradient-brand px-5 font-bold text-white shadow-soft transition hover:brightness-105"
                                    >
                                        Lanjut ke halaman pembayaran iPaymu
                                        <ExternalLink className="size-4" />
                                    </a>
                                )}

                                {(payment.instructions.steps ?? []).length > 0 && isOpen && (
                                    <ol className="mt-5 space-y-2.5">
                                        {(payment.instructions.steps ?? []).map((step, index) => (
                                            <li key={step} className="flex gap-3 text-sm leading-6 text-muted">
                                                <span className="grid size-6 shrink-0 place-items-center rounded-full bg-surface-2 text-xs font-black text-fg">
                                                    {index + 1}
                                                </span>
                                                {step}
                                            </li>
                                        ))}
                                    </ol>
                                )}

                                {(isFailed || isExpired) && (
                                    <ButtonLink href="/dashboard/langganan" variant="gradient" block>
                                        Pilih paket dan coba lagi
                                    </ButtonLink>
                                )}
                            </CardBody>
                        </Card>

                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ringkasan tagihan</CardTitle>
                                </CardHeader>
                                <CardBody className="space-y-4">
                                    <div className="flex items-start justify-between gap-4 text-sm">
                                        <span className="text-muted">Paket</span>
                                        <span className="text-right font-bold">
                                            {payment.plan_name}
                                            <span className="block text-xs font-medium text-muted">
                                                {payment.interval === 'yearly' ? 'Tahunan' : 'Bulanan'}
                                            </span>
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-line pt-4 text-sm">
                                        <span className="text-muted">Harga paket</span>
                                        <span className="font-bold tabular-nums">
                                            {formatIDR(payment.base_amount)}
                                        </span>
                                    </div>
                                    {!automatic && payment.unique_suffix > 0 && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted">Kode unik</span>
                                            <span className="font-bold tabular-nums">
                                                +{payment.unique_suffix}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex items-end justify-between border-t border-line pt-4">
                                        <span className="font-bold">Total bayar</span>
                                        <span className="text-2xl font-black tabular-nums">
                                            {formatIDR(payment.amount)}
                                        </span>
                                    </div>

                                    {automatic && (
                                        <p className="rounded-xl bg-emerald-50 px-3 py-2 text-xs leading-5 text-emerald-700">
                                            Biaya gateway ditanggung JualanYok. Nominalmu tidak bertambah.
                                        </p>
                                    )}

                                    {isPending && (
                                        <div
                                            className={`flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold ${urgent ? 'bg-rose-50 text-rose-700' : 'bg-surface-2'}`}
                                        >
                                            <Clock className="size-4" />
                                            Sisa waktu {countdown(left)}
                                        </div>
                                    )}

                                    <p className="text-center text-xs text-muted">
                                        Referensi <b>{payment.reference}</b>
                                    </p>

                                    {automatic && isPending && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            block
                                            onClick={syncStatus}
                                            disabled={syncing}
                                        >
                                            <RefreshCw className={syncing ? 'animate-spin' : ''} />
                                            {syncing ? 'Mengecek iPaymu…' : 'Cek status pembayaran'}
                                        </Button>
                                    )}

                                    {isOpen && (
                                        <ConfirmButton
                                            title="Batalkan tagihan ini?"
                                            message="Tagihan yang sedang aktif akan ditutup. Kamu bisa memilih paket lagi setelahnya."
                                            confirmLabel="Ya, batalkan"
                                            onConfirm={() =>
                                                router.delete(
                                                    `/dashboard/langganan/bayar/${payment.reference}`,
                                                )
                                            }
                                        >
                                            <Button type="button" variant="ghost" block>
                                                <XCircle />
                                                Batalkan pembayaran
                                            </Button>
                                        </ConfirmButton>
                                    )}
                                </CardBody>
                            </Card>

                            {!automatic && isPending && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Sudah transfer?</CardTitle>
                                    </CardHeader>
                                    <CardBody className="space-y-4">
                                        <Field
                                            label="Catatan buat admin"
                                            hint="Opsional, misalnya nama pengirim."
                                            htmlFor="note"
                                        >
                                            <Textarea
                                                id="note"
                                                rows={2}
                                                value={data.note}
                                                onChange={(event) => setData('note', event.target.value)}
                                                placeholder="Dibayar dari DANA a.n. ..."
                                            />
                                        </Field>
                                        <Button
                                            type="button"
                                            variant="gradient"
                                            block
                                            loading={processing}
                                            onClick={() =>
                                                post(
                                                    `/dashboard/langganan/bayar/${payment.reference}/konfirmasi`,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <CheckCircle2 />
                                            Saya sudah bayar
                                        </Button>
                                    </CardBody>
                                </Card>
                            )}

                            <p className="flex items-center justify-center gap-1.5 text-xs text-muted">
                                <ShieldCheck className="size-3.5" />
                                {automatic
                                    ? 'Status diverifikasi otomatis oleh iPaymu.'
                                    : `Batas pembayaran ${windowMinutes} menit.`}
                            </p>
                        </div>
                    </div>
                </>
            )}
        </DashboardLayout>
    );
}
