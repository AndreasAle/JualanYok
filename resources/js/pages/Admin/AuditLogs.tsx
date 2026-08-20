import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Badge, Button, Card, EmptyState, Select } from '@/components/ui';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';

interface LogRow {
    id: number;
    action: string;
    actor: string;
    subject: string | null;
    reason: string | null;
    before: Record<string, any> | null;
    after: Record<string, any> | null;
    ip_address: string | null;
    created_at: string;
}

export default function AuditLogs({
    logs,
    filters,
    actions,
}: {
    logs: Paginated<LogRow>;
    filters: { action?: string; user_id?: string };
    actions: string[];
}) {
    const [expanded, setExpanded] = useState<number | null>(null);

    const columns: Column<LogRow>[] = [
        {
            key: 'action',
            header: 'Tindakan',
            render: (row) => (
                <span>
                    <Badge tone={row.action.includes('suspend') || row.action.includes('reject') ? 'danger' : 'neutral'}>
                        {row.action}
                    </Badge>
                    {row.subject && <span className="mt-1 block font-mono text-xs text-muted">{row.subject}</span>}
                </span>
            ),
        },
        {
            key: 'actor',
            header: 'Oleh',
            render: (row) => <span className="text-sm font-medium">{row.actor}</span>,
        },
        {
            key: 'reason',
            header: 'Alasan',
            mobile: false,
            render: (row) => <span className="text-sm text-muted">{row.reason ?? '—'}</span>,
        },
        {
            key: 'ip',
            header: 'IP',
            mobile: false,
            render: (row) => <span className="font-mono text-xs text-muted">{row.ip_address ?? '—'}</span>,
        },
        {
            key: 'time',
            header: 'Waktu',
            align: 'right',
            render: (row) => <span className="text-xs text-muted">{formatDate(row.created_at, true)}</span>,
        },
        {
            key: 'detail',
            header: '',
            align: 'right',
            render: (row) =>
                row.before || row.after ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setExpanded(expanded === row.id ? null : row.id)}
                    >
                        {expanded === row.id ? 'Tutup' : 'Detail'}
                    </Button>
                ) : null,
        },
    ];

    const detail = logs.data.find((log) => log.id === expanded);

    return (
        <DashboardLayout title="Audit Log" area="admin">
            <PageHeader
                title="Audit Log"
                description="Jejak setiap tindakan admin yang sensitif. Nggak bisa dihapus."
            />

            <div className="mb-4">
                <Select
                    value={filters.action ?? ''}
                    onChange={(e) =>
                        router.get(
                            '/admin/audit',
                            { action: e.target.value || undefined },
                            { preserveState: true, replace: true, preserveScroll: true },
                        )
                    }
                    aria-label="Filter tindakan"
                    className="sm:w-72"
                >
                    <option value="">Semua tindakan</option>
                    {actions.map((action) => (
                        <option key={action} value={action}>
                            {action}
                        </option>
                    ))}
                </Select>
            </div>

            {detail && (
                <Card className="mb-4 p-5">
                    <p className="font-bold">Detail perubahan</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">Sebelum</p>
                            <pre className="overflow-x-auto rounded-[var(--radius-field)] bg-surface-2 p-3 text-xs">
                                {JSON.stringify(detail.before ?? {}, null, 2)}
                            </pre>
                        </div>
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">Sesudah</p>
                            <pre className="overflow-x-auto rounded-[var(--radius-field)] bg-surface-2 p-3 text-xs">
                                {JSON.stringify(detail.after ?? {}, null, 2)}
                            </pre>
                        </div>
                    </div>
                </Card>
            )}

            <DataList
                rows={logs.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={<ShieldCheck className="size-6" />}
                        title="Belum ada catatan"
                        description="Tindakan admin akan tercatat otomatis di sini."
                    />
                }
            />

            <Pagination meta={logs} />
        </DashboardLayout>
    );
}
