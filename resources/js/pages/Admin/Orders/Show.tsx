import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatusBadge } from '@/components/shared';
import { Badge, Card, CardBody, CardHeader, CardTitle } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function AdminOrderShow({ order }: { order: any }) {
    const gatewayFee = order.settlement_version >= 2
        ? order.gateway_fee_actual
        : order.gateway_fee_estimated;
    const pendingCredit = Math.max(0, order.seller_net - order.reserve_amount - order.debt_offset);

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
                                <p className="pb-1 text-xs font-extrabold uppercase tracking-[0.12em] text-muted">Arus uang pembeli</p>
                                <Row label="Subtotal produk" value={formatIDR(order.subtotal)} />
                                {order.discount_total > 0 && <Row label="Diskon" value={`−${formatIDR(order.discount_total)}`} />}
                                {order.shipping_total > 0 && <Row label="Ongkir dari pembeli" value={formatIDR(order.shipping_total)} />}
                                {order.tax_total > 0 && <Row label="Pajak" value={formatIDR(order.tax_total)} />}
                                <Row label="Dibayar pembeli" value={formatIDR(order.grand_total)} bold />

                                <p className="pb-1 pt-4 text-xs font-extrabold uppercase tracking-[0.12em] text-muted">Distribusi pendapatan</p>
                                <Row label="Dasar komisi" value={formatIDR(order.commission_base)} />
                                <Row label={`Biaya platform · ${Number(order.platform_fee_rate ?? 0).toLocaleString('id-ID')}%`} value={formatIDR(order.platform_fee)} accent />
                                {gatewayFee > 0 && <Row label={`Biaya gateway · ${order.gateway_fee_bearer === 'PLATFORM' ? 'platform' : 'seller'}${order.settlement_version < 2 ? ' · estimasi' : ''}`} value={`−${formatIDR(gatewayFee)}`} />}
                                {order.affiliate_commission > 0 && <Row label="Komisi affiliate" value={`−${formatIDR(order.affiliate_commission)}`} />}
                                {order.shipping_cost_actual > 0 && <Row label="Biaya kurir aktual" value={`−${formatIDR(order.shipping_cost_actual)}`} />}
                                {order.shipping_total > 0 && <Row label="Selisih ongkir" value={formatIDR(order.shipping_variance)} />}
                                <Row label="Hak bersih seller" value={formatIDR(order.seller_net)} bold />
                                {order.reserve_amount > 0 && <Row label={`Cadangan · ${Number(order.reserve_rate ?? 0).toLocaleString('id-ID')}%`} value={`−${formatIDR(order.reserve_amount)}`} />}
                                {order.debt_offset > 0 && <Row label="Pemulihan saldo minus" value={`−${formatIDR(order.debt_offset)}`} />}
                                <Row label="Kredit saldo tertahan" value={formatIDR(pendingCredit)} bold />

                                <div className="mt-3 rounded-xl border border-violet-200 bg-violet-50 p-3">
                                    <Row label="Contribution margin platform" value={formatIDR(order.contribution_margin)} bold accent />
                                    <p className="mt-1 text-xs text-muted">Pendapatan platform setelah biaya gateway dan kebocoran operasional pesanan.</p>
                                </div>
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
                                                <span className="text-sm font-semibold">
                                                    {payment.provider} · {payment.method}
                                                    {payment.channel ? ` (${payment.channel})` : ''}
                                                </span>
                                                <StatusBadge status={payment.status} label={payment.status} />
                                            </div>
                                            <p className="mt-0.5 truncate font-mono text-xs text-muted">
                                                {payment.reference}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                Nominal {formatIDR(payment.amount)} · biaya {formatIDR(payment.fee)} ({payment.fee_source === 'PROVIDER' ? 'aktual provider' : 'estimasi'})
                                                {payment.settlement_days > 0 ? ` · settlement H+${payment.settlement_days}` : ''}
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
