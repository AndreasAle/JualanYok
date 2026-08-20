import { Link } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatCard, StatusBadge } from '@/components/shared';
import { Badge, Card, CardBody, CardHeader, CardTitle, EmptyState } from '@/components/ui';
import { formatDate, formatIDR, formatNumber } from '@/lib/utils';

export default function CustomerShow({ customer }: { customer: any }) {
    return (
        <DashboardLayout title={customer.name} area="creator">
            <PageHeader
                title={customer.name}
                description={customer.email}
                breadcrumbs={[{ label: 'Pelanggan', href: '/dashboard/pelanggan' }, { label: customer.name }]}
            />

            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard label="Total order" value={formatNumber(customer.orders_count)} />
                <StatCard label="Total belanja" value={formatIDR(customer.lifetime_value)} />
                <StatCard label="Order terakhir" value={formatDate(customer.last_order_at)} />
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat pesanan</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {customer.orders.length === 0 ? (
                            <EmptyState title="Belum ada pesanan" />
                        ) : (
                            <ul className="divide-y divide-[var(--border)]">
                                {customer.orders.map((order: any) => (
                                    <li key={order.number}>
                                        <Link
                                            href={`/dashboard/pesanan/${order.number}`}
                                            className="flex items-center justify-between gap-3 py-3 hover:bg-surface-2"
                                        >
                                            <span className="min-w-0">
                                                <span className="block font-mono text-sm font-semibold">
                                                    {order.number}
                                                </span>
                                                <span className="block text-xs text-muted">
                                                    {formatDate(order.created_at, true)}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-right">
                                                <span className="block font-bold">{formatIDR(order.grand_total)}</span>
                                                <StatusBadge status={order.status} label={order.status_label} />
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Kontak</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-2 text-sm">
                            <p>{customer.email}</p>
                            {customer.phone && <p className="text-muted">{customer.phone}</p>}
                            <p>
                                {customer.marketing_consent ? (
                                    <Badge tone="success">Boleh dikirimi promo</Badge>
                                ) : (
                                    <Badge>Nggak mau dikirimi promo</Badge>
                                )}
                            </p>
                            {customer.source && <p className="text-xs text-muted">Sumber: {customer.source}</p>}
                        </CardBody>
                    </Card>

                    {customer.addresses?.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Alamat</CardTitle>
                            </CardHeader>
                            <CardBody className="space-y-3 text-sm">
                                {customer.addresses.map((address: any) => (
                                    <div key={address.id} className="rounded-[var(--radius-field)] bg-surface-2 p-3">
                                        <p className="font-semibold">{address.recipient}</p>
                                        <p className="text-muted">{address.phone}</p>
                                        <p className="mt-1 text-muted">
                                            {address.address_line}, {address.city}, {address.province}{' '}
                                            {address.postal_code}
                                        </p>
                                    </div>
                                ))}
                            </CardBody>
                        </Card>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}
