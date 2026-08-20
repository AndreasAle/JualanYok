import { Link } from '@inertiajs/react';
import { BookOpen, Download, ShoppingBag, Star } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, StatCard } from '@/components/shared';
import { ButtonLink, Card, CardBody, CardHeader, CardTitle, EmptyState } from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';

export default function MemberDashboard({
    stats,
    recentOrders,
    courses,
}: {
    stats: { orders: number; downloads: number; courses: number; memberships: number };
    recentOrders: any[];
    courses: any[];
}) {
    return (
        <DashboardLayout title="Akun Saya" area="member">
            <PageHeader title="Pembelian Kamu" description="Semua yang pernah kamu beli ada di sini." />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total pembelian" value={formatNumber(stats.orders)} icon={<ShoppingBag className="size-4.5" />} />
                <StatCard label="File bisa diunduh" value={formatNumber(stats.downloads)} icon={<Download className="size-4.5" />} />
                <StatCard label="Kelas diikuti" value={formatNumber(stats.courses)} icon={<BookOpen className="size-4.5" />} />
                <StatCard label="Membership aktif" value={formatNumber(stats.memberships)} icon={<Star className="size-4.5" />} />
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Pembelian terbaru</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {recentOrders.length === 0 ? (
                            <EmptyState
                                icon={<ShoppingBag className="size-6" />}
                                title="Belum ada pembelian"
                                description="Pembelian kamu akan muncul di sini."
                            />
                        ) : (
                            <ul className="divide-y divide-[var(--border)]">
                                {recentOrders.map((order) => (
                                    <li key={order.number}>
                                        <Link
                                            href={`/member/pembelian/${order.number}`}
                                            className="flex items-center justify-between gap-3 py-3 hover:bg-surface-2"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold">
                                                    {order.store}
                                                </span>
                                                <span className="block text-xs text-muted">
                                                    {order.number} · {order.created_at}
                                                </span>
                                            </span>
                                            <span className="shrink-0 text-right">
                                                <span className="block font-bold">{formatIDR(order.grand_total)}</span>
                                                <span className="block text-xs text-muted">{order.status_label}</span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lanjut belajar</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {courses.length === 0 ? (
                            <EmptyState
                                icon={<BookOpen className="size-6" />}
                                title="Belum ikut kelas"
                                description="Kelas yang kamu beli akan muncul di sini."
                            />
                        ) : (
                            <ul className="space-y-3">
                                {courses.map((course) => (
                                    <li key={course.id}>
                                        <Link
                                            href={`/member/kelas/${course.id}`}
                                            className="flex items-center gap-3 rounded-[var(--radius-field)] p-2 hover:bg-surface-2"
                                        >
                                            {course.thumbnail_url ? (
                                                <img
                                                    src={course.thumbnail_url}
                                                    alt=""
                                                    className="size-12 shrink-0 rounded-xl object-cover"
                                                />
                                            ) : (
                                                <span className="size-12 shrink-0 rounded-xl gradient-brand" />
                                            )}

                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-semibold">
                                                    {course.title}
                                                </span>
                                                <span className="mt-1 block h-1.5 rounded-full bg-surface-2">
                                                    <span
                                                        className="block h-full rounded-full gradient-brand"
                                                        style={{ width: `${Math.max(3, course.progress)}%` }}
                                                    />
                                                </span>
                                                <span className="mt-1 block text-xs text-muted">
                                                    {course.progress}% selesai
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {courses.length > 0 && (
                            <ButtonLink href="/member/kelas" variant="outline" block className="mt-4">
                                Lihat Semua Kelas
                            </ButtonLink>
                        )}
                    </CardBody>
                </Card>
            </div>
        </DashboardLayout>
    );
}
