import { router, useForm } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, StatusBadge, type Column } from '@/components/shared';
import {
    Alert, Badge, Button, Card, EmptyState, Field, Input, Select, Textarea,
} from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Row {
    number: string;
    user: { name: string; username: string; email: string };
    amount: number;
    fee: number;
    net_amount: number;
    status: string;
    status_label: string;
    is_open: boolean;
    account: { provider: string; masked: string; account_name: string } | null;
    account_verified: boolean;
    review_note: string | null;
    created_at: string;
    paid_at: string | null;
}

export default function AdminWithdrawals({
    withdrawals,
    filters,
    statuses,
    canProcess,
}: {
    withdrawals: Paginated<Row>;
    filters: { status?: string; q?: string };
    statuses: { value: string; label: string }[];
    canProcess: boolean;
}) {
    const [acting, setActing] = useState<{ row: Row; mode: 'approve' | 'reject' | 'pay' } | null>(null);

    const columns: Column<Row>[] = [
        {
            key: 'number',
            header: 'Penarikan',
            render: (row) => (
                <span>
                    <span className="block font-mono text-sm font-semibold">{row.number}</span>
                    <span className="block text-xs text-muted">{formatDate(row.created_at, true)}</span>
                </span>
            ),
        },
        {
            key: 'user',
            header: 'Pengaju',
            render: (row) => (
                <span>
                    <span className="block text-sm font-medium">{row.user.name}</span>
                    <span className="block text-xs text-muted">@{row.user.username}</span>
                </span>
            ),
        },
        {
            key: 'account',
            header: 'Rekening',
            render: (row) =>
                row.account ? (
                    <span>
                        <span className="block text-sm">
                            {row.account.provider} {row.account.masked}
                        </span>
                        <span className="block text-xs text-muted">{row.account.account_name}</span>
                        {!row.account_verified && <Badge tone="warning">Belum verifikasi</Badge>}
                    </span>
                ) : (
                    <span className="text-muted">—</span>
                ),
        },
        {
            key: 'amount',
            header: 'Nominal',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-bold">{formatIDR(row.amount)}</span>
                    <span className="block text-xs text-muted">Transfer {formatIDR(row.net_amount)}</span>
                </span>
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
                canProcess && row.is_open ? (
                    <span className="flex flex-wrap justify-end gap-1">
                        {row.status === 'REQUESTED' && (
                            <Button size="sm" variant="outline" onClick={() => setActing({ row, mode: 'approve' })}>
                                Setujui
                            </Button>
                        )}
                        {(row.status === 'APPROVED' || row.status === 'PROCESSING') && (
                            <Button size="sm" variant="success" onClick={() => setActing({ row, mode: 'pay' })}>
                                Tandai Cair
                            </Button>
                        )}
                        <Button size="sm" variant="ghost" onClick={() => setActing({ row, mode: 'reject' })}>
                            Tolak
                        </Button>
                    </span>
                ) : null,
        },
    ];

    return (
        <DashboardLayout title="Penarikan" area="admin">
            <PageHeader
                title="Penarikan"
                description="Review dan proses pencairan dana creator dan afiliator."
            />

            {!canProcess && (
                <div className="mb-4">
                    <Alert tone="info">
                        Kamu bisa melihat daftar ini, tapi hanya finance admin yang bisa memproses penarikan.
                    </Alert>
                </div>
            )}

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/penarikan"
                    value={filters.q}
                    placeholder="Cari nomor, nama, atau email..."
                    extra={filters}
                />

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/admin/penarikan',
                            { ...filters, status: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter status"
                    className="sm:w-56"
                >
                    <option value="">Semua status</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
            </div>

            <DataList
                rows={withdrawals.data}
                columns={columns}
                rowKey={(row) => row.number}
                empty={
                    <EmptyState
                        icon={<CreditCard className="size-6" />}
                        title="Nggak ada penarikan"
                        description="Penarikan yang diajukan creator muncul di sini."
                    />
                }
            />

            <Pagination meta={withdrawals} />

            {acting && <ActionDialog action={acting} onClose={() => setActing(null)} />}
        </DashboardLayout>
    );
}

function ActionDialog({
    action,
    onClose,
}: {
    action: { row: Row; mode: 'approve' | 'reject' | 'pay' };
    onClose: () => void;
}) {
    const { row, mode } = action;

    const form = useForm({
        note: '',
        reason: '',
        transfer_reference: '',
    });

    // State-transition failures come back under `status`, not a payload key.
    const serverErrors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const url = {
            approve: `/admin/penarikan/${row.number}/setujui`,
            reject: `/admin/penarikan/${row.number}/tolak`,
            pay: `/admin/penarikan/${row.number}/bayar`,
        }[mode];

        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    const titles = {
        approve: 'Setujui penarikan',
        reject: 'Tolak penarikan',
        pay: 'Tandai dana sudah cair',
    };

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="w-full max-w-md animate-rise p-6">
                <h2 className="text-lg font-bold">{titles[mode]}</h2>
                <p className="mt-1 text-sm text-muted">
                    {row.number} · {formatIDR(row.amount)} · {row.user.name}
                </p>

                {row.account && (
                    <div className="mt-4 rounded-[var(--radius-field)] bg-surface-2 p-3 text-sm">
                        <p className="font-semibold">
                            {row.account.provider} {row.account.masked}
                        </p>
                        <p className="text-muted">a.n. {row.account.account_name}</p>
                        <p className="mt-1 font-bold">Transfer: {formatIDR(row.net_amount)}</p>
                    </div>
                )}

                <form onSubmit={submit} className="mt-4 space-y-3">
                    {mode === 'reject' && (
                        <Field
                            label="Alasan penolakan"
                            required
                            error={form.errors.reason}
                            hint="Alasan ini dikirim ke pengaju."
                            htmlFor="reason"
                        >
                            <Textarea
                                id="reason"
                                rows={3}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                required
                            />
                        </Field>
                    )}

                    {mode === 'approve' && (
                        <Field label="Catatan internal" error={form.errors.note} htmlFor="note">
                            <Textarea
                                id="note"
                                rows={2}
                                value={form.data.note}
                                onChange={(e) => form.setData('note', e.target.value)}
                            />
                        </Field>
                    )}

                    {mode === 'pay' && (
                        <Field
                            label="Referensi transfer"
                            required
                            error={form.errors.transfer_reference}
                            hint="Nomor referensi dari mutasi bank."
                            htmlFor="ref"
                        >
                            <Input
                                id="ref"
                                value={form.data.transfer_reference}
                                onChange={(e) => form.setData('transfer_reference', e.target.value)}
                                invalid={!!form.errors.transfer_reference}
                                required
                            />
                        </Field>
                    )}

                    {serverErrors.status && <Alert tone="danger">{serverErrors.status}</Alert>}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="ghost" onClick={onClose}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant={mode === 'reject' ? 'danger' : mode === 'pay' ? 'success' : 'primary'}
                            loading={form.processing}
                        >
                            {titles[mode]}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
