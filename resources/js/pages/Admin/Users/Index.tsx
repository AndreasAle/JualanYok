import { Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, SearchInput, type Column } from '@/components/shared';
import { Badge, EmptyState, Select } from '@/components/ui';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

interface UserRow {
    id: number;
    name: string;
    username: string;
    email: string;
    status: string;
    is_creator: boolean;
    is_affiliate: boolean;
    roles: string[];
    stores_count: number;
    created_at: string;
}

export default function AdminUsers({
    users,
    filters,
    roles,
}: {
    users: Paginated<UserRow>;
    filters: { q?: string; role?: string; status?: string };
    roles: { slug: string; name: string }[];
}) {
    const setFilter = (key: string, value: string) => {
        router.get('/admin/pengguna', { ...filters, [key]: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Pengguna',
            render: (row) => (
                <Link href={`/admin/pengguna/${row.id}`} className="group block">
                    <span className="block font-semibold group-hover:text-[var(--primary)]">{row.name}</span>
                    <span className="block text-xs text-muted">
                        @{row.username} · {row.email}
                    </span>
                </Link>
            ),
        },
        {
            key: 'roles',
            header: 'Peran',
            render: (row) => (
                <span className="flex flex-wrap gap-1">
                    {row.roles.map((role) => (
                        <Badge key={role} tone={role.includes('admin') ? 'brand' : 'neutral'}>
                            {role}
                        </Badge>
                    ))}
                </span>
            ),
        },
        {
            key: 'stores',
            header: 'Toko',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-muted">{row.stores_count}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) =>
                row.status === 'active' ? <Badge tone="success">Aktif</Badge> : <Badge tone="danger">{row.status}</Badge>,
        },
        {
            key: 'joined',
            header: 'Bergabung',
            align: 'right',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{formatDate(row.created_at)}</span>,
        },
    ];

    return (
        <DashboardLayout title="Pengguna" area="admin">
            <PageHeader title="Pengguna" description="Semua akun terdaftar di platform." />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <SearchInput
                    routeName="/admin/pengguna"
                    value={filters.q}
                    placeholder="Cari nama, email, atau username..."
                    extra={filters}
                />

                <Select
                    value={filters.role ?? ''}
                    onChange={(e) => setFilter('role', e.target.value)}
                    aria-label="Filter peran"
                    className="sm:w-48"
                >
                    <option value="">Semua peran</option>
                    {roles.map((role) => (
                        <option key={role.slug} value={role.slug}>
                            {role.name}
                        </option>
                    ))}
                </Select>

                <Select
                    value={filters.status ?? ''}
                    onChange={(e) => setFilter('status', e.target.value)}
                    aria-label="Filter status"
                    className="sm:w-40"
                >
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="suspended">Ditangguhkan</option>
                </Select>
            </div>

            <DataList
                rows={users.data}
                columns={columns}
                rowKey={(row) => row.id}
                rowHref={(row) => `/admin/pengguna/${row.id}`}
                empty={
                    <EmptyState
                        icon={<Users className="size-6" />}
                        title="Nggak ada pengguna yang cocok"
                        description="Coba ubah kata kunci atau filternya."
                    />
                }
            />

            <Pagination meta={users} />
        </DashboardLayout>
    );
}
