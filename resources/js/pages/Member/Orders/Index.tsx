import { Link, router } from '@inertiajs/react';
import { ImageIcon, MessageCircle, ShoppingBag, Store as StoreIcon } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Pagination, StatusBadge } from '@/components/shared';
import { Card, EmptyState } from '@/components/ui';
import { cn, formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface OrderItem {
    name: string;
    quantity: number;
    thumbnail_url: string | null;
}

interface OrderRow {
    number: string;
    store: string;
    store_username: string;
    grand_total: number;
    status: string;
    status_label: string;
    items_count: number;
    items: OrderItem[];
    is_payable: boolean;
    created_at: string;
}

interface Tab {
    key: string;
    label: string;
    count: number;
}

/**
 * The buyer's orders.
 *
 * A table was the wrong shape: a row of columns answers "how many orders do I
 * have", which nobody wonders. What people come here for is one order — the one
 * they are waiting on — so each is a card carrying the thing they bought, and
 * the tabs are the four questions they actually have about it.
 */
export default function MemberOrdersIndex({
    orders,
    tab,
    tabs,
}: {
    orders: Paginated<OrderRow>;
    tab: string;
    tabs: Tab[];
}) {
    return (
        <DashboardLayout title="Pembelian" area="member">
            <div className="mb-5">
                <h1 className="text-[1.375rem] font-semibold tracking-[-.02em]">Pembelian</h1>
                <p className="mt-1.5 text-sm text-muted">Semua pesanan kamu, dari yang belum dibayar sampai selesai.</p>
            </div>

            <div className="mb-4 flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none]">
                {tabs.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => router.get('/member/pembelian', item.key === 'semua' ? {} : { tab: item.key }, {
                            preserveScroll: true,
                            preserveState: false,
                        })}
                        className={cn(
                            '-mb-px shrink-0 border-b-2 px-3.5 py-2.5 text-[0.8125rem] transition-colors',
                            tab === item.key
                                ? 'border-[var(--primary)] font-semibold text-[var(--primary)]'
                                : 'border-transparent text-muted hover:text-fg',
                        )}
                    >
                        {item.label}
                        {item.count > 0 && <span className="ml-1.5 text-xs opacity-70">({item.count})</span>}
                    </button>
                ))}
            </div>

            {orders.data.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<ShoppingBag className="size-6" />}
                        title="Belum ada pesanan di sini"
                        description={
                            tab === 'semua'
                                ? 'Pesanan kamu akan muncul di halaman ini setelah checkout.'
                                : 'Coba lihat tab lain — mungkin pesananmu ada di tahap yang berbeda.'
                        }
                    />
                </Card>
            ) : (
                <ul className="space-y-3">
                    {orders.data.map((order) => (
                        <li key={order.number}>
                            <Card className="overflow-hidden">
                                {/* Store row: who you bought from, and how to reach them. */}
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-4 py-2.5">
                                    <span className="flex items-center gap-1.5 text-[0.8125rem] font-semibold">
                                        <StoreIcon className="size-3.5 text-muted" />
                                        {order.store}
                                    </span>

                                    <Link
                                        href={`/${order.store_username}`}
                                        className="rounded border border-line px-2 py-1 text-[0.6875rem] font-medium"
                                    >
                                        Kunjungi Toko
                                    </Link>

                                    <span className="ml-auto flex items-center gap-2">
                                        <span className="text-xs text-muted">{formatDate(order.created_at, true)}</span>
                                        <StatusBadge status={order.status} label={order.status_label} />
                                    </span>
                                </div>

                                <Link href={`/member/pembelian/${order.number}`} className="block px-4 py-3 transition-colors hover:bg-surface-2">
                                    <ul className="space-y-2.5">
                                        {order.items.map((item, i) => (
                                            <li key={i} className="flex gap-3">
                                                <span className="size-14 shrink-0 overflow-hidden rounded bg-surface-2">
                                                    {item.thumbnail_url ? (
                                                        <img src={item.thumbnail_url} alt="" loading="lazy" className="size-full object-cover" />
                                                    ) : (
                                                        <span className="grid size-full place-items-center text-muted">
                                                            <ImageIcon className="size-4" />
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="line-clamp-2 text-[0.8125rem] leading-5">{item.name}</span>
                                                    <span className="mt-0.5 block text-xs text-muted">x{item.quantity}</span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>

                                    {order.items_count > order.items.length && (
                                        <p className="mt-2 text-xs text-muted">
                                            +{order.items_count - order.items.length} produk lainnya
                                        </p>
                                    )}
                                </Link>

                                <div className="flex flex-wrap items-center justify-end gap-3 border-t border-line px-4 py-3">
                                    <span className="mr-auto text-xs text-muted">{order.number}</span>

                                    <span className="text-[0.8125rem]">
                                        Total Pesanan:{' '}
                                        <strong className="jy-num text-base font-semibold text-[var(--primary)]">
                                            {formatIDR(order.grand_total)}
                                        </strong>
                                    </span>

                                    <Link
                                        href={`/${order.store_username}`}
                                        className="inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-field)] border border-line px-3 text-xs font-medium"
                                    >
                                        <MessageCircle className="size-3.5" /> Hubungi Penjual
                                    </Link>

                                    <Link
                                        href={`/member/pembelian/${order.number}`}
                                        className={cn(
                                            'inline-flex h-8 items-center rounded-[var(--radius-field)] px-3 text-xs font-semibold',
                                            order.is_payable
                                                ? 'bg-[var(--primary)] text-white'
                                                : 'border border-line',
                                        )}
                                    >
                                        {order.is_payable ? 'Bayar Sekarang' : 'Lihat Detail'}
                                    </Link>
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination meta={orders} />
        </DashboardLayout>
    );
}
