import { Link } from '@inertiajs/react';
import { Check, Copy, Handshake, MousePointerClick, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatCard, StatusBadge } from '@/components/shared';
import { Button, ButtonLink, Card, CardBody, CardHeader, CardTitle, EmptyState } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

export default function AffiliateDashboard({
    summary,
    wallet,
    links,
    recentCommissions,
}: {
    summary: {
        clicks: number;
        conversions: number;
        revenue: number;
        pending: number;
        approved: number;
        paid: number;
        conversion_rate: number;
    };
    wallet: { pending: number; available: number; held: number };
    links: any[];
    recentCommissions: any[];
}) {
    const [copied, setCopied] = useState<string | null>(null);

    const copy = async (url: string) => {
        await navigator.clipboard.writeText(url);
        setCopied(url);
        setTimeout(() => setCopied(null), 2000);
    };

    return (
        <DashboardLayout title="Affiliate" area="affiliate">
            <PageHeader
                title="Dashboard Affiliate"
                description="Pantau klik, konversi, dan komisi kamu."
                actions={
                    <ButtonLink href="/affiliate/marketplace" variant="gradient">
                        Cari Produk
                    </ButtonLink>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Komisi tersedia"
                    value={formatIDR(wallet.available)}
                    hint="siap ditarik"
                    icon={<Wallet className="size-4.5" />}
                    tone="brand"
                />
                <StatCard label="Komisi tertahan" value={formatIDR(wallet.pending)} hint="menunggu masa refund" />
                <StatCard
                    label="Total klik"
                    value={formatNumber(summary.clicks)}
                    icon={<MousePointerClick className="size-4.5" />}
                />
                <StatCard
                    label="Konversi"
                    value={`${formatNumber(summary.conversions)} (${summary.conversion_rate}%)`}
                    icon={<TrendingUp className="size-4.5" />}
                />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1.3fr_1fr]">
                <Card>
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Link kamu</CardTitle>
                        <Link
                            href="/affiliate/link"
                            className="text-sm font-semibold text-[var(--primary)] hover:underline"
                        >
                            Kelola semua
                        </Link>
                    </CardHeader>
                    <CardBody>
                        {links.length === 0 ? (
                            <EmptyState
                                icon={<Handshake className="size-6" />}
                                title="Belum punya link affiliate"
                                description="Pilih produk dari marketplace, nanti link kamu dibuatkan otomatis."
                                action={
                                    <ButtonLink href="/affiliate/marketplace" variant="gradient" size="sm">
                                        Buka Marketplace
                                    </ButtonLink>
                                }
                            />
                        ) : (
                            <ul className="space-y-3">
                                {links.map((link) => (
                                    <li key={link.id} className="rounded-[var(--radius-field)] bg-surface-2 p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-bold">
                                                    {link.product ?? link.store}
                                                </p>
                                                <p className="truncate text-xs text-muted">
                                                    {link.store}
                                                    {link.campaign && ` · ${link.campaign}`}
                                                </p>
                                            </div>

                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => copy(link.url)}
                                                className="shrink-0"
                                            >
                                                {copied === link.url ? (
                                                    <Check className="size-3.5" />
                                                ) : (
                                                    <Copy className="size-3.5" />
                                                )}
                                                {copied === link.url ? 'Tersalin' : 'Salin'}
                                            </Button>
                                        </div>

                                        <p className="mt-2 truncate font-mono text-[11px] text-muted">{link.url}</p>

                                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                            <span>{formatNumber(link.clicks)} klik</span>
                                            <span>{formatNumber(link.conversions)} konversi</span>
                                            <span>{link.conversion_rate}% CR</span>
                                            <span className="font-semibold text-fg">{formatIDR(link.revenue)}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader className="flex items-center justify-between">
                        <CardTitle>Komisi terbaru</CardTitle>
                        <Link
                            href="/affiliate/komisi"
                            className="text-sm font-semibold text-[var(--primary)] hover:underline"
                        >
                            Lihat semua
                        </Link>
                    </CardHeader>
                    <CardBody>
                        {recentCommissions.length === 0 ? (
                            <EmptyState
                                title="Belum ada komisi"
                                description="Komisi muncul setelah ada yang beli lewat link kamu."
                            />
                        ) : (
                            <ul className="divide-y divide-[var(--border)]">
                                {recentCommissions.map((commission) => (
                                    <li key={commission.id} className="flex items-start justify-between gap-3 py-3">
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-semibold">
                                                {commission.store}
                                            </span>
                                            <span className="block font-mono text-xs text-muted">
                                                {commission.order_number}
                                            </span>
                                            <span className="block text-xs text-muted">{commission.created_at}</span>
                                        </span>

                                        <span className="shrink-0 text-right">
                                            <span className="block font-bold text-[var(--success)]">
                                                +{formatIDR(commission.amount)}
                                            </span>
                                            <StatusBadge status={commission.status} label={commission.status_label} />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>
            </div>

            <Card className="mt-4 p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="font-bold">Mau cairkan komisi?</p>
                        <p className="text-sm text-muted">
                            Komisi yang sudah lewat masa refund otomatis masuk ke saldo tersedia.
                        </p>
                    </div>
                    <ButtonLink href="/dashboard/penarikan" variant="gradient" className="shrink-0">
                        <Wallet className="size-4" />
                        Tarik Saldo
                    </ButtonLink>
                </div>
            </Card>
        </DashboardLayout>
    );
}
