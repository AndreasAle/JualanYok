import { Link, router } from '@inertiajs/react';
import { AlertTriangle, CircleDollarSign, Landmark, ShieldCheck, TrendingUp, WalletCards } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AreaChart, PageHeader, StatCard } from '@/components/shared';
import { Badge, Card, CardBody, CardHeader, CardTitle, EmptyState } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

type Stats = {
    orders: number; gmv: number; merchandise: number; platform_fee: number; gateway_fee: number;
    affiliate_fee: number; shipping_variance: number; refunds: number; refund_loss: number;
    contribution: number; margin_percent: number; reserve_balance: number; negative_balance: number;
    negative_wallets: number; unbalanced_journals: number; gateway_paid_by_platform: number;
    split_cost: number; subscription_revenue: number; subscription_gateway_cost: number;
    biteship_api_cost: number; payout_subsidy: number; refund_rate: number; open_refunds: number;
    open_refund_amount: number; refund_platform_fee_reversal: number;
    legacy_orders: number;
};

export default function Economics({ days, stats, channels, daily, lowMarginOrders, alerts }: {
    days: number;
    stats: Stats;
    channels: Array<{ provider: string; method: string; channel: string; transactions: number; volume: number; cost: number; effective_rate: number; settlement_days: number }>;
    daily: Array<{ date: string; gmv: number; revenue: number; contribution: number }>;
    lowMarginOrders: Array<{ number: string; store: string; gmv: number; platform_fee: number; gateway_fee: number; margin: number }>;
    alerts: Array<{ severity: 'warning' | 'critical'; message: string }>;
}) {
    return (
        <DashboardLayout title="Unit Economics" area="admin">
            <PageHeader title="Unit Economics" description="Angka riil dari order, gateway, refund, ongkir, reserve, dan ledger." actions={
                <select value={days} onChange={(e) => router.get('/admin/ekonomi', { days: Number(e.target.value) }, { preserveState: true })} className="h-10 rounded-xl border border-line bg-app px-3 text-sm font-semibold">
                    <option value={7}>7 hari</option><option value={30}>30 hari</option><option value={90}>90 hari</option><option value={365}>1 tahun</option>
                </select>
            } />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="GMV" value={formatIDR(stats.gmv)} hint={`${formatNumber(stats.orders)} transaksi`} icon={<TrendingUp className="size-4.5" />} tone="brand" />
                <StatCard label="Pendapatan platform" value={formatIDR(stats.platform_fee + stats.subscription_revenue)} hint={`komisi ${formatIDR(stats.platform_fee)} · langganan ${formatIDR(stats.subscription_revenue)}`} icon={<CircleDollarSign className="size-4.5" />} />
                <StatCard label="Biaya gateway" value={formatIDR(stats.gateway_fee)} hint={`subsidi platform ${formatIDR(stats.gateway_paid_by_platform)}`} icon={<Landmark className="size-4.5" />} />
                <StatCard label="Contribution margin" value={formatIDR(stats.contribution)} hint={`${stats.margin_percent}% dari GMV`} icon={<WalletCards className="size-4.5" />} />
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Dana reserve" value={formatIDR(stats.reserve_balance)} hint="bukan pendapatan" />
                <StatCard label="Saldo negatif" value={formatIDR(stats.negative_balance)} hint={`${stats.negative_wallets} akun perlu dipulihkan`} />
                <StatCard label="Refund selesai" value={formatIDR(stats.refunds)} hint={`dampak margin ${formatIDR(stats.refund_loss)}`} />
                <StatCard label="Selisih ongkir" value={formatIDR(stats.shipping_variance)} hint="positif = efisien" />
            </div>

            {alerts.length > 0 && <div className="mt-4 space-y-2">{alerts.map((alert, index) => <div key={`${alert.message}-${index}`} className={`rounded-2xl border p-4 text-sm font-medium ${alert.severity === 'critical' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'}`}><AlertTriangle className="mr-2 inline size-4" />{alert.message}</div>)}</div>}

            <div className="mt-5 grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                <Card><CardHeader><CardTitle>Contribution harian</CardTitle></CardHeader><CardBody><AreaChart data={daily} valueKey="contribution" /></CardBody></Card>
                <Card><CardHeader><CardTitle>Profit Guard</CardTitle></CardHeader><CardBody><div className="flex items-center gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-800"><ShieldCheck className="size-6" /><div><p className="font-bold">{stats.unbalanced_journals === 0 ? 'Semua jurnal seimbang' : 'Perlu rekonsiliasi'}</p><p className="text-xs">Debit dan kredit diperiksa dari posting aktual.</p></div></div><div className="mt-3 divide-y divide-line rounded-xl border border-line px-4 text-sm"><div className="flex justify-between py-3"><span>Gateway langganan</span><b>{formatIDR(stats.subscription_gateway_cost)}</b></div><div className="flex justify-between py-3"><span>Biaya split</span><b>{formatIDR(stats.split_cost)}</b></div><div className="flex justify-between py-3"><span>API Biteship</span><b>{formatIDR(stats.biteship_api_cost)}</b></div><div className="flex justify-between py-3"><span>Subsidi payout</span><b>{formatIDR(stats.payout_subsidy)}</b></div><div className="flex justify-between py-3"><span>Refund menunggu</span><b>{stats.open_refunds} · {formatIDR(stats.open_refund_amount)}</b></div><div className="flex justify-between py-3"><span>Refund rate</span><b>{stats.refund_rate}%</b></div></div></CardBody></Card>
            </div>

            <Card className="mt-4"><CardHeader><CardTitle>Biaya per kanal pembayaran</CardTitle></CardHeader><CardBody>{channels.length === 0 ? <EmptyState title="Belum ada transaksi" /> : <div className="overflow-x-auto"><table className="w-full min-w-[680px] text-left text-sm"><thead className="text-xs uppercase text-muted"><tr><th className="pb-3">Kanal</th><th>Transaksi</th><th>Volume</th><th>Biaya aktual</th><th>Rate efektif</th><th>Settlement</th></tr></thead><tbody className="divide-y divide-line">{channels.map((row) => <tr key={`${row.provider}-${row.method}-${row.channel}`}><td className="py-3 font-bold uppercase">{row.method} {row.channel}<span className="ml-2 text-xs font-normal text-muted">{row.provider}</span></td><td>{formatNumber(row.transactions)}</td><td>{formatIDR(row.volume)}</td><td>{formatIDR(row.cost)}</td><td>{row.effective_rate}%</td><td>H+{row.settlement_days}</td></tr>)}</tbody></table></div>}</CardBody></Card>

            <Card className="mt-4"><CardHeader><CardTitle>Order margin nol atau minus</CardTitle></CardHeader><CardBody>{lowMarginOrders.length === 0 ? <EmptyState title="Tidak ada order merugi" /> : <div className="space-y-2">{lowMarginOrders.map((order) => <Link href={`/admin/pesanan/${order.number}`} key={order.number} className="grid gap-2 rounded-xl border border-line p-3 hover:bg-surface-2 sm:grid-cols-[1.2fr_1fr_repeat(3,.8fr)]"><span><b className="block font-mono">{order.number}</b><small className="text-muted">{order.store}</small></span><span>GMV <b className="block">{formatIDR(order.gmv)}</b></span><span>Komisi <b className="block">{formatIDR(order.platform_fee)}</b></span><span>Gateway <b className="block">{formatIDR(order.gateway_fee)}</b></span><span>Margin <Badge tone="danger">{formatIDR(order.margin)}</Badge></span></Link>)}</div>}</CardBody></Card>
        </DashboardLayout>
    );
}
