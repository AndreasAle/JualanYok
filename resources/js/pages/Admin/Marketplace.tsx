import { Link, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Boxes, ExternalLink, Eye, Flag, Megaphone, Search, Sparkles, Store, Tags, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader, Pagination, StatCard } from '@/components/shared';
import { Badge, Button, Card, Field, Input, Select, Textarea } from '@/components/ui';
import { formatIDR } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Listing {
    id: number; name: string; type: string; price: number; thumbnail_url?: string | null;
    status: 'DRAFT' | 'PENDING_REVIEW' | 'APPROVED' | 'REJECTED' | 'SUSPENDED'; status_label: string;
    category?: string | null; reason?: string | null; quality_score: number;
    featured_at?: string | null; featured_until?: string | null;
    creator: { name: string; username: string }; storefront_url: string;
}

const tone = { DRAFT: 'neutral', PENDING_REVIEW: 'warning', APPROVED: 'success', REJECTED: 'danger', SUSPENDED: 'danger' } as const;

export default function MarketplaceAdmin({ products, filters, stats, configuration }: { products: Paginated<Listing>; filters: { q?: string; status?: string }; stats: Record<string, number>; configuration: Record<string, number> }) {
    const [review, setReview] = useState<Listing | null>(null);
    const applyFilters = (updates: Record<string, string | undefined>) => router.get('/admin/marketplace', { ...filters, ...updates }, { preserveState: true, replace: true });

    return <DashboardLayout title="Marketplace" area="admin">
        <PageHeader title="Marketplace Overview" description="Kurasi listing creator tanpa mengubah transaksi, penjualan, ataupun storefront asalnya." actions={<Badge tone="warning">{stats.PENDING_REVIEW ?? 0} perlu direview</Badge>} />
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><StatCard label="Menunggu review" value={stats.PENDING_REVIEW ?? 0} hint="prioritas moderasi" icon={<Eye className="size-4" />} tone="warning" /><StatCard label="Listing tayang" value={stats.APPROVED ?? 0} hint="approved & eligible" icon={<BadgeCheck className="size-4" />} tone="success" /><StatCard label="Kategori aktif" value={configuration.categories} hint={`${configuration.sections} section homepage`} icon={<Tags className="size-4" />} /><StatCard label="Creator unggulan" value={configuration.featured_creators} hint={`${configuration.banners} banner aktif`} icon={<Store className="size-4" />} /></div>

        <Card className="mt-5 p-4 sm:p-5"><div className="flex flex-col gap-3 sm:flex-row"><label className="flex h-11 flex-1 items-center gap-2 rounded-xl border border-line bg-white px-3"><Search className="size-4 text-muted" /><Input defaultValue={filters.q} placeholder="Cari produk atau creator..." className="h-auto border-0 p-0 shadow-none" onKeyDown={(event) => event.key === 'Enter' && applyFilters({ q: event.currentTarget.value || undefined })} /></label><Select value={filters.status ?? ''} onChange={(event) => applyFilters({ status: event.target.value || undefined })} className="sm:w-56"><option value="">Semua status</option><option value="PENDING_REVIEW">Menunggu review</option><option value="APPROVED">Tayang</option><option value="REJECTED">Ditolak</option><option value="SUSPENDED">Ditangguhkan</option></Select></div></Card>

        <div className="mt-5 space-y-3">{products.data.map((product) => <article key={product.id} className="grid gap-4 rounded-2xl border border-line bg-white p-4 shadow-card md:grid-cols-[72px_1fr_auto] md:items-center"><div className="size-[72px] overflow-hidden rounded-xl bg-violet-50">{product.thumbnail_url ? <img src={product.thumbnail_url} alt="" className="size-full object-cover" /> : <Boxes className="m-6 size-6 text-violet-500" />}</div><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><Badge tone={tone[product.status]}>{product.status_label}</Badge><span className="text-[10px] font-bold text-muted">{product.type}</span><span className="text-[10px] font-bold text-muted">Kualitas {product.quality_score}/100</span></div><h2 className="mt-2 truncate text-sm font-extrabold">{product.name}</h2><p className="mt-1 text-xs text-muted">{product.creator.name} · @{product.creator.username} · {product.category ?? 'Tanpa kategori'} · {formatIDR(product.price)}</p>{product.reason && <p className="mt-2 text-xs text-rose-600">Alasan terakhir: {product.reason}</p>}</div><div className="flex flex-wrap items-center gap-2 md:justify-end"><Link href={product.storefront_url} target="_blank" className="inline-flex h-9 items-center gap-1 rounded-xl border border-line px-3 text-[11px] font-bold">Lihat <ExternalLink className="size-3" /></Link>{product.status === 'APPROVED' && <button onClick={() => router.post(`/admin/marketplace/produk/${product.id}/unggulkan`, {})} className="inline-flex h-9 items-center gap-1 rounded-xl border border-violet-200 px-3 text-[11px] font-bold text-violet-700"><Sparkles className="size-3" /> Unggulkan</button>}<Button size="sm" onClick={() => setReview(product)}>{product.status === 'PENDING_REVIEW' ? 'Review' : 'Ubah status'}</Button></div></article>)}{products.data.length === 0 && <div className="rounded-2xl border border-dashed border-line bg-white p-16 text-center"><Flag className="mx-auto size-8 text-muted" /><p className="mt-3 font-extrabold">Tidak ada listing pada filter ini</p></div>}</div>
        <Pagination meta={products} />
        {review && <ReviewDialog product={review} close={() => setReview(null)} />}
    </DashboardLayout>;
}

function ReviewDialog({ product, close }: { product: Listing; close: () => void }) {
    const form = useForm({ decision: 'approve', reason: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post(`/admin/marketplace/produk/${product.id}/moderasi`, { preserveScroll: true, onSuccess: close }); };
    return <div className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm" onClick={(event) => event.target === event.currentTarget && close()}><Card className="w-full max-w-lg p-6"><div className="flex items-start justify-between gap-3"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-violet-600">Moderasi produk</p><h2 className="mt-2 text-xl font-black">{product.name}</h2><p className="mt-1 text-xs text-muted">@{product.creator.username}</p></div><Button variant="ghost" size="sm" onClick={close}>Tutup</Button></div><form onSubmit={submit} className="mt-6 space-y-4"><Field label="Keputusan" error={form.errors.decision}><Select value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}><option value="approve">Setujui dan tayangkan</option><option value="reject">Tolak untuk diperbaiki</option><option value="suspend">Tangguhkan listing</option></Select></Field>{form.data.decision !== 'approve' && <Field label="Alasan yang jelas" required error={form.errors.reason}><Textarea value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} placeholder="Jelaskan bagian yang perlu diperbaiki atau dasar penangguhan..." /></Field>}<div className="rounded-xl bg-surface-2 p-3 text-xs leading-5 text-muted">Keputusan ini hanya mengubah distribusi marketplace. Produk dan histori transaksi creator tidak dihapus.</div><Button type="submit" block loading={form.processing}>{form.data.decision === 'approve' ? <><BadgeCheck className="size-4" /> Setujui listing</> : form.data.decision === 'reject' ? <><XCircle className="size-4" /> Tolak listing</> : <><Megaphone className="size-4" /> Tangguhkan</>}</Button></form></Card></div>;
}
