import { router, useForm } from '@inertiajs/react';
import { IdCard, ShieldCheck, UserRoundCheck, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatCard, type Column } from '@/components/shared';
import { Alert, Badge, Button, Card, Field, Select, Textarea } from '@/components/ui';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

interface VerificationRow {
    id: number;
    user: { name: string; email: string; phone: string | null };
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
    status_label: string;
    full_name: string;
    nik: string;
    birth_place: string;
    birth_date: string;
    address: string;
    id_card_url: string;
    selfie_url: string;
    consented_at: string;
    consent_ip: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    created_at: string;
}

const TONES = { PENDING: 'warning', APPROVED: 'success', REJECTED: 'danger' } as const;

export default function IdentityVerifications({
    verifications,
    filters,
    stats,
}: {
    verifications: Paginated<VerificationRow>;
    filters: { status?: string; q?: string };
    stats: { pending: number; approved: number; rejected: number };
}) {
    const [inspecting, setInspecting] = useState<VerificationRow | null>(null);

    const columns: Column<VerificationRow>[] = [
        {
            key: 'person',
            header: 'Pemohon',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{row.full_name}</span>
                    <span className="block text-xs text-muted">
                        {row.user.name} · {row.user.email}
                    </span>
                </span>
            ),
        },
        {
            key: 'nik',
            header: 'NIK',
            mobile: false,
            render: (row) => <span className="font-mono text-sm tabular-nums">{row.nik}</span>,
        },
        {
            key: 'submitted',
            header: 'Diajukan',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{formatDate(row.created_at, true)}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <span>
                    <Badge tone={TONES[row.status]}>{row.status_label}</Badge>
                    {row.rejection_reason && (
                        <span className="mt-1 block text-xs text-muted">{row.rejection_reason}</span>
                    )}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => (
                <Button variant="outline" size="sm" onClick={() => setInspecting(row)}>
                    Periksa
                </Button>
            ),
        },
    ];

    return (
        <DashboardLayout title="Verifikasi Identitas" area="admin">
            <PageHeader
                title="Verifikasi Identitas"
                description="Cocokkan data yang diketik dengan foto KTP dan selfie sebelum creator bisa menarik dana."
            />

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard label="Menunggu review" value={stats.pending} hint="prioritas hari ini" icon={<ShieldCheck className="size-4.5" />} tone="warning" />
                <StatCard label="Terverifikasi" value={stats.approved} hint="boleh menarik dana" icon={<UserRoundCheck className="size-4.5" />} tone="success" />
                <StatCard label="Ditolak" value={stats.rejected} hint="alasan dikirim ke creator" icon={<XCircle className="size-4.5" />} />
            </div>

            <div className="my-4">
                <Alert tone="info" title="Data pribadi, perlakukan seperlunya">
                    Foto KTP dan selfie hanya dibuka lewat tautan bertanda tangan yang kedaluwarsa dalam 20 menit.
                    Membuka dokumen, menyetujui, dan menolak semuanya tercatat di Audit Log. Jangan menyalin, mengunduh,
                    atau membagikan dokumen ini ke luar dashboard.
                </Alert>
            </div>

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/verifikasi-identitas"
                    value={filters.q}
                    placeholder="Cari nama, email, atau 4 digit NIK..."
                    extra={filters}
                />
                <Select
                    value={filters.status ?? ''}
                    onChange={(event) => router.get(
                        '/admin/verifikasi-identitas',
                        { ...filters, status: event.target.value || undefined },
                        { preserveState: true, replace: true, preserveScroll: true },
                    )}
                    aria-label="Filter status verifikasi"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    <option value="PENDING">Menunggu</option>
                    <option value="APPROVED">Terverifikasi</option>
                    <option value="REJECTED">Ditolak</option>
                </Select>
            </div>

            <DataList
                rows={verifications.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={(
                    <div className="grid min-h-56 place-items-center p-8 text-center">
                        <span>
                            <IdCard className="mx-auto size-8 text-muted" />
                            <strong className="mt-3 block">Tidak ada pengajuan di antrean ini</strong>
                            <span className="mt-1 block text-sm text-muted">Pengajuan baru muncul otomatis di sini.</span>
                        </span>
                    </div>
                )}
            />

            <Pagination meta={verifications} />

            {inspecting && <ReviewDialog row={inspecting} onClose={() => setInspecting(null)} />}
        </DashboardLayout>
    );
}

function ReviewDialog({ row, onClose }: { row: VerificationRow; onClose: () => void }) {
    const [mode, setMode] = useState<'view' | 'reject'>('view');
    const form = useForm({ reason: '' });

    const approve = () => {
        router.post(`/admin/verifikasi-identitas/${row.id}/setujui`, {}, { preserveScroll: true, onSuccess: onClose });
    };

    const reject = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/admin/verifikasi-identitas/${row.id}/tolak`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <Card className="max-h-[92vh] w-full max-w-3xl animate-rise overflow-y-auto p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Badge tone={TONES[row.status]}>{row.status_label}</Badge>
                        <h2 className="mt-3 text-xl font-bold">{row.full_name}</h2>
                        <p className="mt-1 text-sm text-muted">
                            Akun {row.user.name} · {row.user.email}
                            {row.user.phone ? ` · ${row.user.phone}` : ''}
                        </p>
                    </div>
                    <Button type="button" variant="ghost" size="sm" onClick={onClose}>Tutup</Button>
                </div>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                    <figure className="space-y-2">
                        <figcaption className="text-xs font-semibold uppercase tracking-wide text-muted">Foto KTP</figcaption>
                        <img src={row.id_card_url} alt="Foto KTP pemohon" className="w-full rounded-[var(--radius-field)] border border-line object-contain" />
                    </figure>
                    <figure className="space-y-2">
                        <figcaption className="text-xs font-semibold uppercase tracking-wide text-muted">Selfie dengan KTP</figcaption>
                        <img src={row.selfie_url} alt="Selfie pemohon" className="w-full rounded-[var(--radius-field)] border border-line object-contain" />
                    </figure>
                </div>

                <dl className="mt-5 grid gap-3 rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm sm:grid-cols-2">
                    <Row label="NIK" value={<span className="font-mono tabular-nums">{row.nik}</span>} />
                    <Row label="Tempat, tanggal lahir" value={`${row.birth_place}, ${row.birth_date}`} />
                    <Row label="Alamat sesuai KTP" value={row.address} wide />
                    <Row
                        label="Persetujuan pengelolaan data"
                        value={`${formatDate(row.consented_at, true)}${row.consent_ip ? ` · IP ${row.consent_ip}` : ''}`}
                        wide
                    />
                    {row.reviewed_at && (
                        <Row label="Ditinjau" value={`${row.reviewer ?? '—'} · ${formatDate(row.reviewed_at, true)}`} wide />
                    )}
                </dl>

                {mode === 'reject' ? (
                    <form onSubmit={reject} className="mt-5 space-y-4">
                        <Field
                            label="Alasan penolakan"
                            required
                            hint="Ditampilkan apa adanya ke creator, jadi tulis bagian mana yang perlu diperbaiki."
                            htmlFor="identity-reason"
                            error={form.errors.reason}
                        >
                            <Textarea
                                id="identity-reason"
                                rows={3}
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                placeholder="Contoh: Foto KTP buram, nomor NIK tidak terbaca."
                            />
                        </Field>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setMode('view')}>Batal</Button>
                            <Button type="submit" variant="danger" disabled={form.processing}>
                                {form.processing ? 'Mengirim...' : 'Tolak dan kirim alasan'}
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="mt-5 flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setMode('reject')}>Tolak</Button>
                        <Button type="button" variant="success" onClick={approve} disabled={row.status === 'APPROVED'}>
                            {row.status === 'APPROVED' ? 'Sudah terverifikasi' : 'Setujui identitas'}
                        </Button>
                    </div>
                )}
            </Card>
        </div>
    );
}

function Row({ label, value, wide }: { label: string; value: React.ReactNode; wide?: boolean }) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs text-muted">{label}</dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}
