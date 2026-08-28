import { router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Wallet } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Alert, Badge, ButtonLink, Card, EmptyState, Select } from '@/components/ui';
import { cn, formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Entry {
    id: number;
    type: string;
    type_label: string;
    bucket: string;
    bucket_label: string;
    amount: number;
    balance_after: number;
    description: string | null;
    created_at: string | null;
}

export default function Balance({
    wallet,
    entries,
    filters,
    holdingDays,
}: {
    wallet: {
        pending: number;
        available: number;
        held: number;
        reserve: number;
        negative: number;
        withdrawn: number;
        lifetime_earned: number;
        is_frozen: boolean;
        currency: string;
    };
    entries: Paginated<Entry>;
    filters: { bucket?: string; type?: string };
    holdingDays: number;
}) {
    const filter = (key: string, value: string) => {
        router.get('/dashboard/saldo', { ...filters, [key]: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: Column<Entry>[] = [
        {
            key: 'description',
            header: 'Keterangan',
            render: (row) => (
                <span className="flex items-center gap-2.5">
                    <span
                        className={cn(
                            'grid size-8 shrink-0 place-items-center rounded-full',
                            row.amount >= 0
                                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300',
                        )}
                    >
                        {row.amount >= 0 ? (
                            <ArrowDownLeft className="size-4" />
                        ) : (
                            <ArrowUpRight className="size-4" />
                        )}
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-semibold">
                            {row.description ?? row.type_label}
                        </span>
                        <span className="block text-xs text-muted">{formatDate(row.created_at, true)}</span>
                    </span>
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Jenis',
            mobile: false,
            render: (row) => <Badge>{row.type_label}</Badge>,
        },
        {
            key: 'bucket',
            header: 'Kantong',
            render: (row) => <span className="text-sm text-muted">{row.bucket_label}</span>,
        },
        {
            key: 'amount',
            header: 'Nominal',
            align: 'right',
            render: (row) => (
                <span
                    className={cn(
                        'font-bold',
                        row.amount >= 0 ? 'text-[var(--success)]' : 'text-[var(--danger)]',
                    )}
                >
                    {row.amount >= 0 ? '+' : '−'}
                    {formatIDR(Math.abs(row.amount))}
                </span>
            ),
        },
        {
            key: 'balance',
            header: 'Saldo akhir',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{formatIDR(row.balance_after)}</span>,
        },
    ];

    return (
        <DashboardLayout title="Saldo" area="creator">
            <PageHeader
                title="Saldo"
                description="Setiap pergerakan dana tercatat di sini dan nggak bisa diubah."
                actions={
                    <ButtonLink href="/dashboard/penarikan" variant="gradient">
                        <Wallet className="size-4" />
                        Tarik Saldo
                    </ButtonLink>
                }
            />

            {wallet.is_frozen && (
                <div className="mb-4">
                    <Alert tone="danger" title="Saldo ditahan">
                        Saldo kamu sedang ditahan oleh tim JualanYok. Hubungi support untuk info lebih lanjut.
                    </Alert>
                </div>
            )}

            {wallet.negative > 0 && (
                <div className="mb-4">
                    <Alert tone="danger" title={`Saldo minus ${formatIDR(wallet.negative)}`}>
                        Refund atau penyesuaian terjadi setelah dana sempat dicairkan. Pendapatan berikutnya otomatis
                        menutup saldo ini dan penarikan baru ditahan sampai kembali nol.
                    </Alert>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <Card className="p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Tersedia</p>
                    <p className="mt-1.5 text-2xl font-extrabold tabular-nums text-[var(--success)]">
                        {formatIDR(wallet.available)}
                    </p>
                    <p className="mt-2 text-xs text-muted">Siap ditarik kapan saja.</p>
                </Card>

                <Card className="p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Tertahan</p>
                    <p className="mt-1.5 text-2xl font-extrabold tabular-nums">{formatIDR(wallet.pending)}</p>
                    <p className="mt-2 text-xs text-muted">Cair otomatis {holdingDays} hari setelah pembayaran.</p>
                </Card>

                <Card className="p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Diproses</p>
                    <p className="mt-1.5 text-2xl font-extrabold tabular-nums">{formatIDR(wallet.held)}</p>
                    <p className="mt-2 text-xs text-muted">Penarikan yang sedang berjalan.</p>
                </Card>

                <Card className="p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Dana cadangan</p>
                    <p className="mt-1.5 text-2xl font-extrabold tabular-nums">{formatIDR(wallet.reserve)}</p>
                    <p className="mt-2 text-xs text-muted">Perlindungan refund; dilepas otomatis sesuai jadwal.</p>
                </Card>

                <Card className={cn('p-5', wallet.negative > 0 && 'border-rose-200 bg-rose-50')}>
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Saldo minus</p>
                    <p className={cn('mt-1.5 text-2xl font-extrabold tabular-nums', wallet.negative > 0 && 'text-[var(--danger)]')}>
                        {wallet.negative > 0 ? `−${formatIDR(wallet.negative)}` : formatIDR(0)}
                    </p>
                    <p className="mt-2 text-xs text-muted">Dipulihkan otomatis dari pendapatan berikutnya.</p>
                </Card>

                <Card className="p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Total pendapatan</p>
                    <p className="mt-1.5 text-2xl font-extrabold tabular-nums">{formatIDR(wallet.lifetime_earned)}</p>
                    <p className="mt-2 text-xs text-muted">Sudah ditarik {formatIDR(wallet.withdrawn)}.</p>
                </Card>
            </div>

            <div className="mb-4 mt-6 flex flex-col gap-2 sm:flex-row">
                <Select
                    value={filters.bucket ?? ''}
                    onChange={(e) => filter('bucket', e.target.value)}
                    aria-label="Filter kantong saldo"
                    className="sm:w-52"
                >
                    <option value="">Semua kantong</option>
                    <option value="PENDING">Tertahan</option>
                    <option value="AVAILABLE">Tersedia</option>
                    <option value="HELD">Dibekukan</option>
                    <option value="RESERVE">Dana cadangan</option>
                    <option value="NEGATIVE">Saldo negatif</option>
                    <option value="WITHDRAWN">Sudah ditarik</option>
                </Select>

                <Select
                    value={filters.type ?? ''}
                    onChange={(e) => filter('type', e.target.value)}
                    aria-label="Filter jenis transaksi"
                    className="sm:w-56"
                >
                    <option value="">Semua jenis</option>
                    <option value="SELLER_REVENUE">Pendapatan penjualan</option>
                    <option value="AFFILIATE_COMMISSION">Komisi affiliate</option>
                    <option value="RELEASE">Pencairan ke tersedia</option>
                    <option value="RESERVE">Dana cadangan risiko</option>
                    <option value="RESERVE_RELEASE">Pelepasan cadangan</option>
                    <option value="DEBT">Saldo negatif</option>
                    <option value="DEBT_RECOVERY">Pelunasan saldo negatif</option>
                    <option value="REFUND">Refund</option>
                    <option value="WITHDRAWAL">Penarikan</option>
                    <option value="ADJUSTMENT">Penyesuaian</option>
                </Select>
            </div>

            <DataList
                rows={entries.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<Wallet className="size-6" />}
                        title="Belum ada transaksi"
                        description="Riwayat saldo akan muncul setelah ada penjualan pertama."
                    />
                }
            />

            <Pagination meta={entries} />
        </DashboardLayout>
    );
}
