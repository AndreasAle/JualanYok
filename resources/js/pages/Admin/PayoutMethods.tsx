import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Building2, Copy, Eye, EyeOff, ShieldCheck, UserRoundCheck, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatCard, type Column } from '@/components/shared';
import { Alert, Badge, Button, Card, Field, Select, Textarea } from '@/components/ui';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

interface PayoutMethodRow {
    id: number;
    user: { name: string; username: string; email: string; phone: string | null };
    type: string;
    provider: string;
    account_name: string;
    account_number: string;
    masked: string;
    is_default: boolean;
    status: 'unverified' | 'verified' | 'rejected';
    review_note: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    created_at: string;
}

const STATUS_LABELS = {
    unverified: 'Menunggu',
    verified: 'Terverifikasi',
    rejected: 'Ditolak',
};

const STATUS_TONES = {
    unverified: 'warning',
    verified: 'success',
    rejected: 'danger',
} as const;

export default function PayoutMethods({
    methods,
    filters,
    stats,
}: {
    methods: Paginated<PayoutMethodRow>;
    filters: { status?: string; q?: string };
    stats: { pending: number; verified: number; rejected: number };
}) {
    const [visibleAccounts, setVisibleAccounts] = useState<number[]>([]);
    const [acting, setActing] = useState<{ row: PayoutMethodRow; mode: 'approve' | 'reject' } | null>(null);

    const toggleAccount = (id: number) => {
        setVisibleAccounts((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
    };

    const copyAccount = async (account: string) => {
        await navigator.clipboard.writeText(account);
    };

    const columns: Column<PayoutMethodRow>[] = [
        {
            key: 'owner',
            header: 'Pemilik',
            render: (row) => (
                <span>
                    <span className="block text-sm font-bold">{row.user.name}</span>
                    <span className="block text-xs text-muted">@{row.user.username} · {row.user.email}</span>
                    {row.user.phone && <span className="block text-xs text-muted">{row.user.phone}</span>}
                </span>
            ),
        },
        {
            key: 'account',
            header: 'Rekening tujuan',
            render: (row) => {
                const visible = visibleAccounts.includes(row.id);

                return (
                    <span>
                        <span className="flex flex-wrap items-center gap-1.5 text-sm font-bold">
                            {row.provider}
                            <Badge tone="neutral">{row.type === 'ewallet' ? 'E-wallet' : 'Bank'}</Badge>
                            {row.is_default && <Badge tone="brand">Utama</Badge>}
                        </span>
                        <span className="mt-1 block text-xs text-muted">a.n. {row.account_name}</span>
                        <span className="mt-1 flex items-center gap-1.5 font-mono text-sm font-semibold">
                            {visible ? row.account_number : row.masked}
                            <button
                                type="button"
                                className="rounded-lg p-1 text-muted transition hover:bg-surface-2 hover:text-fg"
                                onClick={() => toggleAccount(row.id)}
                                aria-label={visible ? 'Sembunyikan nomor rekening' : 'Lihat nomor rekening'}
                            >
                                {visible ? <EyeOff className="size-3.5" /> : <Eye className="size-3.5" />}
                            </button>
                            {visible && (
                                <button
                                    type="button"
                                    className="rounded-lg p-1 text-muted transition hover:bg-surface-2 hover:text-fg"
                                    onClick={() => copyAccount(row.account_number)}
                                    aria-label="Salin nomor rekening"
                                >
                                    <Copy className="size-3.5" />
                                </button>
                            )}
                        </span>
                    </span>
                );
            },
        },
        {
            key: 'submitted',
            header: 'Diajukan',
            mobile: false,
            render: (row) => (
                <span className="text-xs text-muted">
                    {formatDate(row.created_at, true)}
                    {row.reviewer && row.reviewed_at && (
                        <span className="mt-1 block">Direview {row.reviewer} · {formatDate(row.reviewed_at, true)}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span>
                    <Badge tone={STATUS_TONES[row.status]}>{STATUS_LABELS[row.status]}</Badge>
                    {row.review_note && <span className="mt-1 block max-w-xs text-xs text-muted">{row.review_note}</span>}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => row.status !== 'verified' ? (
                <span className="flex flex-wrap justify-end gap-1.5">
                    <Button size="sm" variant="success" onClick={() => setActing({ row, mode: 'approve' })}>
                        <BadgeCheck className="size-4" /> Setujui
                    </Button>
                    {row.status === 'unverified' && (
                        <Button size="sm" variant="outline" onClick={() => setActing({ row, mode: 'reject' })}>
                            <XCircle className="size-4" /> Tolak
                        </Button>
                    )}
                </span>
            ) : (
                <span className="text-xs font-semibold text-emerald-600">Siap dipakai</span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Verifikasi Rekening" area="admin">
            <PageHeader
                title="Verifikasi Rekening"
                description="Pastikan nama pemilik dan nomor tujuan benar sebelum rekening dipakai mencairkan saldo."
                actions={stats.pending > 0 ? <Badge tone="warning">{stats.pending} perlu direview</Badge> : undefined}
            />

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard label="Menunggu review" value={stats.pending} hint="prioritas hari ini" icon={<ShieldCheck className="size-4.5" />} tone="warning" />
                <StatCard label="Terverifikasi" value={stats.verified} hint="siap menerima pencairan" icon={<UserRoundCheck className="size-4.5" />} tone="success" />
                <StatCard label="Ditolak" value={stats.rejected} hint="alasan dikirim ke creator" icon={<XCircle className="size-4.5" />} />
            </div>

            <div className="my-4">
                <Alert tone="info" title="Pemeriksaan sensitif">
                    Cocokkan provider, nama pemilik, dan nomor rekening. Nomor lengkap hanya tersedia di halaman ini dan setiap keputusan masuk Audit Log.
                </Alert>
            </div>

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/rekening-pencairan"
                    value={filters.q}
                    placeholder="Cari pemilik, email, bank, atau 4 digit..."
                    extra={filters}
                />
                <Select
                    value={filters.status ?? ''}
                    onChange={(event) => router.get(
                        '/admin/rekening-pencairan',
                        { ...filters, status: event.target.value || undefined },
                        { preserveState: true, replace: true, preserveScroll: true },
                    )}
                    aria-label="Filter status rekening"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    <option value="unverified">Menunggu</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="rejected">Ditolak</option>
                </Select>
            </div>

            <DataList
                rows={methods.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={(
                    <div className="grid min-h-56 place-items-center p-8 text-center">
                        <span>
                            <Building2 className="mx-auto size-8 text-muted" />
                            <strong className="mt-3 block">Tidak ada rekening di antrean ini</strong>
                            <span className="mt-1 block text-sm text-muted">Rekening baru dari creator akan muncul otomatis.</span>
                        </span>
                    </div>
                )}
            />

            <Pagination meta={methods} />

            {acting && <ReviewDialog action={acting} onClose={() => setActing(null)} />}
        </DashboardLayout>
    );
}

function ReviewDialog({
    action,
    onClose,
}: {
    action: { row: PayoutMethodRow; mode: 'approve' | 'reject' };
    onClose: () => void;
}) {
    const { row, mode } = action;
    const form = useForm({ note: '', reason: '' });
    const serverErrors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const url = `/admin/rekening-pencairan/${row.id}/${mode === 'approve' ? 'setujui' : 'tolak'}`;

        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <Card className="w-full max-w-lg animate-rise p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Badge tone={mode === 'approve' ? 'success' : 'danger'}>
                            {mode === 'approve' ? 'Verifikasi rekening' : 'Tolak rekening'}
                        </Badge>
                        <h2 className="mt-3 text-xl font-bold">{row.provider} · {row.account_number}</h2>
                        <p className="mt-1 text-sm text-muted">a.n. {row.account_name} · {row.user.name}</p>
                    </div>
                    <Button type="button" variant="ghost" size="sm" onClick={onClose}>Tutup</Button>
                </div>

                <form onSubmit={submit} className="mt-5 space-y-4">
                    {mode === 'approve' ? (
                        <Field label="Catatan verifikasi" hint="Opsional. Catatan ini terlihat oleh pemilik rekening." htmlFor="verification-note" error={form.errors.note}>
                            <Textarea
                                id="verification-note"
                                rows={3}
                                value={form.data.note}
                                onChange={(event) => form.setData('note', event.target.value)}
                                placeholder="Contoh: Nama dan nomor tujuan sudah cocok."
                            />
                        </Field>
                    ) : (
                        <Field label="Alasan penolakan" required hint="Jelaskan bagian yang perlu diperbaiki oleh creator." htmlFor="rejection-reason" error={form.errors.reason}>
                            <Textarea
                                id="rejection-reason"
                                rows={3}
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                placeholder="Contoh: Nama pemilik tidak sama dengan data akun."
                            />
                        </Field>
                    )}

                    {serverErrors.status && <Alert tone="danger">{serverErrors.status}</Alert>}

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                        <Button type="submit" variant={mode === 'approve' ? 'success' : 'danger'} disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : mode === 'approve' ? 'Ya, verifikasi rekening' : 'Tolak dan kirim alasan'}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
