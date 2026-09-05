import { router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft, ArrowUpRight, BadgeCheck, Banknote, CheckCircle2, ClockAlert, Eye, EyeOff,
    Info, Lock, Plus, ShieldCheck, Trash2, Upload, Wallet, X,
} from 'lucide-react';
import { useMemo, useRef, useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, Pagination } from '@/components/shared';
import { Alert, Badge, Button, Card, Field, Input, Select, Textarea } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface PayoutMethod {
    id: number;
    type: string;
    provider: string;
    account_name: string;
    masked: string;
    status: string;
    is_default: boolean;
    review_note: string | null;
    reviewed_at: string | null;
}

interface WithdrawalRow {
    number: string;
    amount: number;
    fee: number;
    net_amount: number;
    status: string;
    status_label: string;
    can_cancel: boolean;
    account: { provider: string; masked: string } | null;
    review_note: string | null;
    created_at: string;
    paid_at: string | null;
}

interface Identity {
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
    status_label: string;
    full_name: string;
    masked_nik: string;
    rejection_reason: string | null;
    submitted_at: string;
}

const BANKS = ['BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga', 'Permata', 'Danamon'];
const WALLETS = ['GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja'];

const PAID = ['PAID', 'COMPLETED'];

export default function Withdrawals({
    wallet,
    config,
    identity,
    payoutMethods,
    withdrawals,
}: {
    wallet: { available: number; pending: number; held: number; reserve: number; negative: number; is_frozen: boolean };
    config: { minimum: number; fee: number };
    identity: Identity | null;
    payoutMethods: PayoutMethod[];
    withdrawals: Paginated<WithdrawalRow>;
}) {
    const [showBalance, setShowBalance] = useState(true);
    const [addingAccount, setAddingAccount] = useState(false);

    const verified = payoutMethods.filter((m) => m.status === 'verified');
    const identityApproved = identity?.status === 'APPROVED';

    const withdrawForm = useForm({
        amount: '',
        payout_method_id: String(verified[0]?.id ?? ''),
    });

    const accountForm = useForm({
        type: 'bank',
        provider: 'BCA',
        account_name: '',
        account_number: '',
        is_default: payoutMethods.length === 0,
    });

    const amount = Number(withdrawForm.data.amount) || 0;
    const receives = Math.max(0, amount - config.fee);

    const blocked = wallet.is_frozen || wallet.negative > 0 || !identityApproved;
    const canSubmit =
        !blocked && amount >= config.minimum && amount <= wallet.available && !!withdrawForm.data.payout_method_id;

    /* Money that has been paid out, for the little "sudah dicairkan" line. */
    const paidOut = useMemo(
        () => withdrawals.data.filter((row) => PAID.includes(row.status)).reduce((sum, row) => sum + row.net_amount, 0),
        [withdrawals.data],
    );

    const submitWithdrawal = (e: FormEvent) => {
        e.preventDefault();
        withdrawForm.post('/dashboard/penarikan', {
            preserveScroll: true,
            onSuccess: () => withdrawForm.reset('amount'),
        });
    };

    const submitAccount = (e: FormEvent) => {
        e.preventDefault();
        accountForm.post('/dashboard/rekening', {
            preserveScroll: true,
            onSuccess: () => {
                accountForm.reset();
                setAddingAccount(false);
            },
        });
    };

    const money = (value: number) => (showBalance ? formatIDR(value) : 'Rp ••••••');

    return (
        <DashboardLayout title="Saldo & Penarikan" area="creator">
            {/* ── The balance card. The one thing people open this page for. ── */}
            <section className="relative overflow-hidden rounded-[var(--radius-card)] bg-[linear-gradient(135deg,var(--primary),color-mix(in_oklab,var(--primary)_55%,#111))] p-6 text-white shadow-lg sm:p-8">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-white/10 blur-2xl"
                />
                <div className="relative flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-white/70">
                            <Wallet className="size-4" />
                            Saldo tersedia
                        </p>
                        <p className="mt-2 text-4xl font-bold tabular-nums sm:text-5xl">{money(wallet.available)}</p>
                        <p className="mt-2 text-sm text-white/70">
                            {paidOut > 0
                                ? `${money(paidOut)} sudah dicairkan lewat halaman ini.`
                                : 'Dana masuk otomatis begitu pesanan selesai.'}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowBalance((v) => !v)}
                        className="rounded-full bg-white/15 p-2.5 transition hover:bg-white/25"
                        aria-label={showBalance ? 'Sembunyikan saldo' : 'Tampilkan saldo'}
                    >
                        {showBalance ? <EyeOff className="size-4.5" /> : <Eye className="size-4.5" />}
                    </button>
                </div>

                <div className="relative mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-field)] bg-white/15 sm:grid-cols-4">
                    <Bucket label="Menunggu settle" value={money(wallet.pending)} hint="dari pesanan berjalan" />
                    <Bucket label="Sedang diproses" value={money(wallet.held)} hint="penarikan berjalan" />
                    <Bucket label="Cadangan" value={money(wallet.reserve)} hint="jaminan refund" />
                    <Bucket
                        label="Saldo minus"
                        value={money(wallet.negative)}
                        hint={wallet.negative > 0 ? 'dilunasi otomatis' : 'aman'}
                    />
                </div>
            </section>

            {wallet.is_frozen && (
                <div className="mt-4">
                    <Alert tone="danger" title="Saldo kamu sedang ditahan">
                        Penarikan dinonaktifkan sementara. Hubungi tim support buat info lebih lanjut.
                    </Alert>
                </div>
            )}

            {wallet.negative > 0 && (
                <div className="mt-4">
                    <Alert tone="danger" title={`Penarikan ditahan — saldo minus ${formatIDR(wallet.negative)}`}>
                        Pendapatan berikutnya otomatis melunasi saldo ini. Kamu bisa menarik dana lagi setelah saldo
                        minus kembali nol.
                    </Alert>
                </div>
            )}

            <div className="mt-5 grid gap-5 lg:grid-cols-[1.05fr_1fr]">
                <div className="space-y-5">
                    <IdentityCard identity={identity} />

                    {/* ── Withdrawal ── */}
                    <Card className="p-5 sm:p-6">
                        <div className="flex items-center gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-surface-2">
                                <ArrowUpRight className="size-5 text-[var(--primary)]" />
                            </span>
                            <div>
                                <h2 className="font-bold">Tarik dana</h2>
                                <p className="text-xs text-muted">
                                    Minimal {formatIDR(config.minimum)} · biaya admin {formatIDR(config.fee)}
                                </p>
                            </div>
                        </div>

                        {!identityApproved ? (
                            <div className="mt-4 flex items-start gap-3 rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm">
                                <Lock className="mt-0.5 size-4 shrink-0 text-muted" />
                                <p className="text-muted">
                                    Form penarikan terbuka setelah identitasmu terverifikasi. Ini juga diperiksa ulang di
                                    server, bukan cuma disembunyikan di layar.
                                </p>
                            </div>
                        ) : verified.length === 0 ? (
                            <div className="mt-4">
                                <Alert tone="warning" title="Belum ada rekening terverifikasi">
                                    Tambahkan rekening dulu. Tim kami verifikasi maksimal 1×24 jam kerja.
                                </Alert>
                            </div>
                        ) : (
                            <form onSubmit={submitWithdrawal} className="mt-4 space-y-4">
                                <Field label="Jumlah penarikan" required error={withdrawForm.errors.amount} htmlFor="amount">
                                    <div className="relative">
                                        <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted">
                                            Rp
                                        </span>
                                        <Input
                                            id="amount"
                                            type="number"
                                            min={config.minimum}
                                            step={1000}
                                            value={withdrawForm.data.amount}
                                            onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                            invalid={!!withdrawForm.errors.amount}
                                            placeholder="0"
                                            className="!pl-10 !text-lg !font-semibold tabular-nums"
                                        />
                                    </div>
                                </Field>

                                <div className="flex flex-wrap gap-2">
                                    {[config.minimum, 100000, 500000].map((preset) => (
                                        <Button
                                            key={preset}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => withdrawForm.setData('amount', String(preset))}
                                            disabled={preset > wallet.available}
                                        >
                                            {formatIDR(preset)}
                                        </Button>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => withdrawForm.setData('amount', String(Math.max(0, wallet.available)))}
                                    >
                                        Semua
                                    </Button>
                                </div>

                                <Field label="Rekening tujuan" required error={withdrawForm.errors.payout_method_id} htmlFor="method">
                                    <Select
                                        id="method"
                                        value={withdrawForm.data.payout_method_id}
                                        onChange={(e) => withdrawForm.setData('payout_method_id', e.target.value)}
                                    >
                                        {verified.map((method) => (
                                            <option key={method.id} value={method.id}>
                                                {method.provider} {method.masked} — {method.account_name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                {amount > 0 && (
                                    <div className="rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm">
                                        <Line label="Jumlah ditarik" value={formatIDR(amount)} />
                                        <Line label="Biaya admin" value={`−${formatIDR(config.fee)}`} />
                                        <div className="mt-2 flex justify-between border-t border-line pt-2 font-bold">
                                            <span>Masuk ke rekeningmu</span>
                                            <span className="tabular-nums">{formatIDR(receives)}</span>
                                        </div>
                                    </div>
                                )}

                                <Button type="submit" variant="gradient" block size="lg" loading={withdrawForm.processing} disabled={!canSubmit}>
                                    Ajukan Penarikan
                                </Button>
                                <p className="text-center text-xs text-muted">Diproses tim finance maksimal 2 hari kerja.</p>
                            </form>
                        )}
                    </Card>

                    {/* ── Accounts ── */}
                    <Card className="p-5 sm:p-6">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-surface-2">
                                    <Banknote className="size-5 text-[var(--primary)]" />
                                </span>
                                <h2 className="font-bold">Rekening pencairan</h2>
                            </div>
                            <Button variant="ghost" size="sm" onClick={() => setAddingAccount((v) => !v)}>
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        </div>

                        <div className="mt-4 space-y-3">
                            {payoutMethods.length === 0 && !addingAccount && (
                                <p className="text-sm text-muted">Belum ada rekening. Tambahkan satu untuk mencairkan dana.</p>
                            )}

                            {payoutMethods.map((method) => (
                                <div key={method.id} className="rounded-[var(--radius-field)] border border-line p-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">
                                                {method.provider} {method.masked}
                                            </p>
                                            <p className="truncate text-xs text-muted">{method.account_name}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <Badge tone={method.status === 'verified' ? 'success' : method.status === 'rejected' ? 'danger' : 'warning'}>
                                                {method.status === 'verified' ? 'Terverifikasi' : method.status === 'rejected' ? 'Ditolak' : 'Menunggu'}
                                            </Badge>
                                            <ConfirmButton
                                                title="Hapus rekening ini?"
                                                message="Rekening akan dihapus dari daftar pencairan kamu."
                                                confirmLabel="Ya, hapus"
                                                onConfirm={() => router.delete(`/dashboard/rekening/${method.id}`)}
                                            >
                                                <Button variant="ghost" size="icon" aria-label="Hapus rekening">
                                                    <Trash2 className="size-4 text-[var(--danger)]" />
                                                </Button>
                                            </ConfirmButton>
                                        </div>
                                    </div>
                                    {method.review_note && (
                                        <p className="mt-2 rounded-lg bg-surface-2 px-3 py-2 text-xs text-muted">
                                            Catatan tim: {method.review_note}
                                        </p>
                                    )}
                                </div>
                            ))}

                            {addingAccount && (
                                <form onSubmit={submitAccount} className="space-y-3 rounded-[var(--radius-field)] bg-surface-2 p-4">
                                    <Field label="Jenis" htmlFor="acc-type">
                                        <Select
                                            id="acc-type"
                                            value={accountForm.data.type}
                                            onChange={(e) => {
                                                accountForm.setData('type', e.target.value);
                                                accountForm.setData('provider', e.target.value === 'bank' ? BANKS[0] : WALLETS[0]);
                                            }}
                                        >
                                            <option value="bank">Rekening Bank</option>
                                            <option value="ewallet">E-Wallet</option>
                                        </Select>
                                    </Field>

                                    <Field label="Penyedia" error={accountForm.errors.provider} htmlFor="acc-provider">
                                        <Select
                                            id="acc-provider"
                                            value={accountForm.data.provider}
                                            onChange={(e) => accountForm.setData('provider', e.target.value)}
                                        >
                                            {(accountForm.data.type === 'bank' ? BANKS : WALLETS).map((name) => (
                                                <option key={name} value={name}>{name}</option>
                                            ))}
                                        </Select>
                                    </Field>

                                    <Field
                                        label="Nama pemilik"
                                        required
                                        error={accountForm.errors.account_name}
                                        hint="Harus sama persis dengan nama di rekening dan di KTP."
                                        htmlFor="acc-name"
                                    >
                                        <Input
                                            id="acc-name"
                                            value={accountForm.data.account_name}
                                            onChange={(e) => accountForm.setData('account_name', e.target.value)}
                                            invalid={!!accountForm.errors.account_name}
                                            required
                                        />
                                    </Field>

                                    <Field label="Nomor rekening" required error={accountForm.errors.account_number} htmlFor="acc-number">
                                        <Input
                                            id="acc-number"
                                            inputMode="numeric"
                                            value={accountForm.data.account_number}
                                            onChange={(e) => accountForm.setData('account_number', e.target.value.replace(/\D/g, ''))}
                                            invalid={!!accountForm.errors.account_number}
                                            required
                                        />
                                    </Field>

                                    <div className="flex gap-2">
                                        <Button type="submit" variant="gradient" loading={accountForm.processing}>
                                            Simpan Rekening
                                        </Button>
                                        <Button type="button" variant="ghost" onClick={() => setAddingAccount(false)}>
                                            Batal
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </Card>
                </div>

                {/* ── History, read as a statement rather than a table ── */}
                <div>
                    <Card className="p-5 sm:p-6">
                        <h2 className="font-bold">Mutasi penarikan</h2>
                        <p className="text-xs text-muted">Setiap pencairan yang pernah kamu ajukan.</p>

                        <div className="mt-4 divide-y divide-line">
                            {withdrawals.data.length === 0 && (
                                <div className="py-10 text-center">
                                    <Wallet className="mx-auto size-8 text-muted" />
                                    <p className="mt-3 font-semibold">Belum ada penarikan</p>
                                    <p className="mt-1 text-sm text-muted">Riwayat pencairanmu akan muncul di sini.</p>
                                </div>
                            )}

                            {withdrawals.data.map((row) => (
                                <div key={row.number} className="flex items-start gap-3 py-4">
                                    <span
                                        className={`mt-0.5 grid size-10 shrink-0 place-items-center rounded-full ${
                                            PAID.includes(row.status) ? 'bg-[var(--success)]/12' : 'bg-surface-2'
                                        }`}
                                    >
                                        <ArrowDownLeft
                                            className={`size-4.5 ${PAID.includes(row.status) ? 'text-[var(--success)]' : 'text-muted'}`}
                                        />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                                            <p className="font-semibold">
                                                {row.account ? `${row.account.provider} ${row.account.masked}` : 'Penarikan'}
                                            </p>
                                            <p className="font-semibold tabular-nums">−{formatIDR(row.amount)}</p>
                                        </div>
                                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                            <span className="font-mono">{row.number}</span>
                                            <span>·</span>
                                            <span>{formatDate(row.created_at, true)}</span>
                                            <span>·</span>
                                            <span>diterima {formatIDR(row.net_amount)}</span>
                                        </div>
                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                            <Badge tone={PAID.includes(row.status) ? 'success' : row.status === 'REJECTED' ? 'danger' : 'warning'}>
                                                {row.status_label}
                                            </Badge>
                                            {row.can_cancel && (
                                                <ConfirmButton
                                                    title="Batalkan penarikan?"
                                                    message="Saldo akan dikembalikan ke saldo tersedia kamu."
                                                    confirmLabel="Ya, batalkan"
                                                    onConfirm={() => router.post(`/dashboard/penarikan/${row.number}/batal`)}
                                                >
                                                    <Button variant="ghost" size="sm">Batalkan</Button>
                                                </ConfirmButton>
                                            )}
                                        </div>
                                        {row.review_note && (
                                            <p className="mt-2 rounded-lg bg-surface-2 px-3 py-2 text-xs text-muted">
                                                {row.review_note}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <Pagination meta={withdrawals} />
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

function Bucket({ label, value, hint }: { label: string; value: string; hint: string }) {
    return (
        <div className="bg-[color-mix(in_oklab,var(--primary)_82%,#111)] px-4 py-3">
            <p className="text-[11px] uppercase tracking-wide text-white/60">{label}</p>
            <p className="mt-1 text-sm font-semibold tabular-nums">{value}</p>
            <p className="text-[11px] text-white/50">{hint}</p>
        </div>
    );
}

function Line({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between py-0.5">
            <span className="text-muted">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}

/* ────────────────────────────────────────────────────────────────────────────
 * Identity.
 *
 * Nobody enjoys handing over a photo of their ID, so the card says plainly what
 * happens to it before it asks for anything: why it is needed, where it is
 * kept, who can see it, and how long it is held.
 * ──────────────────────────────────────────────────────────────────────────── */
function IdentityCard({ identity }: { identity: Identity | null }) {
    const [open, setOpen] = useState(false);

    if (identity?.status === 'APPROVED') {
        return (
            <Card className="flex items-center gap-3 border-[var(--success)]/30 bg-[var(--success)]/6 p-5">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[var(--success)]/15">
                    <BadgeCheck className="size-5 text-[var(--success)]" />
                </span>
                <div className="min-w-0">
                    <p className="font-bold">Identitas terverifikasi</p>
                    <p className="truncate text-sm text-muted">
                        {identity.full_name} · NIK {identity.masked_nik}
                    </p>
                </div>
            </Card>
        );
    }

    if (identity?.status === 'PENDING') {
        return (
            <Card className="flex items-start gap-3 p-5">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[var(--warning)]/15">
                    <ClockAlert className="size-5 text-[var(--warning)]" />
                </span>
                <div>
                    <p className="font-bold">Verifikasi sedang ditinjau</p>
                    <p className="mt-1 text-sm text-muted">
                        Dikirim {identity.submitted_at}. Biasanya selesai dalam 1×24 jam kerja dan kami kabari lewat
                        notifikasi.
                    </p>
                </div>
            </Card>
        );
    }

    return (
        <>
            <Card className="p-5 sm:p-6">
                <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[var(--primary)]/12">
                        <ShieldCheck className="size-5 text-[var(--primary)]" />
                    </span>
                    <div className="min-w-0">
                        <p className="font-bold">
                            {identity?.status === 'REJECTED' ? 'Verifikasi perlu diperbaiki' : 'Verifikasi identitas dulu'}
                        </p>
                        <p className="mt-1 text-sm text-muted">
                            Penarikan dana mengirim uang ke rekening atas nama seseorang, jadi kami perlu memastikan
                            orang itu memang kamu.
                        </p>
                    </div>
                </div>

                {identity?.status === 'REJECTED' && identity.rejection_reason && (
                    <div className="mt-4">
                        <Alert tone="danger" title="Alasan penolakan">{identity.rejection_reason}</Alert>
                    </div>
                )}

                <DataPromise />

                <Button variant="gradient" block className="mt-4" onClick={() => setOpen(true)}>
                    {identity?.status === 'REJECTED' ? 'Kirim Ulang Data' : 'Mulai Verifikasi'}
                </Button>
            </Card>

            {open && <IdentityForm onClose={() => setOpen(false)} />}
        </>
    );
}

/** The promise, in the same place as the request — not in a policy page. */
function DataPromise() {
    const points = [
        'Nomor KTP disimpan terenkripsi. Di layar kamu hanya 4 angka terakhir yang tampil.',
        'Foto KTP dan selfie disimpan di penyimpanan tertutup, bukan folder publik, dan tidak pernah punya URL yang bisa ditebak.',
        'Hanya tim finance yang bisa membukanya, lewat tautan berumur 20 menit, dan setiap kali dibuka tercatat di audit log.',
        'Dipakai hanya untuk memverifikasi pencairan dana. Tidak dijual, tidak dibagikan ke pihak lain, dan tidak dipakai untuk iklan.',
        'Kamu bisa minta datanya dihapus lewat support setelah akunmu tidak lagi menerima pencairan.',
    ];

    return (
        <ul className="mt-4 space-y-2 rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm">
            {points.map((point) => (
                <li key={point} className="flex gap-2">
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-[var(--success)]" />
                    <span className="text-muted">{point}</span>
                </li>
            ))}
        </ul>
    );
}

function IdentityForm({ onClose }: { onClose: () => void }) {
    const form = useForm<{
        full_name: string;
        nik: string;
        birth_place: string;
        birth_date: string;
        address: string;
        id_card: File | null;
        selfie: File | null;
        consent: boolean;
    }>({
        full_name: '',
        nik: '',
        birth_place: '',
        birth_date: '',
        address: '',
        id_card: null,
        selfie: null,
        consent: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/dashboard/verifikasi-identitas', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <Card className="max-h-[92vh] w-full max-w-xl animate-rise overflow-y-auto p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold">Verifikasi identitas</h2>
                        <p className="mt-1 text-sm text-muted">Isi sesuai KTP. Data yang tidak cocok akan ditolak.</p>
                    </div>
                    <Button type="button" variant="ghost" size="icon" onClick={onClose} aria-label="Tutup">
                        <X className="size-4" />
                    </Button>
                </div>

                <form onSubmit={submit} className="mt-5 space-y-4">
                    <Field label="Nama lengkap sesuai KTP" required error={form.errors.full_name} htmlFor="kyc-name">
                        <Input
                            id="kyc-name"
                            value={form.data.full_name}
                            onChange={(e) => form.setData('full_name', e.target.value)}
                            invalid={!!form.errors.full_name}
                            autoComplete="name"
                            required
                        />
                    </Field>

                    <Field label="NIK" required hint="16 angka di bagian atas KTP." error={form.errors.nik} htmlFor="kyc-nik">
                        <Input
                            id="kyc-nik"
                            inputMode="numeric"
                            maxLength={16}
                            value={form.data.nik}
                            onChange={(e) => form.setData('nik', e.target.value.replace(/\D/g, '').slice(0, 16))}
                            invalid={!!form.errors.nik}
                            className="font-mono tabular-nums"
                            placeholder="3201••••••••••••"
                            required
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Tempat lahir" required error={form.errors.birth_place} htmlFor="kyc-place">
                            <Input
                                id="kyc-place"
                                value={form.data.birth_place}
                                onChange={(e) => form.setData('birth_place', e.target.value)}
                                invalid={!!form.errors.birth_place}
                                required
                            />
                        </Field>
                        <Field label="Tanggal lahir" required error={form.errors.birth_date} htmlFor="kyc-dob">
                            <Input
                                id="kyc-dob"
                                type="date"
                                value={form.data.birth_date}
                                onChange={(e) => form.setData('birth_date', e.target.value)}
                                invalid={!!form.errors.birth_date}
                                required
                            />
                        </Field>
                    </div>

                    <Field label="Alamat sesuai KTP" required error={form.errors.address} htmlFor="kyc-address">
                        <Textarea
                            id="kyc-address"
                            rows={3}
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            placeholder="Jalan, RT/RW, kelurahan, kecamatan, kota, provinsi"
                            required
                        />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <PhotoField
                            id="kyc-idcard"
                            label="Foto KTP"
                            hint="Seluruh kartu terlihat, tidak buram, tanpa pantulan cahaya."
                            error={form.errors.id_card}
                            file={form.data.id_card}
                            onPick={(file) => form.setData('id_card', file)}
                        />
                        <PhotoField
                            id="kyc-selfie"
                            label="Selfie memegang KTP"
                            hint="Wajah dan tulisan di KTP sama-sama terbaca."
                            error={form.errors.selfie}
                            file={form.data.selfie}
                            onPick={(file) => form.setData('selfie', file)}
                        />
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5 size-4 shrink-0 accent-[var(--primary)]"
                            checked={form.data.consent}
                            onChange={(e) => form.setData('consent', e.target.checked)}
                        />
                        <span className="text-muted">
                            Saya menyatakan data dan dokumen di atas benar milik saya, dan saya setuju JualanYok
                            menyimpan serta memprosesnya <strong className="text-fg">khusus untuk verifikasi pencairan
                            dana</strong> — terenkripsi, di penyimpanan tertutup, hanya dapat diakses tim finance dengan
                            jejak audit, dan tidak dibagikan ke pihak ketiga tanpa dasar hukum.
                        </span>
                    </label>
                    {form.errors.consent && <p className="text-sm text-[var(--danger)]">{form.errors.consent}</p>}

                    <div className="flex items-start gap-2 text-xs text-muted">
                        <Info className="mt-0.5 size-3.5 shrink-0" />
                        <span>Waktu dan alamat IP persetujuanmu ikut dicatat sebagai bukti bahwa kamu menyetujuinya.</span>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                        <Button
                            type="submit"
                            variant="gradient"
                            loading={form.processing}
                            disabled={!form.data.consent || !form.data.id_card || !form.data.selfie}
                        >
                            Kirim untuk Ditinjau
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}

function PhotoField({
    id,
    label,
    hint,
    error,
    file,
    onPick,
}: {
    id: string;
    label: string;
    hint: string;
    error?: string;
    file: File | null;
    onPick: (file: File | null) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const preview = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);

    return (
        <div>
            <p className="mb-1.5 text-sm font-semibold">
                {label} <span className="text-[var(--danger)]">*</span>
            </p>

            <button
                type="button"
                onClick={() => input.current?.click()}
                className={`grid h-36 w-full place-items-center overflow-hidden rounded-[var(--radius-field)] border border-dashed p-3 text-center transition hover:bg-surface-2 ${
                    error ? 'border-[var(--danger)]' : 'border-line'
                }`}
            >
                {preview ? (
                    <img src={preview} alt={`Pratinjau ${label}`} className="max-h-full w-full object-contain" />
                ) : (
                    <span>
                        <Upload className="mx-auto size-5 text-muted" />
                        <span className="mt-2 block text-xs text-muted">Pilih foto · JPG/PNG maks 5 MB</span>
                    </span>
                )}
            </button>

            <input
                ref={input}
                id={id}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={(e) => onPick(e.target.files?.[0] ?? null)}
            />

            <p className={`mt-1.5 text-xs ${error ? 'text-[var(--danger)]' : 'text-muted'}`}>{error ?? hint}</p>
        </div>
    );
}
