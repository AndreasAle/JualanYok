import { router } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AreaChart, BarList, PageHeader, StatCard } from '@/components/shared';
import { Alert, Card, CardBody, CardHeader, CardTitle, EmptyState, Input } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

export default function Analytics({
    range,
    stats,
    change,
    series,
    topProducts,
    sources,
    funnel,
    topBlocks,
    advanced,
}: {
    range: { from: string; to: string };
    stats: any;
    change: Record<string, number | null>;
    series: any[];
    topProducts: { name: string; quantity: number; revenue: number }[];
    sources: Record<string, number>;
    funnel: { views: number; checkouts: number; orders: number };
    topBlocks: { title: string; clicks: number; impressions: number }[];
    advanced: boolean;
}) {
    const setRange = (key: 'from' | 'to', value: string) => {
        router.get('/dashboard/analitik', { ...range, [key]: value }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const funnelSteps = [
        { label: 'Kunjungan toko', value: funnel.views },
        { label: 'Mulai checkout', value: funnel.checkouts },
        { label: 'Pesanan dibayar', value: funnel.orders },
    ];

    return (
        <DashboardLayout title="Analitik" area="creator">
            <PageHeader
                title="Analitik"
                description="Lihat dari mana pengunjung datang dan apa yang bikin mereka beli."
                actions={
                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            value={range.from}
                            onChange={(e) => setRange('from', e.target.value)}
                            aria-label="Tanggal mulai"
                            className="w-auto"
                        />
                        <span className="text-muted">–</span>
                        <Input
                            type="date"
                            value={range.to}
                            onChange={(e) => setRange('to', e.target.value)}
                            aria-label="Tanggal akhir"
                            className="w-auto"
                        />
                    </div>
                }
            />

            {!advanced && (
                <div className="mb-4">
                    <Alert tone="info" title="Analitik lanjutan ada di paket Creator ke atas">
                        Data dasar tetap kamu dapat. Upgrade buat laporan yang lebih dalam dan export.
                    </Alert>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Kunjungan" value={formatNumber(stats.views)} change={change.views} />
                <StatCard label="Pengunjung unik" value={formatNumber(stats.visitors)} change={change.visitors} />
                <StatCard label="Konversi" value={`${stats.conversion_rate}%`} />
                <StatCard
                    label="Penjualan kotor"
                    value={formatIDR(stats.gross_revenue)}
                    change={change.gross_revenue}
                    tone="brand"
                />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Tren penjualan</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <AreaChart data={series} valueKey="gross" />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Funnel</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <ul className="space-y-3">
                            {funnelSteps.map((step, i) => {
                                const base = funnelSteps[0].value || 1;
                                const percent = Math.round((step.value / base) * 100);

                                return (
                                    <li key={step.label}>
                                        <div className="flex items-baseline justify-between text-sm">
                                            <span className="font-medium">{step.label}</span>
                                            <span className="font-bold tabular-nums">
                                                {formatNumber(step.value)}
                                                <span className="ml-1 text-xs font-normal text-muted">{percent}%</span>
                                            </span>
                                        </div>
                                        <div className="mt-1.5 h-2.5 rounded-full bg-surface-2">
                                            <div
                                                className="h-full rounded-full gradient-brand"
                                                style={{ width: `${Math.max(3, percent)}%` }}
                                            />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <p className="mt-4 text-xs text-muted">
                            Dari {formatNumber(funnel.views)} kunjungan, {formatNumber(funnel.orders)} berakhir jadi
                            pesanan dibayar.
                        </p>
                    </CardBody>
                </Card>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Produk terlaris</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {topProducts.length === 0 ? (
                            <EmptyState title="Belum ada penjualan" description="Data muncul setelah ada transaksi." />
                        ) : (
                            <BarList
                                items={topProducts.map((p) => ({
                                    label: p.name,
                                    value: p.revenue,
                                    hint: `${p.quantity} terjual`,
                                }))}
                                format={formatIDR}
                            />
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sumber traffic</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {Object.keys(sources).length === 0 ? (
                            <EmptyState title="Belum ada data traffic" />
                        ) : (
                            <BarList
                                items={Object.entries(sources).map(([key, value]) => ({
                                    label: key === 'direct' ? 'Langsung' : key,
                                    value,
                                }))}
                                format={formatNumber}
                            />
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Block paling diklik</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {topBlocks.length === 0 ? (
                            <EmptyState title="Belum ada klik" />
                        ) : (
                            <BarList
                                items={topBlocks.map((b) => ({
                                    label: b.title,
                                    value: b.clicks,
                                    hint: `${formatNumber(b.impressions)} tayang`,
                                }))}
                                format={formatNumber}
                            />
                        )}
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
