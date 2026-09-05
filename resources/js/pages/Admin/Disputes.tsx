import { router, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import { Alert, Badge, Button, Card, EmptyState, Select, Textarea } from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface DisputeRow {
    id: number; number: string; order_number: string; store: string; buyer: string; buyer_email: string;
    type: string; status: string; status_label: string; description: string; seller_response: string | null;
    seller_response_due_at: string | null; order_total: number; courier: string | null; waybill_id: string | null; created_at: string;
}

export default function AdminDisputes({ disputes, filters, canResolve }: { disputes: Paginated<DisputeRow>; filters: { status?: string }; canResolve: boolean }) {
    const [selected, setSelected] = useState<DisputeRow | null>(null);
    const columns: Column<DisputeRow>[] = [
        { key: 'case', header: 'Komplain', render: (row) => <span><b className="block font-mono text-sm">{row.number}</b><span className="text-xs text-muted">{row.order_number} · {formatDate(row.created_at, true)}</span></span> },
        { key: 'parties', header: 'Transaksi', render: (row) => <span><b className="block text-sm">{row.buyer}</b><span className="block text-xs text-muted">{row.store} · {formatIDR(row.order_total)}</span></span> },
        { key: 'issue', header: 'Masalah', mobile: false, render: (row) => <span className="line-clamp-2 max-w-sm text-sm">{row.description}</span> },
        { key: 'status', header: 'Status', render: (row) => <Badge tone={row.status === 'RESOLVED' ? 'success' : row.status === 'OPEN' ? 'warning' : 'info'}>{row.status_label}</Badge> },
        { key: 'action', header: '', align: 'right', render: (row) => <Button size="sm" variant="outline" onClick={() => setSelected(row)}>Tinjau</Button> },
    ];

    return <DashboardLayout title="Pusat Komplain" area="admin">
        <PageHeader title="Pusat Komplain" description="Dana tetap ditahan sampai bukti pembeli, respons penjual, dan perjalanan paket selesai ditinjau." />
        {!canResolve && <div className="mb-4"><Alert tone="info">Kamu dapat meninjau kasus. Keputusan akhir hanya untuk finance atau super admin.</Alert></div>}
        <Select className="mb-4 sm:w-64" value={filters.status ?? ''} onChange={(event) => router.get('/admin/komplain', { status: event.target.value || undefined }, { preserveState: true, replace: true })}>
            <option value="">Semua status</option><option value="OPEN">Menunggu penjual</option><option value="SELLER_RESPONDED">Penjual merespons</option><option value="UNDER_REVIEW">Dalam peninjauan</option><option value="RESOLVED">Selesai</option>
        </Select>
        <DataList rows={disputes.data} columns={columns} rowKey={(row) => row.id} empty={<EmptyState icon={<AlertTriangle className="size-6" />} title="Belum ada komplain" description="Kasus pembeli akan muncul di sini." />} />
        <Pagination meta={disputes} />
        {selected && <DisputeDialog row={selected} canResolve={canResolve} onClose={() => setSelected(null)} />}
    </DashboardLayout>;
}

function DisputeDialog({ row, canResolve, onClose }: { row: DisputeRow; canResolve: boolean; onClose: () => void }) {
    const form = useForm({ winner: 'buyer', note: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post(`/admin/komplain/${row.id}/putuskan`, { preserveScroll: true, onSuccess: onClose }); };
    const open = !['RESOLVED', 'REJECTED', 'CANCELLED'].includes(row.status);
    return <div className="fixed inset-0 z-[90] grid place-items-center overflow-y-auto bg-black/55 p-4 backdrop-blur-sm" onClick={(event) => event.target === event.currentTarget && onClose()}>
        <Card className="w-full max-w-2xl animate-rise p-6">
            <div className="flex items-start justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-wider text-[var(--primary)]">{row.number}</p><h2 className="mt-1 text-xl font-bold">Sengketa {row.order_number}</h2></div><Badge tone="warning">{row.status_label}</Badge></div>
            <div className="mt-5 grid gap-3 sm:grid-cols-2"><div className="rounded-xl bg-surface-2 p-4"><p className="text-xs text-muted">Pembeli</p><p className="mt-1 font-bold">{row.buyer}</p><p className="text-xs text-muted">{row.buyer_email}</p></div><div className="rounded-xl bg-surface-2 p-4"><p className="text-xs text-muted">Logistik</p><p className="mt-1 font-bold">{row.courier || 'Belum ada kurir'}</p><p className="text-xs text-muted">{row.waybill_id || 'Belum ada resi'}</p></div></div>
            <div className="mt-4"><p className="text-xs font-bold uppercase text-muted">Keterangan pembeli</p><p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed">{row.description}</p></div>
            <div className="mt-4"><p className="text-xs font-bold uppercase text-muted">Respons penjual</p><p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed">{row.seller_response || 'Penjual belum memberi respons.'}</p></div>
            {canResolve && open && <form onSubmit={submit} className="mt-6 space-y-3 border-t border-line pt-5"><Select value={form.data.winner} onChange={(event) => form.setData('winner', event.target.value)}><option value="buyer">Menangkan pembeli — buat refund</option><option value="seller">Menangkan penjual — lepaskan dana</option></Select><Textarea rows={4} required minLength={10} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} placeholder="Tuliskan alasan keputusan berbasis bukti." /><Alert tone="warning">Keputusan ini menutup komplain. Jika pembeli menang, refund dibuat dan diteruskan ke antrean finance.</Alert><div className="flex justify-end gap-2"><Button type="button" variant="ghost" onClick={onClose}>Batal</Button><Button type="submit" loading={form.processing}>Simpan keputusan</Button></div></form>}
        </Card>
    </div>;
}
