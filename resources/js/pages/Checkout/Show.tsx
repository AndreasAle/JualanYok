import { Head, Link, useForm } from '@inertiajs/react';
import { CreditCard, Landmark, QrCode, ShieldCheck, Smartphone, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Alert, Badge, Button, Card } from '@/components/ui';
import { Logo } from '@/layouts/MarketingLayout';
import { cn, formatIDR } from '@/lib/utils';

interface Method {
    provider: string;
    provider_name: string;
    method: string;
    channel: string;
    label: string;
    fee_percent: number;
    fee_fixed: number;
}

interface Order {
    number: string;
    status: string;
    status_label: string;
    is_payable: boolean;
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    tax_total: number;
    payment_fee: number;
    grand_total: number;
    coupon_code: string | null;
    expires_at: string | null;
    items: { name: string; variant_name: string | null; quantity: number; unit_price: number; total: number }[];
    store: { name: string; username: string; avatar_url: string | null };
}

const ICONS: Record<string, React.ReactNode> = {
    qris: <QrCode className="size-5" />,
    va: <Landmark className="size-5" />,
    ewallet: <Smartphone className="size-5" />,
    bank_transfer: <Landmark className="size-5" />,
    card: <CreditCard className="size-5" />,
};

export default function CheckoutShow({
    order,
    methods,
    payment,
}: {
    order: Order;
    methods: Method[];
    payment: { is_open: boolean } | null;
}) {
    const [selected, setSelected] = useState<Method | null>(methods[0] ?? null);

    const { post, transform, processing } = useForm({});

    const fee = selected
        ? Math.round((order.grand_total * selected.fee_percent) / 100 + selected.fee_fixed)
        : 0;

    const pay = () => {
        if (!selected) return;

        transform(() => ({
            provider: selected.provider,
            method: selected.method,
            channel: selected.channel,
        }));

        post(`/checkout/${order.number}/pay`);
    };

    const grouped = methods.reduce<Record<string, Method[]>>((acc, method) => {
        (acc[method.method] ??= []).push(method);
        return acc;
    }, {});

    const groupLabels: Record<string, string> = {
        qris: 'QRIS',
        va: 'Virtual Account',
        ewallet: 'E-Wallet',
        bank_transfer: 'Transfer Bank',
        card: 'Kartu Kredit/Debit',
    };

    return (
        <div className="min-h-screen bg-subtle">
            <Head title={`Bayar ${order.number}`} />

            <header className="border-b border-line bg-app">
                <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-4 sm:px-6">
                    <Logo />
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-muted">
                        <ShieldCheck className="size-4 text-[var(--success)]" />
                        Checkout aman
                    </span>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <div className="mb-6 grid grid-cols-3 gap-2" aria-label="Progres pembayaran"><div><span className="block h-1.5 rounded-full bg-emerald-500" /><p className="mt-1.5 text-[10px] font-bold text-muted">Data pembeli</p></div><div><span className="block h-1.5 rounded-full bg-violet-600" /><p className="mt-1.5 text-[10px] font-extrabold text-violet-600">Pembayaran</p></div><div><span className="block h-1.5 rounded-full bg-line" /><p className="mt-1.5 text-[10px] font-bold text-muted">Selesai</p></div></div>
                <div className="mb-6">
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                        Pesanan {order.number}
                    </p>
                    <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Pilih metode pembayaran</h1>
                    <p className="mt-1 text-sm text-muted">
                        Beli dari <span className="font-semibold">{order.store.name}</span>
                    </p>
                </div>

                {!order.is_payable && (
                    <div className="mb-5">
                        <Alert tone="warning" title="Pesanan ini sudah tidak bisa dibayar">
                            Statusnya sekarang: {order.status_label}.{' '}
                            <Link href={`/${order.store.username}`} className="font-bold underline">
                                Balik ke toko
                            </Link>
                        </Alert>
                    </div>
                )}

                {payment?.is_open && (
                    <div className="mb-5">
                        <Alert tone="info">
                            Kamu sudah punya tagihan aktif untuk pesanan ini.{' '}
                            <Link href={`/checkout/${order.number}/status`} className="font-bold underline">
                                Lihat instruksi pembayaran
                            </Link>
                        </Alert>
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                    {/* Methods */}
                    <div className="space-y-4">
                        {Object.entries(grouped).map(([key, group]) => (
                            <Card key={key} className="p-5">
                                <p className="flex items-center gap-2 text-sm font-bold">
                                    {ICONS[key] ?? <Wallet className="size-5" />}
                                    {groupLabels[key] ?? key}
                                </p>

                                <div className="mt-3 space-y-2">
                                    {group.map((method) => {
                                        const active =
                                            selected?.provider === method.provider &&
                                            selected?.method === method.method &&
                                            selected?.channel === method.channel;

                                        const methodFee = Math.round(
                                            (order.grand_total * method.fee_percent) / 100 + method.fee_fixed,
                                        );

                                        return (
                                            <button
                                                key={`${method.provider}-${method.channel}`}
                                                type="button"
                                                onClick={() => setSelected(method)}
                                                disabled={!order.is_payable}
                                                className={cn(
                                                    'flex w-full items-center justify-between gap-3 rounded-[var(--radius-field)] border p-3.5 text-left transition-all disabled:opacity-50',
                                                    active
                                                        ? 'border-[var(--primary)] bg-brand-50 ring-1 ring-[var(--primary)] dark:bg-brand-900/20'
                                                        : 'border-line hover:bg-surface-2',
                                                )}
                                                aria-pressed={active}
                                            >
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-semibold">{method.label}</span>
                                                    <span className="block text-xs text-muted">
                                                        {methodFee > 0 ? `Biaya ${formatIDR(methodFee)}` : 'Tanpa biaya tambahan'}
                                                    </span>
                                                </span>

                                                <span
                                                    className={cn(
                                                        'size-4 shrink-0 rounded-full border-2',
                                                        active
                                                            ? 'border-[var(--primary)] bg-[var(--primary)] ring-2 ring-inset ring-white'
                                                            : 'border-line',
                                                    )}
                                                />
                                            </button>
                                        );
                                    })}
                                </div>
                            </Card>
                        ))}
                    </div>

                    {/* Summary */}
                    <div className="lg:sticky lg:top-6 lg:self-start">
                        <Card className="p-5">
                            <p className="font-bold">Ringkasan</p>

                            <ul className="mt-3 space-y-2.5">
                                {order.items.map((item, i) => (
                                    <li key={i} className="flex justify-between gap-3 text-sm">
                                        <span className="min-w-0">
                                            <span className="block font-medium">{item.name}</span>
                                            {item.variant_name && (
                                                <span className="block text-xs text-muted">{item.variant_name}</span>
                                            )}
                                            <span className="block text-xs text-muted">×{item.quantity}</span>
                                        </span>
                                        <span className="shrink-0 tabular-nums">{formatIDR(item.total)}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 space-y-1.5 border-t border-line pt-4 text-sm">
                                <Row label="Subtotal" value={formatIDR(order.subtotal)} />
                                {order.discount_total > 0 && (
                                    <Row
                                        label={
                                            <>
                                                Diskon {order.coupon_code && <Badge tone="success">{order.coupon_code}</Badge>}
                                            </>
                                        }
                                        value={`−${formatIDR(order.discount_total)}`}
                                        tone="success"
                                    />
                                )}
                                {order.shipping_total > 0 && (
                                    <Row label="Ongkir" value={formatIDR(order.shipping_total)} />
                                )}
                                {order.tax_total > 0 && <Row label="Pajak" value={formatIDR(order.tax_total)} />}
                                {fee > 0 && <Row label="Biaya pembayaran" value={formatIDR(fee)} />}
                            </div>

                            <div className="mt-4 flex items-baseline justify-between border-t border-line pt-4">
                                <span className="font-bold">Total bayar</span>
                                <span className="text-xl font-extrabold tabular-nums">
                                    {formatIDR(order.grand_total + fee - order.payment_fee)}
                                </span>
                            </div>

                            <Button
                                variant="gradient"
                                block
                                size="lg"
                                className="mt-5"
                                loading={processing}
                                disabled={!selected || !order.is_payable}
                                onClick={pay}
                            >
                                Bayar Sekarang
                            </Button>

                            <p className="mt-3 text-center text-xs text-muted">
                                Dengan lanjut, kamu setuju dengan syarat pembelian yang berlaku.
                            </p>
                        </Card>
                    </div>
                </div>
            </main>
        </div>
    );
}

function Row({
    label,
    value,
    tone,
}: {
    label: React.ReactNode;
    value: string;
    tone?: 'success';
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted">{label}</span>
            <span className={cn('tabular-nums', tone === 'success' && 'text-[var(--success)] font-semibold')}>
                {value}
            </span>
        </div>
    );
}
