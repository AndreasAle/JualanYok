import { router, useForm } from '@inertiajs/react';
import { Banknote, Plus, Trash2, Wallet } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, DataList, PageHeader, Pagination, StatusBadge, type Column } from '@/components/shared';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, EmptyState, Field, Input, Select,
} from '@/components/ui';
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

const BANKS = ['BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga', 'Permata', 'Danamon'];
const WALLETS = ['GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja'];

export default function Withdrawals({
    wallet,
    config,
    payoutMethods,
    withdrawals,
}: {
    wallet: { available: number; pending: number; held: number; reserve: number; negative: number; is_frozen: boolean };
    config: { minimum: number; fee: number };
    payoutMethods: PayoutMethod[];
    withdrawals: Paginated<WithdrawalRow>;
}) {
    const [addingAccount, setAddingAccount] = useState(payoutMethods.length === 0);

    const verified = payoutMethods.filter((m) => m.status === 'verified');

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
    const canSubmit = amount >= config.minimum && amount <= wallet.available && wallet.negative === 0 && !!withdrawForm.data.payout_method_id;

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

    const columns: Column<WithdrawalRow>[] = [
        {
            key: 'number',
            header: 'Nomor',
            render: (row) => (
                <span>
                    <span className="block font-mono text-sm font-semibold">{row.number}</span>
                    <span className="block text-xs text-muted">{formatDate(row.created_at, true)}</span>
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Jumlah',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{formatIDR(row.amount)}</span>
                    <span className="block text-xs text-muted">Diterima {formatIDR(row.net_amount)}</span>
                </span>
            ),
        },
        {
            key: 'account',
            header: 'Rekening',
            mobile: false,
            render: (row) =>
                row.account ? (
                    <span className="text-sm">
                        {row.account.provider} {row.account.masked}
                    </span>
                ) : (
                    <span className="text-muted">—</span>
                ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span>
                    <StatusBadge status={row.status} label={row.status_label} />
                    {row.review_note && <span className="mt-1 block text-xs text-muted">{row.review_note}</span>}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) =>
                row.can_cancel ? (
                    <ConfirmButton
                        title="Batalkan penarikan?"
                        message="Saldo akan dikembalikan ke saldo tersedia kamu."
                        confirmLabel="Ya, batalkan"
                        onConfirm={() => router.post(`/dashboard/penarikan/${row.number}/batal`)}
                    >
                        <Button variant="ghost" size="sm">
                            Batalkan
                        </Button>
                    </ConfirmButton>
                ) : null,
        },
    ];

    return (
        <DashboardLayout title="Penarikan" area="creator">
            <PageHeader
                title="Tarik Saldo"
                description="Cairkan hasil jualanmu ke rekening bank atau e-wallet."
            />

            {wallet.is_frozen && (
                <div className="mb-4">
                    <Alert tone="danger" title="Saldo kamu sedang ditahan">
                        Penarikan dinonaktifkan sementara. Hubungi tim support buat info lebih lanjut.
                    </Alert>
                </div>
            )}

            {wallet.negative > 0 && (
                <div className="mb-4">
                    <Alert tone="danger" title={`Penarikan ditahan — saldo minus ${formatIDR(wallet.negative)}`}>
                        Pendapatan berikutnya otomatis melunasi saldo ini. Kamu bisa menarik dana lagi setelah saldo
                        minus kembali nol.
                    </Alert>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
                {/* Left: form */}
                <div className="space-y-4">
                    <Card className="p-5">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted">Saldo tersedia</p>
                        <p className="mt-1 text-3xl font-semibold tabular-nums text-[var(--success)]">
                            {formatIDR(wallet.available)}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span>Tertahan: {formatIDR(wallet.pending)}</span>
                            <span>Cadangan: {formatIDR(wallet.reserve)}</span>
                            <span>Diproses: {formatIDR(wallet.held)}</span>
                        </div>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ajukan penarikan</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {verified.length === 0 ? (
                                <Alert tone="warning" title="Belum ada rekening terverifikasi">
                                    Tambahkan rekening dulu. Tim kami verifikasi maksimal 1×24 jam kerja sebelum
                                    penarikan bisa diajukan.
                                </Alert>
                            ) : (
                                <form onSubmit={submitWithdrawal} className="space-y-4">
                                    <Field
                                        label="Jumlah penarikan"
                                        required
                                        error={withdrawForm.errors.amount}
                                        hint={`Minimal ${formatIDR(config.minimum)} · biaya admin ${formatIDR(config.fee)}`}
                                        htmlFor="amount"
                                    >
                                        <Input
                                            id="amount"
                                            type="number"
                                            min={config.minimum}
                                            step={1000}
                                            value={withdrawForm.data.amount}
                                            onChange={(e) => withdrawForm.setData('amount', e.target.value)}
                                            invalid={!!withdrawForm.errors.amount}
                                            placeholder={String(config.minimum)}
                                        />
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
                                            onClick={() =>
                                                withdrawForm.setData(
                                                    'amount',
                                                    String(Math.max(0, wallet.available)),
                                                )
                                            }
                                        >
                                            Semua
                                        </Button>
                                    </div>

                                    <Field
                                        label="Rekening tujuan"
                                        required
                                        error={withdrawForm.errors.payout_method_id}
                                        htmlFor="method"
                                    >
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
                                            <div className="flex justify-between">
                                                <span className="text-muted">Jumlah</span>
                                                <span className="tabular-nums">{formatIDR(amount)}</span>
                                            </div>
                                            <div className="mt-1 flex justify-between">
                                                <span className="text-muted">Biaya admin</span>
                                                <span className="tabular-nums">−{formatIDR(config.fee)}</span>
                                            </div>
                                            <div className="mt-2 flex justify-between border-t border-line pt-2 font-bold">
                                                <span>Kamu terima</span>
                                                <span className="tabular-nums">{formatIDR(receives)}</span>
                                            </div>
                                        </div>
                                    )}

                                    <Button
                                        type="submit"
                                        variant="gradient"
                                        block
                                        size="lg"
                                        loading={withdrawForm.processing}
                                        disabled={!canSubmit || wallet.is_frozen || wallet.negative > 0}
                                    >
                                        Ajukan Penarikan
                                    </Button>

                                    <p className="text-center text-xs text-muted">
                                        Diproses tim finance maksimal 2 hari kerja.
                                    </p>
                                </form>
                            )}
                        </CardBody>
                    </Card>

                    {/* Accounts */}
                    <Card>
                        <CardHeader className="flex items-center justify-between">
                            <CardTitle>Rekening pencairan</CardTitle>
                            <Button variant="ghost" size="sm" onClick={() => setAddingAccount((v) => !v)}>
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        </CardHeader>
                        <CardBody className="space-y-3">
                            {payoutMethods.map((method) => (
                                <div
                                    key={method.id}
                                    className="rounded-[var(--radius-field)] border border-line p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2">
                                                <Banknote className="size-5 text-[var(--primary)]" />
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold">
                                                    {method.provider} {method.masked}
                                                </p>
                                                <p className="truncate text-xs text-muted">{method.account_name}</p>
                                            </div>
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
                                                accountForm.setData(
                                                    'provider',
                                                    e.target.value === 'bank' ? BANKS[0] : WALLETS[0],
                                                );
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
                                                <option key={name} value={name}>
                                                    {name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>

                                    <Field
                                        label="Nama pemilik"
                                        required
                                        error={accountForm.errors.account_name}
                                        hint="Harus sama persis dengan nama di rekening."
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

                                    <Field
                                        label="Nomor rekening"
                                        required
                                        error={accountForm.errors.account_number}
                                        htmlFor="acc-number"
                                    >
                                        <Input
                                            id="acc-number"
                                            inputMode="numeric"
                                            value={accountForm.data.account_number}
                                            onChange={(e) =>
                                                accountForm.setData('account_number', e.target.value.replace(/\D/g, ''))
                                            }
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
                        </CardBody>
                    </Card>
                </div>

                {/* Right: history */}
                <div>
                    <h2 className="mb-3 font-bold">Riwayat penarikan</h2>

                    <DataList
                        rows={withdrawals.data}
                        columns={columns}
                        rowKey={(row) => row.number}
                        empty={
                            <EmptyState
                                icon={<Wallet className="size-6" />}
                                title="Belum ada penarikan"
                                description="Penarikan yang kamu ajukan akan muncul di sini."
                            />
                        }
                    />

                    <Pagination meta={withdrawals} />
                </div>
            </div>
        </DashboardLayout>
    );
}
