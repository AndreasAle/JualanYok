import { Link } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Receipt, Store, TrendingUp, Users, Wallet } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AreaChart, PageHeader, StatCard, StatusBadge } from '@/components/shared';
import { Card, CardBody, CardHeader, CardTitle, EmptyState } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

export default function AdminDashboard({
    stats,
    series,
    queues,
    recentWithdrawals,
}: {
    stats: {
        users: number;
        creators: number;
        stores_live: number;
        orders_30d: number;
        gross_30d: number;
        platform_revenue_30d: number;
        payable: number;
        held: number;
    };
    series: { date: string; orders: number; gross: number; fees: number }[];
    queues: { withdrawals_open: number; refunds_open: number; commissions_pending: number };
    recentWithdrawals: any[];
}) {
    const queueItems = [
        {
            label: 'Penarikan menunggu review',
            count: queues.withdrawals_open,
            href: '/admin/penarikan?status=REQUESTED',
            icon: <CreditCard className="size-5" />,
        },
        {
            label: 'Refund menunggu keputusan',
            count: queues.refunds_open,
            href: '/admin/refund?status=REQUESTED',
            icon: <Receipt className="size-5" />,
        },
        {
            label: 'Komisi dalam masa tahan',
            count: queues.commissions_pending,
            href: '/admin/ledger?type=AFFILIATE_COMMISSION',
            icon: <Wallet className="size-5" />,
        },
    ];

    return (
        <DashboardLayout title="Admin" area="admin">
            <PageHeader
                title="Dashboard Platform"
                description="Ringkasan 30 hari terakhir dan antrean yang butuh tindakan."
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Penjualan kotor 30 hari"
                    value={formatIDR(stats.gross_30d)}
                    icon={<TrendingUp className="size-4.5" />}
                    tone="brand"
                />
                <StatCard
                    label="Pendapatan platform"
                    value={formatIDR(stats.platform_revenue_30d)}
                    hint="dari biaya transaksi"
                />
                <StatCard label="Pesanan 30 hari" value={formatNumber(stats.orders_30d)} />
                <StatCard label="Toko live" value={formatNumber(stats.stores_live)} icon={<Store className="size-4.5" />} />
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total pengguna" value={formatNumber(stats.users)} icon={<Users className="size-4.5" />} />
                <StatCard label="Creator aktif" value={formatNumber(stats.creators)} />
                <StatCard label="Kewajiban ke seller" value={formatIDR(stats.payable)} hint="tertahan + tersedia" />
                <StatCard label="Sedang ditarik" value={formatIDR(stats.held)} />
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Volume transaksi harian</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <AreaChart data={series} valueKey="gross" />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Butuh tindakan</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <ul className="space-y-2">
                            {queueItems.map((item) => (
                                <li key={item.label}>
                                    <Link
                                        href={item.href}
                                        className="flex items-center gap-3 rounded-[var(--radius-field)] p-3 transition-colors hover:bg-surface-2"
                                    >
                                        <span
                                            className={
                                                item.count > 0
                                                    ? 'grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                                    : 'grid size-10 shrink-0 place-items-center rounded-xl bg-surface-2 text-muted'
                                            }
                                        >
                                            {item.icon}
                                        </span>
                                        <span className="min-w-0 flex-1 text-sm font-medium">{item.label}</span>
                                        <span className="shrink-0 text-lg font-semibold tabular-nums">
                                            {formatNumber(item.count)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {queueItems.every((item) => item.count === 0) && (
                            <p className="mt-3 flex items-center gap-1.5 text-xs text-muted">
                                <AlertTriangle className="size-3.5" />
                                Nggak ada antrean. Semua sudah tertangani.
                            </p>
                        )}
                    </CardBody>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Penarikan terbaru</CardTitle>
                </CardHeader>
                <CardBody>
                    {recentWithdrawals.length === 0 ? (
                        <EmptyState title="Belum ada penarikan" />
                    ) : (
                        <ul className="divide-y divide-[var(--border)]">
                            {recentWithdrawals.map((withdrawal) => (
                                <li key={withdrawal.number}>
                                    <Link
                                        href="/admin/penarikan"
                                        className="flex items-center justify-between gap-3 py-3 hover:bg-surface-2"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-mono text-sm font-semibold">
                                                {withdrawal.number}
                                            </span>
                                            <span className="block text-xs text-muted">
                                                {withdrawal.user} · {withdrawal.created_at}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="block font-bold">{formatIDR(withdrawal.amount)}</span>
                                            <StatusBadge status={withdrawal.status} label={withdrawal.status_label} />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>
        </DashboardLayout>
    );
}
