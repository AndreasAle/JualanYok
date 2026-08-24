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

const PAYMENT_BRANDS: Record<string, { name: string; logo?: string; className: string }> = {
    mpm: {
        name: 'QRIS',
        className: 'text-slate-950',
    },
    static: {
        name: 'QRIS',
        className: 'text-slate-950',
    },
    bca: {
        name: 'BCA',
        logo: '/images/payments/bca.png',
        className: 'text-[#0866a8]',
    },
    bni: {
        name: 'BNI',
        logo: '/images/payments/bni.png',
        className: 'text-[#f15a23]',
    },
    bri: {
        name: 'BRI',
        logo: '/images/payments/bri.png',
        className: 'text-[#0756a3]',
    },
    mandiri: {
        name: 'mandiri',
        logo: '/images/payments/mandiri.png',
        className: 'text-[#153d75]',
    },
    permata: {
        name: 'PermataBank',
        logo: '/images/payments/permata.png',
        className: 'text-[#149b64]',
    },
    dana: {
        name: 'DANA',
        className: 'text-[#108ee9]',
    },
    shopeepay: {
        name: 'ShopeePay',
        className: 'text-[#ee4d2d]',
    },
    manual: { name: 'Transfer bank', className: 'text-slate-800' },
    qris: { name: 'QRIS', className: 'text-slate-950' },
    gopay: { name: 'GoPay', className: 'text-[#00aed6]' },
    ovo: { name: 'OVO', className: 'text-[#4c3494]' },
    credit_card: { name: 'VISA / Mastercard', className: 'text-slate-800' },
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
                                                    'group flex w-full items-center justify-between gap-3 rounded-[var(--radius-field)] border p-3 text-left transition-all disabled:opacity-50 sm:p-3.5',
                                                    active
                                                        ? 'border-[var(--primary)] bg-brand-50 ring-1 ring-[var(--primary)] dark:bg-brand-900/20'
                                                        : 'border-line hover:bg-surface-2',
                                                )}
                                                aria-pressed={active}
                                            >
                                                <span className="flex min-w-0 items-center gap-3">
                                                    <PaymentBrand method={method} />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-semibold">
                                                            {method.label}
                                                        </span>
                                                        <span className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] text-muted sm:text-xs">
                                                            <span>
                                                                {methodFee > 0
                                                                    ? `Biaya pembeli ${formatIDR(methodFee)}`
                                                                    : 'Tanpa biaya tambahan untuk pembeli'}
                                                            </span>
                                                            <span aria-hidden="true">&bull;</span>
                                                            <span className="font-semibold text-foreground">
                                                                {method.provider === 'ipaymu'
                                                                    ? 'Otomatis via iPaymu'
                                                                    : method.provider_name}
                                                            </span>
                                                        </span>
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

function PaymentBrand({ method }: { method: Method }) {
    const brand = PAYMENT_BRANDS[method.channel] ?? PAYMENT_BRANDS[method.method] ?? {
        name: method.provider_name,
        className: 'text-slate-800',
    };

    return (
        <span className="relative flex h-10 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-white px-2 shadow-sm sm:h-11 sm:w-[4.5rem]">
            <span className={cn('text-center text-[10px] font-black leading-none tracking-tight', brand.className)}>
                {brand.name}
            </span>
            {brand.logo && (
                <img
                    src={brand.logo}
                    alt={`Logo ${brand.name}`}
                    loading="lazy"
                    className="absolute inset-0 z-10 size-full bg-white object-contain p-1.5"
                    onError={(event) => {
                        event.currentTarget.style.display = 'none';
                    }}
                />
            )}
        </span>
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
