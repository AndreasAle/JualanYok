import { useForm } from '@inertiajs/react';
import { CheckCircle2, Package, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatusBadge } from '@/components/shared';
import {
    Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Textarea,
} from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function OrderShow({ order }: { order: any }) {
    const [refundOpen, setRefundOpen] = useState(false);

    const shipForm = useForm({ tracking_number: order.tracking_number ?? '', courier: order.shipping_method ?? '' });
    const refundForm = useForm({ amount: order.refundable, reason: '' });

    const ship = (e: FormEvent) => {
        e.preventDefault();
        shipForm.post(`/dashboard/pesanan/${order.number}/kirim`, { preserveScroll: true });
    };

    const requestRefund = (e: FormEvent) => {
        e.preventDefault();
        refundForm.post(`/dashboard/pesanan/${order.number}/refund`, {
            preserveScroll: true,
            onSuccess: () => setRefundOpen(false),
        });
    };

    return (
        <DashboardLayout title={`Pesanan ${order.number}`} area="creator">
            <PageHeader
                title={order.number}
                description={`Dibuat ${formatDate(order.created_at, true)}`}
                breadcrumbs={[{ label: 'Pesanan', href: '/dashboard/pesanan' }, { label: order.number }]}
                actions={
                    <>
                        <StatusBadge status={order.status} label={order.status_label} />
                        <StatusBadge status={order.fulfillment_status} label={order.fulfillment_label} />
                    </>
                }
            />

            <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Item dibeli</CardTitle>
                        </CardHeader>
                        <CardBody>
                            <ul className="divide-y divide-[var(--border)]">
                                {order.items.map((item: any, i: number) => (
                                    <li key={i} className="flex items-start justify-between gap-3 py-3 first:pt-0">
                                        <div className="min-w-0">
                                            <p className="font-semibold">{item.name}</p>
                                            {item.variant_name && (
                                                <p className="text-xs text-muted">{item.variant_name}</p>
                                            )}
                                            <p className="text-xs text-muted">
                                                {item.quantity} × {formatIDR(item.unit_price)}
                                                {item.commission_amount > 0 &&
                                                    ` · komisi ${formatIDR(item.commission_amount)}`}
                                            </p>
                                        </div>
                                        <span className="shrink-0 font-bold tabular-nums">{formatIDR(item.total)}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 space-y-1.5 border-t border-line pt-4 text-sm">
                                <Row label="Subtotal" value={formatIDR(order.subtotal)} />
                                {order.discount_total > 0 && (
                                    <Row
                                        label={`Diskon ${order.coupon_code ?? ''}`}
                                        value={`−${formatIDR(order.discount_total)}`}
                                    />
                                )}
                                {order.shipping_total > 0 && (
                                    <Row label="Ongkir" value={formatIDR(order.shipping_total)} />
                                )}
                                {order.payment_fee > 0 && (
                                    <Row label="Biaya pembayaran" value={formatIDR(order.payment_fee)} />
                                )}
                                <Row label="Biaya platform" value={`−${formatIDR(order.platform_fee)}`} />
                                {order.affiliate_commission > 0 && (
                                    <Row
                                        label="Komisi affiliate"
                                        value={`−${formatIDR(order.affiliate_commission)}`}
                                    />
                                )}

                                <div className="flex justify-between border-t border-line pt-2 font-bold">
                                    <span>Dibayar pembeli</span>
                                    <span className="tabular-nums">{formatIDR(order.grand_total)}</span>
                                </div>
                                <div className="flex justify-between font-bold text-[var(--success)]">
                                    <span>Masuk ke saldo kamu</span>
                                    <span className="tabular-nums">{formatIDR(order.seller_net)}</span>
                                </div>

                                {order.refunded_total > 0 && (
                                    <div className="flex justify-between text-[var(--danger)]">
                                        <span>Sudah direfund</span>
                                        <span className="tabular-nums">−{formatIDR(order.refunded_total)}</span>
                                    </div>
                                )}
                            </div>
                        </CardBody>
                    </Card>

                    {/* Fulfilment */}
                    {order.requires_shipping && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Pengiriman</CardTitle>
                            </CardHeader>
                            <CardBody>
                                {order.shipping_address ? (
                                    <div className="mb-4 rounded-[var(--radius-field)] bg-surface-2 p-4 text-sm">
                                        <p className="font-semibold">{order.shipping_address.recipient}</p>
                                        <p className="text-muted">{order.shipping_address.phone}</p>
                                        <p className="mt-1 text-muted">
                                            {order.shipping_address.address_line}, {order.shipping_address.city},{' '}
                                            {order.shipping_address.province} {order.shipping_address.postal_code}
                                        </p>
                                    </div>
                                ) : (
                                    <Alert tone="warning">Pembeli belum mengisi alamat pengiriman.</Alert>
                                )}

                                <form onSubmit={ship} className="mt-4 space-y-3">
                                    <Field label="Kurir" htmlFor="courier">
                                        <Input
                                            id="courier"
                                            value={shipForm.data.courier}
                                            onChange={(e) => shipForm.setData('courier', e.target.value)}
                                            placeholder="JNE, SiCepat, ..."
                                        />
                                    </Field>

                                    <Field
                                        label="Nomor resi"
                                        required
                                        error={shipForm.errors.tracking_number}
                                        htmlFor="tracking"
                                    >
                                        <Input
                                            id="tracking"
                                            value={shipForm.data.tracking_number}
                                            onChange={(e) => shipForm.setData('tracking_number', e.target.value)}
                                            invalid={!!shipForm.errors.tracking_number}
                                            required
                                        />
                                    </Field>

                                    <div className="flex flex-wrap gap-2">
                                        <Button type="submit" variant="gradient" loading={shipForm.processing}>
                                            <Truck className="size-4" />
                                            Tandai Dikirim
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                shipForm.post(`/dashboard/pesanan/${order.number}/selesai`, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Tandai Selesai
                                        </Button>
                                    </div>
                                </form>
                            </CardBody>
                        </Card>
                    )}

                    {/* Payments */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Pembayaran</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {order.payments.length === 0 ? (
                                <p className="text-sm text-muted">Belum ada percobaan pembayaran.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {order.payments.map((payment: any, i: number) => (
                                        <li
                                            key={i}
                                            className="flex items-center justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3 text-sm"
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-semibold">
                                                    {payment.provider} · {payment.method}
                                                    {payment.channel ? ` (${payment.channel})` : ''}
                                                </span>
                                                <span className="block truncate font-mono text-xs text-muted">
                                                    {payment.reference}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-right">
                                                <span className="block font-bold">{formatIDR(payment.amount)}</span>
                                                <StatusBadge status={payment.status} label={payment.status_label} />
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardBody>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Pembeli</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-1 text-sm">
                            <p className="font-semibold">{order.customer_name}</p>
                            <p className="text-muted">{order.customer_email}</p>
                            {order.customer_phone && <p className="text-muted">{order.customer_phone}</p>}

                            {order.customer_note && (
                                <div className="mt-3 rounded-[var(--radius-field)] bg-surface-2 p-3">
                                    <p className="text-xs font-semibold text-muted">Catatan pembeli</p>
                                    <p className="mt-1">{order.customer_note}</p>
                                </div>
                            )}

                            {Object.keys(order.custom_fields ?? {}).length > 0 && (
                                <div className="mt-3 space-y-1">
                                    {Object.entries(order.custom_fields).map(([key, value]) => (
                                        <p key={key}>
                                            <span className="text-muted">{key}:</span> {String(value)}
                                        </p>
                                    ))}
                                </div>
                            )}
                        </CardBody>
                    </Card>

                    {order.affiliate_code && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Affiliate</CardTitle>
                            </CardHeader>
                            <CardBody className="text-sm">
                                <p>
                                    Kode <span className="font-mono font-bold">{order.affiliate_code}</span>
                                </p>
                                <p className="mt-1 text-muted">
                                    Komisi {formatIDR(order.affiliate_commission)} ditahan sampai masa refund lewat.
                                </p>
                            </CardBody>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Refund</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {order.refunds.length > 0 && (
                                <ul className="mb-3 space-y-2">
                                    {order.refunds.map((refund: any) => (
                                        <li
                                            key={refund.id}
                                            className="rounded-[var(--radius-field)] bg-surface-2 p-3 text-sm"
                                        >
                                            <div className="flex justify-between">
                                                <span className="font-semibold">{formatIDR(refund.amount)}</span>
                                                <StatusBadge status={refund.status} label={refund.status} />
                                            </div>
                                            {refund.reason && <p className="mt-1 text-xs text-muted">{refund.reason}</p>}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {order.refundable > 0 ? (
                                refundOpen ? (
                                    <form onSubmit={requestRefund} className="space-y-3">
                                        <Field
                                            label="Nominal refund"
                                            required
                                            error={refundForm.errors.amount}
                                            hint={`Maksimal ${formatIDR(order.refundable)}`}
                                            htmlFor="refund-amount"
                                        >
                                            <Input
                                                id="refund-amount"
                                                type="number"
                                                min={1}
                                                max={order.refundable}
                                                value={refundForm.data.amount}
                                                onChange={(e) => refundForm.setData('amount', Number(e.target.value))}
                                            />
                                        </Field>

                                        <Field label="Alasan" required error={refundForm.errors.reason} htmlFor="refund-reason">
                                            <Textarea
                                                id="refund-reason"
                                                rows={3}
                                                value={refundForm.data.reason}
                                                onChange={(e) => refundForm.setData('reason', e.target.value)}
                                                required
                                            />
                                        </Field>

                                        <div className="flex gap-2">
                                            <Button type="submit" variant="danger" loading={refundForm.processing}>
                                                Ajukan Refund
                                            </Button>
                                            <Button type="button" variant="ghost" onClick={() => setRefundOpen(false)}>
                                                Batal
                                            </Button>
                                        </div>
                                    </form>
                                ) : (
                                    <Button variant="outline" block onClick={() => setRefundOpen(true)}>
                                        Ajukan Refund
                                    </Button>
                                )
                            ) : (
                                <p className="text-sm text-muted">Pesanan ini nggak bisa direfund lagi.</p>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}
