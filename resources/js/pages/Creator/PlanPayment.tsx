import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Copy, Check, ShieldCheck, XCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, PageHeader } from '@/components/shared';
import { Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Textarea } from '@/components/ui';
import { formatIDR } from '@/lib/utils';

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
    qr_svg: string | null;
}

function countdown(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${s.toString().padStart(2, '0')}`;
}

export default function PlanPayment({
    payment,
    merchant,
    windowMinutes,
}: {
    payment: Payment;
    merchant: string | null;
    windowMinutes: number;
}) {
    const [left, setLeft] = useState(payment.seconds_left);
    const [copied, setCopied] = useState(false);
    const { data, setData, post, processing } = useForm({ note: '' });

    const isPending = payment.status === 'PENDING';
    const isAwaiting = payment.status === 'AWAITING_REVIEW';

    useEffect(() => {
        if (!isPending) return;

        const timer = window.setInterval(() => {
            setLeft((value) => {
                if (value <= 1) {
                    window.clearInterval(timer);
                    // The window closed — reload so the server state wins.
                    router.reload({ only: ['payment'] });

                    return 0;
                }

                return value - 1;
            });
        }, 1000);

        return () => window.clearInterval(timer);
    }, [isPending]);

    const copyAmount = async () => {
        await navigator.clipboard.writeText(String(payment.amount));
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const urgent = useMemo(() => left > 0 && left < 300, [left]);

    return (
        <DashboardLayout title="Bayar Langganan" area="creator">
            <PageHeader
                title={`Bayar paket ${payment.plan_name}`}
                description="Scan QR di bawah, lalu bayar dengan nominal yang persis sama."
                breadcrumbs={[{ label: 'Langganan', href: '/dashboard/langganan' }, { label: 'Pembayaran' }]}
            />

            {payment.status === 'PAID' && (
                <div className="mb-4">
                    <Alert tone="success" title="Pembayaran dikonfirmasi">
                        Paket {payment.plan_name} sudah aktif. Makasih ya!
                    </Alert>
                </div>
            )}

            {payment.status === 'REJECTED' && (
                <div className="mb-4">
                    <Alert tone="danger" title="Pembayaran ditolak">
                        {payment.review_note ?? 'Admin tidak menemukan dana masuk untuk nominal ini.'}
                    </Alert>
                </div>
            )}

            {payment.status === 'EXPIRED' && (
                <div className="mb-4">
                    <Alert tone="warning" title="Waktu pembayaran habis">
                        Nominal unikmu sudah dilepas. Buat pembayaran baru dari halaman langganan.
                    </Alert>
                </div>
            )}

            {isAwaiting && (
                <div className="mb-4">
                    <Alert tone="info" title="Sedang dicek admin">
                        Pembayaranmu masuk antrean konfirmasi. Paket aktif otomatis begitu admin memverifikasi
                        dana masuk.
                    </Alert>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[1fr_1.1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Scan QRIS</CardTitle>
                    </CardHeader>
                    <CardBody className="text-center">
                        {payment.qr_svg ? (
                            <>
                                <img
                                    src={payment.qr_svg}
                                    alt={`Kode QRIS untuk pembayaran ${formatIDR(payment.amount)}`}
                                    className="mx-auto w-full max-w-[280px] rounded-[var(--radius-field)] border border-line bg-white p-3"
                                />
                                {merchant && (
                                    <p className="mt-3 text-sm font-semibold">
                                        Pembayaran ke <span className="font-extrabold">{merchant}</span>
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted">
                                    Bisa dibayar dari DANA, GoPay, OVO, ShopeePay, atau m-banking apa pun yang
                                    mendukung QRIS.
                                </p>
                            </>
                        ) : (
                            <p className="py-10 text-sm text-muted">
                                QR tidak lagi berlaku untuk pembayaran ini.
                            </p>
                        )}
                    </CardBody>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nominal yang harus dibayar</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-4">
                            <div className="rounded-[var(--radius-field)] bg-surface-2 p-4 text-center">
                                <p className="text-3xl font-black tabular-nums text-[var(--primary)]">
                                    {formatIDR(payment.amount)}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={copyAmount}
                                    className="mt-1"
                                >
                                    {copied ? <Check className="size-4 text-emerald-500" /> : <Copy className="size-4" />}
                                    {copied ? 'Nominal tersalin' : 'Salin nominal'}
                                </Button>
                            </div>

                            <div className="rounded-[var(--radius-field)] border border-line p-4 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted">Harga paket</span>
                                    <span className="font-semibold tabular-nums">{formatIDR(payment.base_amount)}</span>
                                </div>
                                <div className="mt-2 flex items-center justify-between">
                                    <span className="text-muted">Kode unik</span>
                                    <span className="font-semibold tabular-nums">+{payment.unique_suffix}</span>
                                </div>
                                <p className="mt-3 flex items-start gap-2 text-xs leading-relaxed text-muted">
                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                    Tiga digit terakhir itu penanda pembayaranmu. Bayar <b>persis</b> segini —
                                    kurang atau lebih bikin pembayaranmu tidak terdeteksi.
                                </p>
                            </div>

                            {isPending && (
                                <div
                                    className={`flex items-center justify-center gap-2 rounded-[var(--radius-field)] px-4 py-3 text-sm font-bold ${
                                        urgent ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-surface-2'
                                    }`}
                                >
                                    <Clock className="size-4" />
                                    Sisa waktu {countdown(left)}
                                </div>
                            )}

                            <p className="text-center text-xs text-muted">
                                Nomor referensi <b>{payment.reference}</b>
                            </p>
                        </CardBody>
                    </Card>

                    {isPending && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Sudah bayar?</CardTitle>
                            </CardHeader>
                            <CardBody className="space-y-4">
                                <p className="text-sm text-muted">
                                    Klik tombol di bawah setelah transfer berhasil. Admin akan mencocokkan nominal
                                    dan mengaktifkan paketmu.
                                </p>

                                <Field label="Catatan buat admin" hint="Opsional — misal nama pengirim." htmlFor="note">
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
                                        post(`/dashboard/langganan/bayar/${payment.reference}/konfirmasi`, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <CheckCircle2 className="size-4" />
                                    Saya sudah bayar
                                </Button>

                                <ConfirmButton
                                    title="Batalkan pembayaran ini?"
                                    message="Nominal unikmu akan dilepas dan QR ini tidak berlaku lagi."
                                    confirmLabel="Ya, batalkan"
                                    onConfirm={() =>
                                        router.delete(`/dashboard/langganan/bayar/${payment.reference}`)
                                    }
                                >
                                    <Button type="button" variant="ghost" block>
                                        <XCircle className="size-4" />
                                        Batalkan
                                    </Button>
                                </ConfirmButton>
                            </CardBody>
                        </Card>
                    )}

                    <p className="flex items-center justify-center gap-1.5 text-xs text-muted">
                        <ShieldCheck className="size-3.5" />
                        Kamu punya {windowMinutes} menit untuk menyelesaikan pembayaran.
                    </p>
                </div>
            </div>
        </DashboardLayout>
    );
}
