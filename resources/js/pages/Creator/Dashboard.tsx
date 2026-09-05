import { Link, router } from '@inertiajs/react';
import {
    ArrowRight, ArrowUpRight, BarChart3, Blocks, Check, Copy, Eye, Package, Plus,
    ShoppingBag, Ticket, TrendingUp, Wallet,
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

/**
 * Shortcuts to the four jobs a creator opens this page to do.
 *
 * Deliberately monochrome. A rotating violet/sky/rose/emerald icon tile made
 * the four look like four different features from four different products,
 * and gave colour to navigation — which is never the most important thing on
 * the screen.
 */
const ACTIONS = [
    { key: 'produk', label: 'Tambah produk', hint: 'Digital, fisik, jasa, atau affiliate', icon: <Plus className="size-4" /> },
    { key: 'toko', href: '/dashboard/toko', label: 'Atur tampilan', hint: 'Block, template, dan gaya toko', icon: <Blocks className="size-4" /> },
    { key: 'kupon', href: '/dashboard/kupon/create', label: 'Buat promo', hint: 'Kupon untuk mendorong penjualan', icon: <Ticket className="size-4" /> },
    { key: 'analitik', href: '/dashboard/analitik', label: 'Lihat performa', hint: 'Kunjungan, klik, dan konversi', icon: <BarChart3 className="size-4" /> },
];

export default function CreatorDashboard({
    range, stats, change, series, topProducts, balance, recentOrders, checklist, store,
}: Props) {
    const [copied, setCopied] = useState(false);

    const remaining = checklist.filter((item) => !item.done);
    const done = checklist.length - remaining.length;
    const newProductHref = `/dashboard/produk/create${store.products_count === 0 ? '?first=1' : ''}`;

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
            {/*
                The page opens on the store itself — its name, its address, and
                whether the public can reach it — because that is the fact a
                creator checks first. It replaces a dark "command center" panel
                whose greeting and blurred glows carried no information at all.
            */}
            <header className="mb-6 flex flex-col gap-4 border-b border-line pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="truncate text-[1.375rem] font-semibold tracking-[-.02em]">{store.name}</h1>
                        <Badge tone={store.is_published ? 'success' : 'warning'}>
                            {store.is_published ? 'Live' : 'Draft'}
                        </Badge>
                    </div>
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.8125rem] text-muted">
                        <a
                            href={store.public_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 font-medium text-fg hover:text-[var(--primary)]"
                        >
                            /{store.username}
                            <ArrowUpRight className="size-3.5" />
                        </a>
                        <span>{formatNumber(stats.views)} kunjungan</span>
                        <span>{store.products_count} produk</span>
                        <span>{store.blocks_count} bagian</span>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" onClick={copyLink}>
                        {copied ? <Check /> : <Copy />}{copied ? 'Tersalin' : 'Salin link'}
                    </Button>
                    <Select
                        defaultValue="30"
                        onChange={(e) => changeRange(Number(e.target.value))}
                        aria-label="Rentang tanggal"
                        className="h-9 w-auto text-[0.8125rem]"
                    >
                        {RANGES.map((r) => <option key={r.days} value={r.days}>{r.label}</option>)}
                    </Select>
                    <ButtonLink href={newProductHref} size="sm">
                        <Plus className="size-4" /> {store.products_count === 0 ? 'Produk pertama' : 'Produk'}
                    </ButtonLink>
                </div>
            </header>

            {/* Numbers first: seven figures in one grid, each weighted the same,
                so they can actually be compared against each other. */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Penjualan kotor"
                    value={formatIDR(stats.gross_revenue)}
                    change={change.gross_revenue}
                    hint="vs periode sebelumnya"
                    icon={<TrendingUp />}
                    tone="brand"
                />
                <StatCard label="Pendapatan bersih" value={formatIDR(stats.net_revenue)} change={change.net_revenue} icon={<Wallet />} />
                <StatCard label="Pesanan" value={formatNumber(stats.orders)} change={change.orders} icon={<ShoppingBag />} />
                <StatCard label="Pengunjung" value={formatNumber(stats.visitors)} change={change.visitors} icon={<Eye />} />
            </div>

            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                <StatCard label="Konversi" value={`${stats.conversion_rate}%`} hint="kunjungan → pesanan" />
                <StatCard label="Rata-rata order" value={formatIDR(stats.average_order_value)} />
                <StatCard label="Leads terkumpul" value={formatNumber(stats.leads)} change={change.leads} />
            </div>

            {/* Quick actions */}
            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {ACTIONS.map((action) => (
                    <Link
                        key={action.key}
                        href={action.href ?? newProductHref}
                        className="group flex items-start gap-3 rounded-[var(--radius-card)] border border-line bg-surface p-4 transition-colors hover:border-[var(--primary)]/35 hover:bg-surface-2"
                    >
                        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg border border-line bg-surface-2 text-muted transition-colors group-hover:border-[var(--primary)]/30 group-hover:text-[var(--primary)]">
                            {action.icon}
                        </span>
                        <span className="min-w-0">
                            <span className="block text-[0.8125rem] font-medium">
                                {action.key === 'produk' && store.products_count === 0 ? 'Buat produk pertama' : action.label}
                            </span>
                            <span className="mt-0.5 block text-xs leading-5 text-muted">{action.hint}</span>
                        </span>
                        <ArrowRight className="ml-auto mt-1 size-4 shrink-0 text-muted opacity-0 transition-all group-hover:translate-x-0.5 group-hover:opacity-100" />
                    </Link>
                ))}
            </div>

            {/* Setup checklist — only while there is something left to do. */}
            {remaining.length > 0 && (
                <Card className="mt-6">
                    <CardBody className="p-4 sm:p-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-[0.9375rem] font-semibold">Lengkapi toko kamu</p>
                                <p className="mt-0.5 text-[0.8125rem] text-muted">{done} dari {checklist.length} selesai</p>
                            </div>
                            <div className="w-28 shrink-0 sm:w-40">
                                <div className="h-1.5 overflow-hidden rounded-full bg-surface-2">
                                    <div
                                        className="h-full rounded-full bg-[var(--primary)] transition-[width] duration-500"
                                        style={{ width: `${Math.round((done / checklist.length) * 100)}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        <ul className="mt-4 grid gap-1 sm:grid-cols-2">
                            {checklist.map((item) => (
                                <li key={item.key}>
                                    <Link
                                        href={item.href}
                                        className="flex items-center gap-2.5 rounded-[var(--radius-field)] px-2 py-2 text-[0.8125rem] transition-colors hover:bg-surface-2"
                                    >
                                        <span
                                            className={
                                                item.done
                                                    ? 'grid size-4.5 shrink-0 place-items-center rounded-full bg-[var(--success)] text-white'
                                                    : 'size-4.5 shrink-0 rounded-full border border-[var(--border)]'
                                            }
                                        >
                                            {item.done && <Check className="size-2.5" />}
                                        </span>
                                        <span className={item.done ? 'text-muted' : 'font-medium'}>{item.label}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </CardBody>
                </Card>
            )}

            {balance.negative > 0 && (
                <Alert tone="danger" title={`Saldo minus ${formatIDR(balance.negative)}`} className="mt-6">
                    Penarikan ditahan sementara. Pendapatan berikutnya otomatis dipakai untuk memulihkan saldo akibat
                    refund atau penyesuaian.
                </Alert>
            )}

            {/* Wallet. One card, five figures, the withdrawable one given the
                emphasis — the rest are context for it, not rival headlines. */}
            <Card className="mt-6">
                <CardBody className="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,15rem)_1fr] lg:gap-10">
                    <div>
                        <p className="text-[0.8125rem] font-medium text-muted">Saldo siap dicairkan</p>
                        <p className="jy-num mt-2 text-3xl font-semibold leading-none">{formatIDR(balance.available)}</p>
                        <p className="mt-2 text-xs leading-5 text-muted">
                            Dana bersih yang sudah melewati masa tahan dan siap masuk rekeningmu.
                        </p>
                        <ButtonLink href="/dashboard/penarikan" size="sm" className="mt-4">
                            Tarik saldo <ArrowRight />
                        </ButtonLink>
                    </div>

                    <dl className="grid grid-cols-2 gap-x-6 gap-y-5 border-t border-line pt-5 lg:grid-cols-4 lg:border-l lg:border-t-0 lg:pl-10 lg:pt-0">
                        {[
                            ['Saldo tertahan', balance.pending, 'Cair setelah masa refund'],
                            ['Dana cadangan', balance.reserve, 'Proteksi risiko, dilepas otomatis'],
                            ['Sedang ditarik', balance.held, 'Dalam proses finance'],
                            ['Total ditarik', balance.withdrawn, 'Akumulasi pencairan'],
                        ].map(([label, value, hint]) => (
                            <div key={label as string}>
                                <dt className="text-xs font-medium text-muted">{label as string}</dt>
                                <dd className="jy-num mt-1.5 text-base font-semibold">{formatIDR(value as number)}</dd>
                                <p className="mt-1 text-xs leading-5 text-muted">{hint as string}</p>
                            </div>
                        ))}
                        <div className="col-span-2 lg:col-span-4">
                            <Link href="/dashboard/saldo" className="inline-flex items-center gap-1 text-[0.8125rem] font-medium text-[var(--primary)] hover:underline">
                                Lihat riwayat saldo <ArrowRight className="size-3.5" />
                            </Link>
                        </div>
                    </dl>
                </CardBody>
            </Card>

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
                                    <ButtonLink href="/dashboard/produk/create" size="sm">
                                        Tambah produk
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
                        className="flex items-center gap-1 text-[0.8125rem] font-medium text-[var(--primary)] hover:underline"
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
                                        className="-mx-2 flex items-center justify-between gap-3 rounded-[var(--radius-field)] px-2 py-3 transition-colors hover:bg-surface-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-[0.8125rem] font-medium">{order.customer_name}</p>
                                            <p className="mt-0.5 text-xs text-muted">
                                                {order.number} · {order.items_count} item · {order.created_at}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <p className="jy-num text-[0.8125rem] font-semibold">{formatIDR(order.grand_total)}</p>
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
