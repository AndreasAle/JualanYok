import { Link, router, useForm } from '@inertiajs/react';
import { Archive, Bell, Check, CheckCheck, ChevronRight, Clock3, Mail, Settings2, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import DashboardLayout, { type DashboardArea } from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Button, Card, Select } from '@/components/ui';
import { cn } from '@/lib/utils';
import type { NotificationItem, Paginated } from '@/types';

interface CategoryOption { value: string; label: string }
interface Preference { category: string; label: string; description: string; email_frequency: 'immediate' | 'daily' | 'off'; email_locked: boolean }

const toneClass: Record<string, string> = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    danger: 'border-rose-200 bg-rose-50 text-rose-700',
    info: 'border-sky-200 bg-sky-50 text-sky-700',
};

export default function NotificationIndex({
    items,
    area,
    filters,
    stats,
    categories,
    preferences,
}: {
    items: Paginated<NotificationItem>;
    area: DashboardArea;
    filters: { category?: string; status?: string };
    stats: { all: number; unread: number; action: number };
    categories: CategoryOption[];
    preferences: Preference[];
}) {
    const form = useForm({
        preferences: preferences.map((preference) => ({
            category: preference.category,
            email_frequency: preference.email_frequency,
        })),
    });

    const filter = (updates: Record<string, string | undefined>) => router.get('/notifikasi', {
        area,
        category: filters.category || undefined,
        status: filters.status || undefined,
        ...updates,
    }, { preserveState: true, replace: true });

    const updatePreference = (category: string, value: 'immediate' | 'daily' | 'off') => {
        form.setData('preferences', form.data.preferences.map((preference) =>
            preference.category === category ? { ...preference, email_frequency: value } : preference
        ));
    };

    const savePreferences = () => form.put('/notifikasi/preferensi/email', { preserveScroll: true });

    return <DashboardLayout title="Notifikasi" area={area}>
        <PageHeader title="Notifikasi" description="Semua pembaruan penting, tindakan, dan preferensi email dalam satu tempat." />

        <div className="grid gap-4 sm:grid-cols-3">
            <Stat label="Semua" value={stats.all} icon={<Bell className="size-4" />} active={!filters.status} onClick={() => filter({ status: undefined })} />
            <Stat label="Belum dibaca" value={stats.unread} icon={<Clock3 className="size-4" />} active={filters.status === 'unread'} onClick={() => filter({ status: 'unread' })} />
            <Stat label="Perlu tindakan" value={stats.action} icon={<TriangleAlert className="size-4" />} active={filters.status === 'action'} onClick={() => filter({ status: 'action' })} warning />
        </div>

        <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section>
                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center">
                        <div><h2 className="font-extrabold">Kotak masuk</h2><p className="mt-0.5 text-xs text-muted">Klik notifikasi untuk membuka halaman yang berkaitan.</p></div>
                        <div className="flex gap-2 sm:ml-auto">
                            <Select value={filters.category ?? ''} onChange={(event) => filter({ category: event.target.value || undefined })} className="min-w-40">
                                <option value="">Semua kategori</option>
                                {categories.map((category) => <option key={category.value} value={category.value}>{category.label}</option>)}
                            </Select>
                            {stats.unread > 0 && <Button variant="outline" size="sm" onClick={() => router.post('/notifikasi/tandai-semua-dibaca', {}, { preserveScroll: true })}><CheckCheck className="size-4" /> Baca semua</Button>}
                        </div>
                    </div>

                    {items.data.length === 0 ? <div className="px-6 py-20 text-center"><span className="mx-auto grid size-14 place-items-center rounded-2xl bg-surface-2 text-muted"><Bell className="size-6" /></span><h3 className="mt-4 font-extrabold">Tidak ada notifikasi</h3><p className="mt-1 text-sm text-muted">Semua sudah beres untuk filter ini.</p></div> : <div className="divide-y divide-line">
                        {items.data.map((item) => <article key={item.id} className={cn('p-4 transition sm:p-5', !item.is_read && 'bg-violet-50/45')}>
                            <div className="flex gap-3">
                                <span className={cn('mt-0.5 grid size-10 shrink-0 place-items-center rounded-xl border', toneClass[item.tone] ?? toneClass.info)}>{item.action_required && !item.is_resolved ? <TriangleAlert className="size-4" /> : <Bell className="size-4" />}</span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-start gap-2"><h3 className="flex-1 text-sm font-extrabold">{item.title}</h3><span className="text-[10px] text-muted">{item.created_at_human}</span></div>
                                    <p className="mt-1 text-xs leading-5 text-muted">{item.message}</p>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <a href={item.open_url} className="inline-flex h-9 items-center gap-1 rounded-xl bg-[#171722] px-3 text-[11px] font-extrabold text-white">{item.action_label || 'Lihat detail'} <ChevronRight className="size-3.5" /></a>
                                        {!item.is_read && <button type="button" onClick={() => router.patch(`/notifikasi/${item.id}/baca`, {}, { preserveScroll: true })} className="inline-flex h-9 items-center gap-1 rounded-xl border border-line px-3 text-[11px] font-bold"><Check className="size-3.5" /> Tandai dibaca</button>}
                                        {item.action_required && !item.is_resolved && <button type="button" onClick={() => router.patch(`/notifikasi/${item.id}/selesai`, {}, { preserveScroll: true })} className="inline-flex h-9 items-center gap-1 rounded-xl border border-emerald-200 px-3 text-[11px] font-bold text-emerald-700"><CheckCheck className="size-3.5" /> Tandai selesai</button>}
                                        <button type="button" onClick={() => router.patch(`/notifikasi/${item.id}/arsip`, {}, { preserveScroll: true })} className="ml-auto inline-flex h-9 items-center gap-1 rounded-xl px-3 text-[11px] font-bold text-muted hover:bg-surface-2"><Archive className="size-3.5" /> Arsipkan</button>
                                    </div>
                                </div>
                            </div>
                        </article>)}
                    </div>}

                    {items.last_page > 1 && <div className="flex flex-wrap justify-center gap-1 border-t border-line p-4">{items.links.map((link, index) => link.url ? <Link key={index} href={link.url} preserveState className={cn('rounded-lg px-3 py-2 text-xs font-bold', link.active ? 'bg-violet-600 text-white' : 'hover:bg-surface-2')} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="px-3 py-2 text-xs text-muted" dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
                </Card>
            </section>

            <aside>
                <Card className="p-5 xl:sticky xl:top-24">
                    <div className="flex items-start gap-3"><span className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-700"><Settings2 className="size-5" /></span><div><h2 className="font-extrabold">Preferensi email</h2><p className="mt-1 text-xs leading-5 text-muted">Semua kejadian tetap tersimpan di lonceng. Pilih kapan email perlu dikirim.</p></div></div>
                    <div className="mt-5 space-y-3">
                        {preferences.map((preference) => {
                            const current = form.data.preferences.find((item) => item.category === preference.category)?.email_frequency ?? preference.email_frequency;
                            return <div key={preference.category} className="rounded-xl border border-line p-3.5"><div className="flex gap-3"><Mail className="mt-0.5 size-4 shrink-0 text-violet-600" /><div className="min-w-0 flex-1"><p className="text-xs font-extrabold">{preference.label}</p><p className="mt-1 text-[10px] leading-4 text-muted">{preference.description}</p></div></div><Select value={current} disabled={preference.email_locked} onChange={(event) => updatePreference(preference.category, event.target.value as 'immediate' | 'daily' | 'off')} className="mt-3 w-full"><option value="immediate">Email langsung</option><option value="daily">Ringkasan harian</option><option value="off">Tidak kirim email</option></Select>{preference.email_locked && <p className="mt-2 text-[9px] font-semibold text-amber-700">Email wajib untuk keamanan transaksi dan akun.</p>}</div>;
                        })}
                    </div>
                    <Button block variant="gradient" className="mt-5" loading={form.processing} onClick={savePreferences}>Simpan preferensi</Button>
                </Card>
            </aside>
        </div>
    </DashboardLayout>;
}

function Stat({ label, value, icon, active, onClick, warning = false }: { label: string; value: number; icon: ReactNode; active: boolean; onClick: () => void; warning?: boolean }) {
    return <button type="button" onClick={onClick} className={cn('flex items-center gap-4 rounded-2xl border bg-white p-4 text-left shadow-card transition hover:-translate-y-0.5', active ? warning ? 'border-amber-300 ring-2 ring-amber-100' : 'border-violet-300 ring-2 ring-violet-100' : 'border-line')}><span className={cn('grid size-10 place-items-center rounded-xl', warning ? 'bg-amber-100 text-amber-700' : 'bg-violet-100 text-violet-700')}>{icon}</span><span><span className="block text-2xl font-black">{value}</span><span className="text-xs font-semibold text-muted">{label}</span></span></button>;
}
