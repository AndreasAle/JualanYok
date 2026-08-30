import { useForm } from '@inertiajs/react';
import { Check, Clock3, Copy, ExternalLink, MapPin, Package, Search, Store, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import MarketingLayout from '@/layouts/MarketingLayout';
import { Button, Card, Input } from '@/components/ui';
import { cn } from '@/lib/utils';

interface TrackingEvent {
    stage: string;
    title: string;
    description: string | null;
    location: string | null;
    source: 'system' | 'creator' | 'courier';
    occurred_at: string;
}

interface TrackingPayload {
    tracking_code: string;
    order_number: string;
    status: string;
    status_label: string;
    progress: number;
    created_at: string;
    last_updated_at: string | null;
    buyer_first_name: string;
    store: { name: string; username: string; avatar_url: string | null; url: string };
    items: Array<{ name: string; variant_name: string | null; quantity: number; thumbnail_url: string | null }>;
    shipment: null | {
        courier: string | null;
        service: string | null;
        waybill_id: string | null;
        tracking_url: string | null;
        driver_name: string | null;
        driver_photo_url: string | null;
        driver_plate_number: string | null;
    };
    timeline: TrackingEvent[];
}

export default function TrackOrder({ tracking }: { tracking: TrackingPayload | null }) {
    const form = useForm({ tracking_code: tracking?.tracking_code ?? '' });
    const [copied, setCopied] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/lacak', { preserveScroll: true });
    };

    const copy = async () => {
        if (!tracking) return;
        await navigator.clipboard.writeText(tracking.tracking_code);
        setCopied(true);
        setTimeout(() => setCopied(false), 1800);
    };

    return <MarketingLayout title="Lacak Barangmu" description="Pantau proses pesanan dan perjalanan paket JualanYok dengan ID pembelian.">
        <main className="mx-auto max-w-5xl px-4 pb-24 pt-12 sm:px-6 sm:pt-16">
            <section className="mx-auto max-w-2xl text-center">
                <span className="inline-flex items-center gap-2 rounded-full bg-violet-100 px-3 py-1.5 text-xs font-black text-violet-700"><Truck className="size-4" /> TRACKING PESANAN</span>
                <h1 className="mt-5 text-4xl font-black tracking-[-.045em] sm:text-5xl">Lacak Barangmu</h1>
                <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-muted sm:text-base">Masukkan ID pembelian yang tampil setelah pembayaran dan dikirim ke emailmu. Kami akan menampilkan proses penjual sampai checkpoint resmi kurir.</p>

                <form onSubmit={submit} className="mt-7 rounded-2xl border border-black/[.07] bg-white p-2 shadow-[0_18px_50px_rgba(34,24,70,.10)]">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input value={form.data.tracking_code} onChange={(event) => form.setData('tracking_code', event.target.value.toUpperCase())} placeholder="Contoh: JYT-AB12CD34EF56GH78" className="h-12 flex-1 border-0 bg-[#f7f6fb] font-mono uppercase" aria-label="ID pembelian" required />
                        <Button type="submit" variant="gradient" className="h-12" loading={form.processing}><Search className="size-4" /> Lacak sekarang</Button>
                    </div>
                </form>
                {form.errors.tracking_code && <p className="mt-3 text-sm font-semibold text-rose-600">{form.errors.tracking_code}</p>}
            </section>

            {tracking && <div className="mt-10 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card className="overflow-hidden">
                    <div className="bg-gradient-to-r from-[#171722] to-violet-900 p-6 text-white sm:p-7">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div><p className="text-[10px] font-black uppercase tracking-[.16em] text-white/55">Status terkini</p><h2 className="mt-2 text-2xl font-black">{tracking.status_label}</h2><p className="mt-1 text-sm text-white/65">Pesanan {tracking.order_number}</p></div>
                            <span className="rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold">{tracking.progress}%</span>
                        </div>
                        <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/15"><span className="block h-full rounded-full bg-gradient-to-r from-violet-400 to-orange-400 transition-all" style={{ width: `${tracking.progress}%` }} /></div>
                    </div>

                    <div className="p-5 sm:p-7">
                        <h3 className="font-black">Perjalanan pesanan</h3>
                        <ol className="mt-6">
                            {[...tracking.timeline].reverse().map((event, index, list) => <li key={`${event.stage}-${event.occurred_at}-${index}`} className="relative flex gap-4 pb-6 last:pb-0">
                                {index < list.length - 1 && <span className="absolute left-[11px] top-6 h-full w-px bg-violet-200" />}
                                <span className={cn('relative mt-0.5 grid size-6 shrink-0 place-items-center rounded-full ring-4', index === 0 ? 'bg-violet-600 text-white ring-violet-100' : 'bg-white text-violet-600 ring-violet-50')}><Check className="size-3.5" /></span>
                                <div className="min-w-0"><p className="text-sm font-extrabold">{event.title}</p>{event.description && <p className="mt-1 text-xs leading-5 text-muted">{event.description}</p>}<p className="mt-1.5 flex flex-wrap items-center gap-2 text-[10px] font-semibold text-muted">{event.location && <span className="inline-flex items-center gap-1"><MapPin className="size-3" />{event.location}</span>}<span>{new Date(event.occurred_at).toLocaleString('id-ID')}</span><span className="rounded-full bg-surface-2 px-1.5 py-0.5">{event.source === 'courier' ? 'Kurir' : event.source === 'creator' ? 'Penjual' : 'JualanYok'}</span></p></div>
                            </li>)}
                        </ol>
                    </div>
                </Card>

                <aside className="space-y-5">
                    <Card className="p-5"><p className="text-[10px] font-black uppercase tracking-wider text-muted">ID pembelian</p><div className="mt-2 flex items-center gap-2"><code className="min-w-0 flex-1 truncate text-sm font-black">{tracking.tracking_code}</code><button type="button" onClick={copy} className="grid size-9 place-items-center rounded-xl border border-line" aria-label="Salin ID">{copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}</button></div><p className="mt-3 flex items-start gap-2 text-[11px] leading-5 text-muted"><Clock3 className="mt-0.5 size-3.5 shrink-0" />Terakhir diperbarui {tracking.last_updated_at ? new Date(tracking.last_updated_at).toLocaleString('id-ID') : '-'}</p></Card>

                    <Card className="p-5"><div className="flex items-center gap-3"><span className="grid size-11 place-items-center overflow-hidden rounded-xl bg-violet-100">{tracking.store.avatar_url ? <img src={tracking.store.avatar_url} alt="" className="size-full object-cover" /> : <Store className="size-5 text-violet-700" />}</span><div><p className="font-black">{tracking.store.name}</p><a href={tracking.store.url} className="text-xs font-bold text-violet-600">Kunjungi toko</a></div></div></Card>

                    {tracking.shipment && <Card className="p-5"><p className="text-[10px] font-black uppercase tracking-wider text-muted">Ekspedisi</p><p className="mt-2 font-black">{tracking.shipment.courier || 'Kurir'}{tracking.shipment.service ? ` · ${tracking.shipment.service}` : ''}</p>{tracking.shipment.waybill_id && <p className="mt-1 font-mono text-xs text-muted">Resi {tracking.shipment.waybill_id}</p>}{tracking.shipment.driver_name && <div className="mt-4 flex items-center gap-3 rounded-xl bg-surface-2 p-3">{tracking.shipment.driver_photo_url ? <img src={tracking.shipment.driver_photo_url} alt="" className="size-10 rounded-full object-cover" /> : <Truck className="size-5" />}<div><p className="text-xs font-black">{tracking.shipment.driver_name}</p>{tracking.shipment.driver_plate_number && <p className="text-[10px] text-muted">{tracking.shipment.driver_plate_number}</p>}</div></div>}{tracking.shipment.tracking_url && <a href={tracking.shipment.tracking_url} target="_blank" rel="noreferrer" className="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-line text-xs font-black">Tracking resmi kurir <ExternalLink className="size-3.5" /></a>}</Card>}

                    <Card className="p-5"><p className="text-[10px] font-black uppercase tracking-wider text-muted">Isi pesanan</p><ul className="mt-3 space-y-3">{tracking.items.map((item, index) => <li key={`${item.name}-${index}`} className="flex items-center gap-3">{item.thumbnail_url ? <img src={item.thumbnail_url} alt="" className="size-11 rounded-xl object-cover" /> : <span className="grid size-11 place-items-center rounded-xl bg-surface-2"><Package className="size-4" /></span>}<div className="min-w-0"><p className="truncate text-xs font-black">{item.name}</p><p className="text-[10px] text-muted">{item.variant_name || 'Produk'} · {item.quantity} item</p></div></li>)}</ul></Card>
                </aside>
            </div>}
        </main>
    </MarketingLayout>;
}
