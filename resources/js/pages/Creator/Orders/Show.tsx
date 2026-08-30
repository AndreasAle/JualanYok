import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Package, RefreshCw, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatusBadge } from '@/components/shared';
import {
    Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Select, Textarea,
} from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function OrderShow({ order }: { order: any }) {
    const [refundOpen, setRefundOpen] = useState(false);
    const gatewayFee = order.settlement_version >= 2
        ? order.gateway_fee_actual
        : order.gateway_fee_estimated;
    const pendingCredit = Math.max(0, order.seller_net - order.reserve_amount - order.debt_offset);
    const providerPayment = order.payments.find((payment: any) => payment.status === 'PAID') ?? order.payments[0];

    const shipForm = useForm({ tracking_number: order.tracking_number ?? '', courier: order.shipping_method ?? '' });
    const refundForm = useForm({ amount: order.refundable, reason: '' });
    const disputeForm = useForm({ response: order.open_dispute?.seller_response ?? '' });
    const trackingForm = useForm({ stage: 'processing', description: '' });

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
                                <p className="pb-1 text-xs font-extrabold uppercase tracking-[0.12em] text-muted">
                                    Rincian pembayaran pembeli
                                </p>
                                <Row label="Subtotal produk" value={formatIDR(order.subtotal)} />
                                {order.discount_total > 0 && (
                                    <Row
                                        label={`Diskon ${order.coupon_code ?? ''}`}
                                        value={`−${formatIDR(order.discount_total)}`}
                                    />
                                )}
                                {order.shipping_total > 0 && (
                                    <Row
                                        label={order.shipping_provider === 'biteship' ? 'Ongkir · diteruskan ke kurir' : 'Ongkir'}
                                        value={formatIDR(order.shipping_total)}
                                    />
                                )}
                                {order.tax_total > 0 && <Row label="Pajak" value={formatIDR(order.tax_total)} />}

                                <div className="flex justify-between border-t border-line pt-2 font-bold">
                                    <span>Dibayar pembeli</span>
                                    <span className="tabular-nums">{formatIDR(order.grand_total)}</span>
                                </div>

                                <p className="pb-1 pt-4 text-xs font-extrabold uppercase tracking-[0.12em] text-muted">
                                    Perhitungan pendapatanmu
                                </p>
                                <Row label="Nilai produk setelah diskon" value={formatIDR(order.commission_base)} />
                                <Row
                                    label={`Biaya platform · ${Number(order.platform_fee_rate ?? 0).toLocaleString('id-ID')}%`}
                                    value={`−${formatIDR(order.platform_fee)}`}
                                />
                                {gatewayFee > 0 && (
                                    <Row
                                        label={`Biaya gateway${providerPayment?.method ? ` · ${providerPayment.method}` : ''}${order.settlement_version < 2 ? ' · estimasi' : ''}`}
                                        value={order.gateway_fee_bearer === 'PLATFORM' ? `Ditanggung JualanYok · ${formatIDR(gatewayFee)}` : `−${formatIDR(gatewayFee)}`}
                                    />
                                )}
                                {order.affiliate_commission > 0 && (
                                    <Row
                                        label="Komisi affiliate"
                                        value={`−${formatIDR(order.affiliate_commission)}`}
                                    />
                                )}

                                <div className="flex justify-between border-t border-line pt-2 font-bold">
                                    <span>Hak bersih penjual</span>
                                    <span className="tabular-nums">{formatIDR(order.seller_net)}</span>
                                </div>
                                {order.reserve_amount > 0 && (
                                    <Row
                                        label={`Dana cadangan${order.reserve_rate > 0 ? ` · ${Number(order.reserve_rate).toLocaleString('id-ID')}%` : ''}`}
                                        value={`−${formatIDR(order.reserve_amount)}`}
                                    />
                                )}
                                {order.debt_offset > 0 && (
                                    <Row label="Pemulihan saldo minus" value={`−${formatIDR(order.debt_offset)}`} />
                                )}
                                <div className="flex justify-between border-t border-line pt-2 font-extrabold text-[var(--success)]">
                                    <span>Masuk ke saldo tertahan</span>
                                    <span className="tabular-nums">{formatIDR(pendingCredit)}</span>
                                </div>

                                {order.refunded_total > 0 && (
                                    <div className="flex justify-between text-[var(--danger)]">
                                        <span>Sudah direfund</span>
                                        <span className="tabular-nums">−{formatIDR(order.refunded_total)}</span>
                                    </div>
                                )}
                            </div>

                            {(order.funds_release_at || order.reserve_release_at || order.reserve_amount > 0) && (
                                <Alert tone="info" title="Jadwal pencairan" className="mt-4">
                                    <div className="space-y-1 text-sm">
                                        <p>
                                            Saldo tertahan dilepas {order.funds_release_at ? formatDate(order.funds_release_at, true) : 'setelah pesanan selesai'}.
                                        </p>
                                        {order.reserve_amount > 0 && (
                                            <p>
                                                Dana cadangan {formatIDR(order.reserve_amount)} dilepas {order.reserve_release_at ? formatDate(order.reserve_release_at, true) : 'setelah masa perlindungan berakhir'} selama tidak ada refund atau komplain.
                                            </p>
                                        )}
                                    </div>
                                </Alert>
                            )}
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
                                        <p className="font-semibold">{order.customer_name}</p>
                                        <p className="text-muted">{order.customer_phone}</p>
                                        <p className="mt-1 text-muted">
                                            {order.shipping_address.address_line}, {order.shipping_address.city},{' '}
                                            {order.shipping_address.province} {order.shipping_address.postal_code}
                                        </p>
                                    </div>
                                ) : (
                                    <Alert tone="warning">Pembeli belum mengisi alamat pengiriman.</Alert>
                                )}

                                <div className="mb-4 rounded-2xl border border-violet-100 bg-violet-50/60 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="font-extrabold">Update proses untuk pembeli</p><p className="mt-1 text-xs leading-5 text-muted">Tahap persiapan diisi oleh toko. Setelah paket diambil, status Biteship diperbarui otomatis.</p></div>{order.public_tracking_url && <a href={order.public_tracking_url} target="_blank" rel="noreferrer" className="text-xs font-extrabold text-violet-700">Buka tracking pembeli ↗</a>}</div>
                                    <form className="mt-4 grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)_auto]" onSubmit={(event) => { event.preventDefault(); trackingForm.patch(`/dashboard/pesanan/${order.number}/status-pelacakan`, { preserveScroll: true, onSuccess: () => trackingForm.setData('description', '') }); }}>
                                        <Select value={trackingForm.data.stage} onChange={(event) => trackingForm.setData('stage', event.target.value)}><option value="processing">Sedang diproses</option><option value="packed">Sudah dikemas</option><option value="ready_for_pickup">Siap diserahkan ke kurir</option></Select>
                                        <Input value={trackingForm.data.description} onChange={(event) => trackingForm.setData('description', event.target.value)} placeholder="Catatan opsional untuk pembeli" />
                                        <Button type="submit" loading={trackingForm.processing}>Perbarui</Button>
                                    </form>
                                    {trackingForm.errors.stage && <p className="mt-2 text-xs font-semibold text-red-600">{trackingForm.errors.stage}</p>}
                                    {order.tracking?.timeline?.length > 0 && <ol className="mt-4 space-y-2 border-t border-violet-100 pt-4">{[...order.tracking.timeline].reverse().slice(0, 4).map((event: any, index: number) => <li key={`${event.stage}-${event.occurred_at}-${index}`} className="flex items-start gap-2"><span className="mt-1.5 size-2 shrink-0 rounded-full bg-violet-500" /><div><p className="text-xs font-extrabold">{event.title}</p><p className="text-[10px] text-muted">{event.description ? `${event.description} · ` : ''}{formatDate(event.occurred_at, true)}</p></div></li>)}</ol>}
                                    <div className="mt-4 border-t border-violet-100 pt-4"><p className="text-[10px] font-black uppercase tracking-wider text-muted">ID pembelian</p><code className="mt-1 block text-xs font-black">{order.tracking_code}</code></div>
                                </div>

                                {!order.shipment && order.payment_status === 'PAID' && (
                                    <Button type="button" variant="gradient" block onClick={() => shipForm.post(`/dashboard/pesanan/${order.number}/pesan-kurir`, { preserveScroll: true })} loading={shipForm.processing}>
                                        <Truck className="size-4" /> {order.shipping_provider === 'biteship' ? 'Pesan kurir & jadwalkan pickup' : 'Siapkan pengiriman'}
                                    </Button>
                                )}

                                {order.shipment && (
                                    <div className="mt-4 space-y-3 rounded-2xl border border-line bg-surface-2 p-4">
                                        <div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-violet-600">{order.shipment.provider}</p><p className="font-extrabold">{order.shipping_courier || 'Pengiriman penjual'} · {order.shipping_service || 'Reguler'}</p><p className="text-xs text-muted">{order.shipment.status_label}{order.shipment.waybill_id ? ` · Resi ${order.shipment.waybill_id}` : ''}</p></div><Button type="button" variant="outline" size="sm" onClick={() => shipForm.post(`/dashboard/pesanan/${order.number}/sinkron-kurir`, { preserveScroll: true })}><RefreshCw className="size-3.5" /> Sinkron</Button></div>
                                        {order.shipment.last_error && <Alert tone="danger" title="Kurir belum berhasil dipesan">{order.shipment.last_error}</Alert>}
                                        {order.shipment.events?.length > 0 && <ol className="space-y-3 border-l border-violet-200 pl-4">{order.shipment.events.map((event: any, index: number) => <li key={index}><p className="text-sm font-bold">{event.description}</p><p className="text-xs text-muted">{event.location ? `${event.location} · ` : ''}{formatDate(event.event_at, true)}</p></li>)}</ol>}
                                    </div>
                                )}

                                {(order.shipping_provider !== 'biteship' || order.shipment?.provider === 'manual') && <form onSubmit={ship} className="mt-4 space-y-3">
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
                                </form>}
                            </CardBody>
                        </Card>
                    )}

                    {order.open_dispute && (
                        <Card><CardHeader><CardTitle>Komplain {order.open_dispute.number}</CardTitle></CardHeader><CardBody className="space-y-4"><Alert tone="warning" title={order.open_dispute.status_label}><span className="text-sm">Dana pesanan ditahan selama komplain diproses.</span></Alert><div className="rounded-xl bg-surface-2 p-4"><p className="text-xs font-bold uppercase text-muted">{order.open_dispute.type}</p><p className="mt-2 text-sm">{order.open_dispute.description}</p></div><form onSubmit={(e) => { e.preventDefault(); disputeForm.post(`/dashboard/pesanan/${order.number}/respons-komplain`, { preserveScroll: true }); }} className="space-y-3"><Field label="Respons untuk pembeli" required error={disputeForm.errors.response}><Textarea rows={4} value={disputeForm.data.response} onChange={(e) => disputeForm.setData('response', e.target.value)} placeholder="Jelaskan kondisi paket dan solusi yang kamu tawarkan." /></Field><Button type="submit" variant="gradient" loading={disputeForm.processing}><AlertTriangle className="size-4" /> Kirim respons</Button></form></CardBody></Card>
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
                                                 {payment.fee > 0 && (
                                                     <span className="block text-xs text-muted">
                                                         Biaya {formatIDR(payment.fee)} · {payment.fee_source === 'PROVIDER' ? 'aktual provider' : 'estimasi'}
                                                         {payment.settlement_days > 0 ? ` · estimasi cair H+${payment.settlement_days}` : ''}
                                                     </span>
                                                 )}
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
