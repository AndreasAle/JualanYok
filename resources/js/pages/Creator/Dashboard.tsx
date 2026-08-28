import { Link, router } from '@inertiajs/react';
import {
    ArrowRight, BarChart3, Blocks, Check, Copy, ExternalLink, Eye, Package, Plus, ShoppingBag, Ticket,
    TrendingUp, Wallet,
} from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AreaChart, BarList, StatCard, StatusBadge } from '@/components/shared';
import { Alert, Badge, Button, ButtonLink, Card, CardBody, CardHeader, CardTitle, EmptyState, Select } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

interface Props {
    range: { from: string; to: string };
    stats: {
        views: number;
        visitors: number;
        orders: number;
        gross_revenue: number;
        net_revenue: number;
        leads: number;
        conversion_rate: number;
        average_order_value: number;
    };
    change: Record<string, number | null>;
    series: { date: string; views: number; orders: number; gross: number; net: number }[];
    topProducts: { product_id: number; name: string; quantity: number; revenue: number }[];
    balance: { pending: number; available: number; held: number; reserve: number; negative: number; withdrawn: number };
    recentOrders: {
        number: string;
        customer_name: string;
        grand_total: number;
        status: string;
        status_label: string;
        items_count: number;
        created_at: string;
    }[];
    checklist: { key: string; label: string; done: boolean; href: string }[];
    store: {
        username: string;
        name: string;
        is_published: boolean;
        public_url: string;
        products_count: number;
        blocks_count: number;
    };
}

const RANGES = [
    { label: '7 hari terakhir', days: 7 },
    { label: '30 hari terakhir', days: 30 },
    { label: '90 hari terakhir', days: 90 },
];

export default function CreatorDashboard({
    range, stats, change, series, topProducts, balance, recentOrders, checklist, store,
}: Props) {
    const [copied, setCopied] = useState(false);

    const remaining = checklist.filter((item) => !item.done);

    const changeRange = (days: number) => {
        const to = new Date();
        const from = new Date();
        from.setDate(to.getDate() - (days - 1));

        router.get('/dashboard', {
            from: from.toISOString().slice(0, 10),
            to: to.toISOString().slice(0, 10),
        }, { preserveState: true, preserveScroll: true });
    };

    const copyLink = async () => {
        await navigator.clipboard.writeText(store.public_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <DashboardLayout title="Ringkasan" area="creator">
            <section className="relative mb-6 overflow-hidden rounded-[2rem] bg-[#1b1925] p-5 text-white shadow-[0_24px_70px_rgba(27,25,37,.18)] sm:p-7 lg:p-8">
                <span className="pointer-events-none absolute -right-20 -top-28 size-72 rounded-full bg-violet-500/25 blur-3xl" />
                <span className="pointer-events-none absolute -bottom-32 left-1/3 size-64 rounded-full bg-rose-400/15 blur-3xl" />
                <div className="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-[10px] font-black uppercase tracking-[.2em] text-violet-300">
                            <span className="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.8)]" /> Creator command center
                        </div>
                        <h1 className="mt-3 text-3xl font-black tracking-[-.045em] sm:text-4xl">Halo, {store.name}!</h1>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-white/55">Pantau performa, kelola penjualan, dan tentukan langkah berikutnya dari satu workspace.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Select defaultValue="30" onChange={(e) => changeRange(Number(e.target.value))} aria-label="Rentang tanggal" className="w-auto border-white/10 bg-white/10 text-white shadow-none">
                            {RANGES.map((r) => <option key={r.days} value={r.days} className="text-black">{r.label}</option>)}
                        </Select>
                        <ButtonLink href={`/dashboard/produk/create${store.products_count === 0 ? '?first=1' : ''}`} variant="ghost" className="rounded-xl bg-white px-5 text-[#171620] hover:bg-white/90 hover:text-[#171620]">
                            <Plus className="size-4" /> {store.products_count === 0 ? 'Buat produk pertama' : 'Tambah produk'}
                        </ButtonLink>
                    </div>
                </div>

                <div className="relative mt-7 flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/[.065] p-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-500 text-white shadow-lg"><Eye className="size-5" /></span>
                        <div className="min-w-0"><div className="flex items-center gap-2"><p className="truncate text-sm font-extrabold">/{store.username}</p><Badge tone={store.is_published ? 'success' : 'warning'}>{store.is_published ? 'Live' : 'Draft'}</Badge></div><p className="mt-0.5 text-xs text-white/45">{formatNumber(stats.views)} kunjungan · {store.products_count} produk · {store.blocks_count} bagian</p></div>
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <Button variant="ghost" size="sm" className="border border-white/10 bg-white/[.06] text-white hover:bg-white/10 hover:text-white" onClick={copyLink}>{copied ? <Check /> : <Copy />}{copied ? 'Tersalin' : 'Salin link'}</Button>
                        <a href={store.public_url} target="_blank" rel="noopener noreferrer" className="inline-flex h-9 items-center gap-2 rounded-xl bg-white px-3 text-xs font-extrabold text-[#171620]"><ExternalLink className="size-3.5" /> Buka toko</a>
                    </div>
                </div>
            </section>

            <section className="mb-6">
                <div className="mb-3 flex items-end justify-between gap-3">
                    <div><p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-600">Mulai cepat</p><h2 className="mt-1 text-lg font-black tracking-tight">Apa yang mau kamu kerjakan?</h2></div>
                    <span className="hidden text-xs text-muted sm:block">Akses pekerjaan utama tanpa mencari menu.</span>
                </div>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        { href: `/dashboard/produk/create${store.products_count === 0 ? '?first=1' : ''}`, label: store.products_count === 0 ? 'Buat produk pertama' : 'Tambah produk', hint: 'Digital, fisik, jasa, atau affiliate', icon: <Plus className="size-5" />, tone: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' },
                        { href: '/dashboard/toko', label: 'Atur tampilan', hint: 'Block, template, dan gaya toko', icon: <Blocks className="size-5" />, tone: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' },
                        { href: '/dashboard/kupon/create', label: 'Buat promo', hint: 'Kupon untuk dorong penjualan', icon: <Ticket className="size-5" />, tone: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' },
                        { href: '/dashboard/analitik', label: 'Lihat performa', hint: 'Kunjungan, klik, dan konversi', icon: <BarChart3 className="size-5" />, tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
                    ].map((action) => (
                        <Link key={action.href} href={action.href} className="group flex min-h-28 flex-col rounded-[1.35rem] border border-line bg-surface p-4 shadow-[0_8px_24px_rgba(16,24,40,.04)] transition duration-300 hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-lift">
                            <span className={`grid size-10 place-items-center rounded-xl ${action.tone}`}>{action.icon}</span>
                            <span className="mt-4 flex items-center justify-between gap-2 text-sm font-extrabold">{action.label}<ArrowRight className="size-4 text-muted transition-transform group-hover:translate-x-1 group-hover:text-violet-600" /></span>
                            <span className="mt-1 text-[11px] leading-4 text-muted">{action.hint}</span>
                        </Link>
                    ))}
                </div>
            </section>

            {/* Checklist */}
            {remaining.length > 0 && (
                <Card className="mb-6 p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="font-bold">Lengkapi toko kamu</p>
                            <p className="text-sm text-muted">
                                {checklist.length - remaining.length} dari {checklist.length} selesai
                            </p>
                        </div>
                        <span className="text-2xl font-extrabold gradient-text">
                            {Math.round(((checklist.length - remaining.length) / checklist.length) * 100)}%
                        </span>
                    </div>

                    <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                        {checklist.map((item) => (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className="flex items-center gap-2.5 rounded-[var(--radius-field)] px-3 py-2.5 text-sm transition-colors hover:bg-surface-2"
                                >
                                    <span
                                        className={
                                            item.done
                                                ? 'grid size-5 shrink-0 place-items-center rounded-full bg-[var(--success)] text-white'
                                                : 'size-5 shrink-0 rounded-full border-2 border-line'
                                        }
                                    >
                                        {item.done && <Check className="size-3" />}
                                    </span>
                                    <span className={item.done ? 'text-muted line-through' : 'font-medium'}>
                                        {item.label}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {/* Stats */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Penjualan kotor"
                    value={formatIDR(stats.gross_revenue)}
                    change={change.gross_revenue}
                    hint="vs periode sebelumnya"
                    icon={<TrendingUp className="size-4.5" />}
                    tone="brand"
                />
                <StatCard
                    label="Pendapatan bersih"
                    value={formatIDR(stats.net_revenue)}
                    change={change.net_revenue}
                    icon={<Wallet className="size-4.5" />}
                />
                <StatCard
                    label="Pesanan"
                    value={formatNumber(stats.orders)}
                    change={change.orders}
                    icon={<ShoppingBag className="size-4.5" />}
                />
                <StatCard
                    label="Pengunjung"
                    value={formatNumber(stats.visitors)}
                    change={change.visitors}
                    icon={<Eye className="size-4.5" />}
                />
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                <StatCard label="Konversi" value={`${stats.conversion_rate}%`} hint="kunjungan → pesanan" />
                <StatCard label="Rata-rata order" value={formatIDR(stats.average_order_value)} />
                <StatCard label="Leads terkumpul" value={formatNumber(stats.leads)} change={change.leads} />
            </div>

            {/* Wallet bento */}
            {balance.negative > 0 && (
                <Alert tone="danger" title={`Saldo minus ${formatIDR(balance.negative)}`} className="mt-6">
                    Penarikan ditahan sementara. Pendapatan berikutnya otomatis dipakai untuk memulihkan saldo akibat
                    refund atau penyesuaian.
                </Alert>
            )}
            <div className="mt-6 grid gap-4 lg:grid-cols-[.9fr_1.6fr]">
                <Card className="relative overflow-hidden border-transparent bg-[#1b1925] p-6 text-white">
                    <span className="absolute -right-12 -top-12 size-40 rounded-full bg-emerald-400/15 blur-2xl" />
                    <div className="relative">
                        <span className="grid size-10 place-items-center rounded-xl bg-white/10"><Wallet className="size-5 text-emerald-300" /></span>
                        <p className="mt-6 text-[10px] font-black uppercase tracking-[.16em] text-white/45">Saldo siap dicairkan</p>
                        <p className="mt-2 text-3xl font-black tracking-[-.04em] tabular-nums text-emerald-300">{formatIDR(balance.available)}</p>
                        <p className="mt-2 text-xs leading-5 text-white/45">Dana bersih yang sudah melewati masa tahan dan siap masuk rekeningmu.</p>
                        <ButtonLink href="/dashboard/penarikan" variant="ghost" size="sm" className="mt-5 rounded-xl bg-white text-[#171620] hover:bg-white/90 hover:text-[#171620]">Tarik saldo <ArrowRight /></ButtonLink>
                    </div>
                </Card>
                <Card className="grid overflow-hidden p-2 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Saldo tertahan', balance.pending, 'Cair setelah masa refund'],
                        ['Dana cadangan', balance.reserve, 'Proteksi risiko, dilepas otomatis'],
                        ['Sedang ditarik', balance.held, 'Dalam proses finance'],
                        ['Total ditarik', balance.withdrawn, 'Akumulasi pencairan'],
                    ].map(([label, value, hint], index) => (
                        <div key={label as string} className={index > 0 ? 'rounded-2xl border-t border-line p-5 sm:border-l sm:border-t-0' : 'rounded-2xl p-5'}>
                            <p className="text-[10px] font-black uppercase tracking-[.14em] text-muted">{label as string}</p>
                            <p className="mt-3 text-xl font-black tabular-nums">{formatIDR(value as number)}</p>
                            <p className="mt-1 text-xs leading-5 text-muted">{hint as string}</p>
                            {index === 3 && <ButtonLink href="/dashboard/saldo" variant="ghost" size="sm" className="mt-3 -ml-3 text-violet-600">Lihat riwayat <ArrowRight /></ButtonLink>}
                        </div>
                    ))}
                </Card>
            </div>

            {/* Chart + top products */}
            <div className="mt-6 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Penjualan harian</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <AreaChart data={series} valueKey="gross" />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Produk terlaris</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {topProducts.length === 0 ? (
                            <EmptyState
                                icon={<Package className="size-6" />}
                                title="Belum ada penjualan"
                                description="Begitu ada yang beli, produk terlaris muncul di sini."
                                action={
                                    <ButtonLink href="/dashboard/produk/create" variant="gradient" size="sm">
                                        Tambah Produk
                                    </ButtonLink>
                                }
                            />
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
            </div>

            {/* Recent orders */}
            <Card className="mt-6">
                <CardHeader className="flex items-center justify-between">
                    <CardTitle>Pesanan terbaru</CardTitle>
                    <Link
                        href="/dashboard/pesanan"
                        className="flex items-center gap-1 text-sm font-semibold text-[var(--primary)] hover:underline"
                    >
                        Lihat semua
                        <ArrowRight className="size-3.5" />
                    </Link>
                </CardHeader>
                <CardBody>
                    {recentOrders.length === 0 ? (
                        <EmptyState
                            icon={<ShoppingBag className="size-6" />}
                            title="Belum ada pesanan"
                            description="Bagikan link tokomu ke followers biar mulai ada yang beli."
                        />
                    ) : (
                        <ul className="divide-y divide-[var(--border)]">
                            {recentOrders.map((order) => (
                                <li key={order.number}>
                                    <Link
                                        href={`/dashboard/pesanan/${order.number}`}
                                        className="flex items-center justify-between gap-3 py-3 transition-colors hover:bg-surface-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-bold">{order.customer_name}</p>
                                            <p className="text-xs text-muted">
                                                {order.number} · {order.items_count} item · {order.created_at}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="font-bold tabular-nums">{formatIDR(order.grand_total)}</p>
                                            <StatusBadge status={order.status} label={order.status_label} />
                                        </div>
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
