import { useForm } from '@inertiajs/react';
import { AlertTriangle, Download, MapPin, PackageCheck, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatusBadge } from '@/components/shared';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Field, Textarea,
} from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function MemberOrderShow({ order }: { order: any }) {
    const [refundOpen, setRefundOpen] = useState(false);
    const [disputeOpen, setDisputeOpen] = useState(false);
    const refundForm = useForm({ reason: '' });
    const disputeForm = useForm({ type: 'not_received', description: '' });
    const receiptForm = useForm({});

    const submitRefund = (e: FormEvent) => {
        e.preventDefault();
        refundForm.post(`/member/pembelian/${order.number}/refund`, {
            preserveScroll: true,
            onSuccess: () => setRefundOpen(false),
        });
    };

    const submitDispute = (e: FormEvent) => {
        e.preventDefault();
        disputeForm.post(`/member/pembelian/${order.number}/komplain`, {
            preserveScroll: true,
            onSuccess: () => setDisputeOpen(false),
        });
    };

    return (
        <DashboardLayout title={`Pesanan ${order.number}`} area="member">
            <PageHeader
                title={order.number}
                description={`Dari ${order.store.name} · ${formatDate(order.created_at, true)}`}
                breadcrumbs={[{ label: 'Pembelian', href: '/member/pembelian' }, { label: order.number }]}
                actions={<StatusBadge status={order.status} label={order.status_label} />}
            />

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <div className="space-y-4">
                    {order.requires_shipping && (
                        <Card>
                            <CardHeader><CardTitle>Perjalanan paket</CardTitle></CardHeader>
                            <CardBody>
                                <div className="flex flex-wrap items-start justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-4">
                                    <div>
                                        <p className="font-bold">{order.shipment?.status_label ?? order.fulfillment_label}</p>
                                        <p className="mt-1 text-xs text-muted">{order.shipment?.courier || 'Kurir belum ditentukan'}{order.shipment?.waybill_id ? ` · ${order.shipment.waybill_id}` : ''}</p>
                                    </div>
                                    {order.shipment?.tracking_url && <a href={order.shipment.tracking_url} target="_blank" rel="noreferrer" className="text-sm font-bold text-[var(--primary)]">Lacak resmi</a>}
                                </div>

                                {order.shipment?.events?.length > 0 && (
                                    <ol className="mt-5 space-y-4">
                                        {order.shipment.events.map((event: any, index: number) => (
                                            <li key={index} className="flex gap-3">
                                                <span className="mt-1 size-3 shrink-0 rounded-full bg-[var(--primary)]" />
                                                <div>
                                                    <p className="text-sm font-semibold">{event.description}</p>
                                                    <p className="mt-0.5 flex flex-wrap gap-2 text-xs text-muted">
                                                        {event.location && <span className="inline-flex items-center gap-1"><MapPin className="size-3" />{event.location}</span>}
                                                        <span>{formatDate(event.event_at, true)}</span>
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                )}

                                {order.open_dispute && (
                                    <div className="mt-4">
                                        <Alert tone="warning">
                                            <b>{order.open_dispute.number}</b> · {order.open_dispute.status_label}
                                            {order.open_dispute.seller_response && <><br />Respons penjual: {order.open_dispute.seller_response}</>}
                                        </Alert>
                                    </div>
                                )}

                                {!order.open_dispute && (
                                    <div className="mt-5 flex flex-wrap gap-2">
                                        {order.can_confirm_receipt && <Button loading={receiptForm.processing} onClick={() => receiptForm.post(`/member/pembelian/${order.number}/diterima`, { preserveScroll: true })}><PackageCheck className="size-4" /> Pesanan diterima</Button>}
                                        {order.can_open_dispute && <Button variant="outline" onClick={() => setDisputeOpen((value) => !value)}><AlertTriangle className="size-4" /> Ajukan komplain</Button>}
                                    </div>
                                )}

                                {disputeOpen && (
                                    <form onSubmit={submitDispute} className="mt-4 space-y-3 rounded-[var(--radius-card)] border border-red-200 bg-red-50 p-4">
                                        <select value={disputeForm.data.type} onChange={(event) => disputeForm.setData('type', event.target.value)} className="h-11 w-full rounded-[var(--radius-field)] border border-line bg-white px-3 text-sm">
                                            <option value="not_received">Barang belum diterima</option>
                                            <option value="damaged">Barang rusak</option>
                                            <option value="wrong_item">Barang tidak sesuai</option>
                                            <option value="incomplete">Barang kurang</option>
                                            <option value="other">Lainnya</option>
                                        </select>
                                        <Textarea rows={4} required minLength={20} value={disputeForm.data.description} onChange={(event) => disputeForm.setData('description', event.target.value)} placeholder="Ceritakan kronologi selengkap mungkin." />
                                        <Button type="submit" variant="danger" loading={disputeForm.processing}>Kirim komplain</Button>
                                    </form>
                                )}
                            </CardBody>
                        </Card>
                    )}

                    {/* Downloads */}
                    {order.downloads.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Produk digital kamu</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <ul className="space-y-2">
                                    {order.downloads.map((file: any) => (
                                        <li
                                            key={file.id}
                                            className="flex items-center justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold">
                                                    {file.name}
                                                </span>
                                                <span className="block text-xs text-muted">
                                                    v{file.version}
                                                    {file.remaining !== null && ` · sisa ${file.remaining} unduhan`}
                                                    {file.expires_at && ` · berlaku sampai ${formatDate(file.expires_at)}`}
                                                </span>
                                            </span>

                                            {file.available ? (
                                                <a
                                                    href={file.url}
                                                    className="inline-flex h-9 shrink-0 items-center gap-2 rounded-[var(--radius-field)] gradient-brand px-3 text-sm font-bold text-white"
                                                >
                                                    <Download className="size-4" />
                                                    Unduh
                                                </a>
                                            ) : (
                                                <Badge tone="danger">Nggak tersedia</Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>

                                <p className="mt-3 text-xs text-muted">
                                    Link unduhan berumur pendek dan dibuat khusus buat kamu. Jangan dibagikan ya.
                                </p>
                            </CardBody>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Item</CardTitle>
                        </CardHeader>
                        <CardBody>
                            <ul className="divide-y divide-[var(--border)]">
                                {order.items.map((item: any, i: number) => (
                                    <li key={i} className="py-3 first:pt-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="font-semibold">{item.name}</p>
                                                {item.variant_name && (
                                                    <p className="text-xs text-muted">{item.variant_name}</p>
                                                )}
                                                <p className="text-xs text-muted">×{item.quantity}</p>
                                            </div>
                                            <span className="shrink-0 font-bold tabular-nums">
                                                {formatIDR(item.total)}
                                            </span>
                                        </div>

                                        {item.post_purchase_message && (
                                            <div className="mt-2 rounded-[var(--radius-field)] bg-surface-2 p-3 text-sm">
                                                {item.post_purchase_message}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 space-y-1.5 border-t border-line pt-4 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted">Subtotal</span>
                                    <span className="tabular-nums">{formatIDR(order.subtotal)}</span>
                                </div>
                                {order.discount_total > 0 && (
                                    <div className="flex justify-between text-[var(--success)]">
                                        <span>Diskon</span>
                                        <span className="tabular-nums">−{formatIDR(order.discount_total)}</span>
                                    </div>
                                )}
                                {order.payment_fee > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-muted">Biaya pembayaran</span>
                                        <span className="tabular-nums">{formatIDR(order.payment_fee)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-line pt-2 font-bold">
                                    <span>Total dibayar</span>
                                    <span className="tabular-nums">{formatIDR(order.grand_total)}</span>
                                </div>
                            </div>
                        </CardBody>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-2 text-sm">
                            <p>
                                <span className="text-muted">Pembayaran:</span> {order.payment_label}
                            </p>
                            <p>
                                <span className="text-muted">Pengiriman:</span> {order.fulfillment_label}
                            </p>
                            {order.paid_at && (
                                <p>
                                    <span className="text-muted">Dibayar:</span> {formatDate(order.paid_at, true)}
                                </p>
                            )}
                            {order.tracking_number && (
                                <p className="flex items-center gap-1.5">
                                    <Truck className="size-4 text-[var(--primary)]" />
                                    <span className="font-mono font-semibold">{order.tracking_number}</span>
                                </p>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Butuh refund?</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {order.refundable > 0 ? (
                                refundOpen ? (
                                    <form onSubmit={submitRefund} className="space-y-3">
                                        <Field
                                            label="Kenapa mau refund?"
                                            required
                                            error={refundForm.errors.reason}
                                            hint="Minimal 10 karakter."
                                            htmlFor="reason"
                                        >
                                            <Textarea
                                                id="reason"
                                                rows={4}
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
                                    <>
                                        <p className="mb-3 text-sm text-muted">
                                            Refund maksimal {formatIDR(order.refundable)}. Diproses tim JualanYok
                                            sesuai kebijakan penjual.
                                        </p>
                                        <Button variant="outline" block onClick={() => setRefundOpen(true)}>
                                            Ajukan Refund
                                        </Button>
                                    </>
                                )
                            ) : (
                                <Alert tone="info">Pesanan ini nggak bisa direfund lagi.</Alert>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
