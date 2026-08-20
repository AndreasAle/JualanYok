import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatusBadge } from '@/components/shared';
import { Badge, Card, CardBody, CardHeader, CardTitle } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function AdminOrderShow({ order }: { order: any }) {
    return (
        <DashboardLayout title={`Pesanan ${order.number}`} area="admin">
            <PageHeader
                title={order.number}
                description={`${order.store.name} · ${order.customer_email}`}
                breadcrumbs={[{ label: 'Pesanan', href: '/admin/pesanan' }, { label: order.number }]}
                actions={<StatusBadge status={order.status} label={order.status_label} />}
            />

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Rincian uang</CardTitle>
                        </CardHeader>
                        <CardBody>
                            <ul className="divide-y divide-[var(--border)]">
                                {order.items.map((item: any, i: number) => (
                                    <li key={i} className="flex justify-between gap-3 py-2.5 text-sm first:pt-0">
                                        <span>
                                            {item.name} <span className="text-muted">×{item.quantity}</span>
                                        </span>
                                        <span className="tabular-nums">{formatIDR(item.total)}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 space-y-1.5 border-t border-line pt-4 text-sm">
                                <Row label="Subtotal" value={formatIDR(order.subtotal)} />
                                <Row label="Diskon" value={`−${formatIDR(order.discount_total)}`} />
                                <Row label="Biaya pembayaran" value={formatIDR(order.payment_fee)} />
                                <Row label="Biaya platform" value={formatIDR(order.platform_fee)} accent />
                                <Row label="Komisi affiliate" value={formatIDR(order.affiliate_commission)} />
                                <Row label="Dibayar pembeli" value={formatIDR(order.grand_total)} bold />
                                <Row label="Diterima seller" value={formatIDR(order.seller_net)} bold />
                                {order.refunded_total > 0 && (
                                    <Row label="Sudah direfund" value={`−${formatIDR(order.refunded_total)}`} />
                                )}
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Jejak pembayaran</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {order.payments.length === 0 ? (
                                <p className="text-sm text-muted">Belum ada percobaan pembayaran.</p>
                            ) : (
                                <div className="space-y-4">
                                    {order.payments.map((payment: any, i: number) => (
                                        <div key={i} className="rounded-[var(--radius-field)] bg-surface-2 p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-sm font-semibold">{payment.provider}</span>
                                                <StatusBadge status={payment.status} label={payment.status} />
                                            </div>
                                            <p className="mt-0.5 truncate font-mono text-xs text-muted">
                                                {payment.reference}
                                            </p>

                                            <ul className="mt-2 space-y-1">
                                                {payment.attempts.map((attempt: any, j: number) => (
                                                    <li key={j} className="flex justify-between gap-3 text-xs">
                                                        <span className="text-muted">
                                                            {attempt.action}
                                                            {attempt.error && (
                                                                <span className="ml-1 text-[var(--danger)]">
                                                                    {attempt.error}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="shrink-0 text-muted">
                                                            {attempt.status} · {attempt.created_at}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardBody>
                    </Card>
                </div>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Pihak terkait</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-3 text-sm">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted">Penjual</p>
                                <p className="font-semibold">{order.seller.name}</p>
                                <p className="text-muted">{order.seller.email}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted">Pembeli</p>
                                <p className="font-semibold">{order.customer_name}</p>
                                <p className="text-muted">{order.customer_email}</p>
                            </div>
                        </CardBody>
                    </Card>

                    {order.commissions.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Komisi affiliate</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <ul className="space-y-2 text-sm">
                                    {order.commissions.map((commission: any, i: number) => (
                                        <li key={i} className="flex items-center justify-between gap-3">
                                            <span>{commission.affiliate}</span>
                                            <span className="flex shrink-0 items-center gap-2">
                                                <span className="font-bold">{formatIDR(commission.amount)}</span>
                                                <Badge>{commission.status}</Badge>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    )}

                    {order.refunds.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Refund</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <ul className="space-y-2 text-sm">
                                    {order.refunds.map((refund: any) => (
                                        <li key={refund.id} className="rounded-[var(--radius-field)] bg-surface-2 p-3">
                                            <div className="flex justify-between gap-3">
                                                <span className="font-bold">{formatIDR(refund.amount)}</span>
                                                <Badge>{refund.status}</Badge>
                                            </div>
                                            {refund.reason && (
                                                <p className="mt-1 text-xs text-muted">{refund.reason}</p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </CardBody>
                        </Card>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

function Row({
    label,
    value,
    bold,
    accent,
}: {
    label: string;
    value: string;
    bold?: boolean;
    accent?: boolean;
}) {
    return (
        <div className={`flex justify-between gap-3 ${bold ? 'font-bold' : ''}`}>
            <span className={bold ? '' : 'text-muted'}>{label}</span>
            <span className={`tabular-nums ${accent ? 'text-[var(--primary)] font-semibold' : ''}`}>{value}</span>
        </div>
    );
}
