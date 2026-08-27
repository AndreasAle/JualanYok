import { useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, MapPin, Search, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Select, Switch, Textarea } from '@/components/ui';

type Area = { id: string; name: string; postal_code?: string | number; administrative_division_level_1_name?: string; administrative_division_level_2_name?: string; administrative_division_level_3_name?: string };

const courierNames: Record<string, string> = {
    jne: 'JNE', sicepat: 'SiCepat', anteraja: 'AnterAja', jnt: 'J&T Express', ninja: 'Ninja Xpress', tiki: 'TIKI', pos: 'Pos Indonesia', grab: 'GrabExpress', gojek: 'GoSend', paxel: 'Paxel', lion: 'Lion Parcel', idexpress: 'ID Express',
};

export default function ShippingEdit({ profile, provider }: { profile: any; provider: { name: string; ready: boolean; couriers: string[] } }) {
    const [query, setQuery] = useState(''); const [areas, setAreas] = useState<Area[]>([]); const [searching, setSearching] = useState(false); const [searchError, setSearchError] = useState('');
    const { data, setData, put, processing, errors } = useForm({
        contact_name: profile?.contact_name ?? '', contact_phone: profile?.contact_phone ?? '', contact_email: profile?.contact_email ?? '', address_line: profile?.address_line ?? '', district: profile?.district ?? '', city: profile?.city ?? '', province: profile?.province ?? '', postal_code: profile?.postal_code ?? '', area_id: profile?.area_id ?? '', note: profile?.note ?? '', collection_method: profile?.collection_method ?? 'pickup', enabled_couriers: profile?.enabled_couriers ?? provider.couriers, default_insurance: profile?.default_insurance ?? false, is_active: profile?.is_active ?? true,
    });
    const findAreas = async () => { if (query.trim().length < 3) return; setSearching(true); setSearchError(''); try { const response = await fetch(`/dashboard/pengiriman/area?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } }); const body = await response.json(); if (!response.ok) throw new Error(body.message ?? 'Area tidak ditemukan.'); setAreas(body.areas ?? []); } catch (error) { setSearchError(error instanceof Error ? error.message : 'Gagal mencari area.'); } finally { setSearching(false); } };
    const choose = (area: Area) => { setData({ ...data, area_id: area.id, district: area.administrative_division_level_3_name ?? area.name, city: area.administrative_division_level_2_name ?? '', province: area.administrative_division_level_1_name ?? '', postal_code: area.postal_code == null ? '' : String(area.postal_code) }); setQuery(area.name); setAreas([]); };
    const toggleCourier = (courier: string) => setData('enabled_couriers', data.enabled_couriers.includes(courier) ? data.enabled_couriers.filter((item: string) => item !== courier) : [...data.enabled_couriers, courier]);
    const submit = (event: FormEvent) => { event.preventDefault(); put('/dashboard/pengiriman', { preserveScroll: true }); };
    return <DashboardLayout title="Pengiriman" area="creator">
        <PageHeader title="Pusat Pengiriman" description="Atur gudang, kurir, pickup, dan resi dari satu tempat." breadcrumbs={[{ label: 'Pesanan', href: '/dashboard/pesanan' }, { label: 'Pengiriman' }]} />
        <div className="grid gap-4 xl:grid-cols-[1.4fr_.8fr]">
            <form onSubmit={submit}><Card><CardHeader><CardTitle>Alamat asal paket</CardTitle></CardHeader><CardBody className="space-y-4">
                {!provider.ready && <Alert tone="warning" title="Biteship belum aktif">Isi BITESHIP_API_KEY di server sebelum menawarkan tarif kurir.</Alert>}
                <div className="grid gap-4 sm:grid-cols-2"><Field label="Nama pengirim" required error={errors.contact_name}><Input value={data.contact_name} onChange={(e) => setData('contact_name', e.target.value)} /></Field><Field label="Nomor pengirim" required error={errors.contact_phone}><Input value={data.contact_phone} onChange={(e) => setData('contact_phone', e.target.value)} /></Field></div>
                <Field label="Email operasional" error={errors.contact_email}><Input type="email" value={data.contact_email} onChange={(e) => setData('contact_email', e.target.value)} /></Field>
                {provider.name === 'biteship' && <Field label="Cari kecamatan gudang" required error={errors.area_id}><div className="flex gap-2"><Input value={query} onChange={(e) => { setQuery(e.target.value); setData('area_id', ''); }} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); void findAreas(); } }} placeholder="Kecamatan atau kode pos" /><Button type="button" variant="outline" onClick={() => void findAreas()}>{searching ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}</Button></div></Field>}
                {searchError && <Alert tone="danger">{searchError}</Alert>}{areas.length > 0 && <div className="max-h-48 overflow-y-auto rounded-xl border border-line p-1">{areas.map((area) => <button type="button" key={area.id} onClick={() => choose(area)} className="block w-full rounded-lg p-3 text-left text-sm font-semibold hover:bg-surface-2">{area.name}</button>)}</div>}
                {data.area_id && <Alert tone="success" title="Area kurir terhubung"><span className="text-xs">{data.district}, {data.city}, {data.province}</span></Alert>}
                <Field label="Alamat lengkap" required error={errors.address_line}><Textarea rows={3} value={data.address_line} onChange={(e) => setData('address_line', e.target.value)} placeholder="Jalan, nomor bangunan, RT/RW, patokan" /></Field>
                <div className="grid gap-4 sm:grid-cols-3"><Field label="Kota" required error={errors.city}><Input value={data.city} onChange={(e) => setData('city', e.target.value)} /></Field><Field label="Provinsi" required error={errors.province}><Input value={data.province} onChange={(e) => setData('province', e.target.value)} /></Field><Field label="Kode pos" required error={errors.postal_code}><Input value={data.postal_code} onChange={(e) => setData('postal_code', e.target.value)} /></Field></div>
                <Field label="Catatan pickup"><Textarea rows={2} value={data.note} onChange={(e) => setData('note', e.target.value)} placeholder="Masuk dari gerbang samping" /></Field>
                <Field label="Serah terima paket"><Select value={data.collection_method} onChange={(e) => setData('collection_method', e.target.value)}><option value="pickup">Kurir jemput ke alamat</option><option value="drop_off">Antar ke gerai/drop point</option></Select></Field>
                {provider.name === 'biteship' && <Field label="Kurir yang ditawarkan" required error={errors.enabled_couriers}><div className="grid grid-cols-2 gap-2 sm:grid-cols-3">{provider.couriers.map((courier) => { const active = data.enabled_couriers.includes(courier); return <button key={courier} type="button" aria-pressed={active} onClick={() => toggleCourier(courier)} className={`flex items-center justify-between rounded-xl border px-3 py-2.5 text-left text-sm font-bold transition ${active ? 'border-violet-500 bg-violet-50 text-violet-800 ring-1 ring-violet-500' : 'border-line bg-white text-ink hover:bg-surface-2'}`}><span>{courierNames[courier] ?? courier.toUpperCase()}</span>{active && <CheckCircle2 className="size-4" />}</button>; })}</div><p className="mt-2 text-xs text-muted">Pembeli hanya melihat layanan aktif dari kurir yang kamu pilih.</p></Field>}
                <Switch checked={data.default_insurance} onChange={(value) => setData('default_insurance', value)} label="Asuransi otomatis" description="Gunakan asuransi saat layanan kurir mendukungnya." />
                <Button type="submit" variant="gradient" loading={processing}><MapPin className="size-4" /> Simpan alamat pengiriman</Button>
            </CardBody></Card></form>
            <div className="space-y-4"><Card><CardHeader><CardTitle>Status logistik</CardTitle></CardHeader><CardBody className="space-y-3"><div className="flex items-center gap-3 rounded-xl bg-surface-2 p-4"><span className="grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700">{provider.ready ? <CheckCircle2 className="size-5" /> : <Truck className="size-5" />}</span><div><p className="font-extrabold capitalize">{provider.name}</p><p className="text-xs text-muted">{provider.ready ? 'Siap menerima tarif dan booking kurir.' : 'Menunggu kredensial server.'}</p></div></div></CardBody></Card>
            </div>
        </div>
    </DashboardLayout>;
}
