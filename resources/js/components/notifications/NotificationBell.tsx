import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CheckCheck, CircleDollarSign, Package, ShieldCheck, ShoppingBag, Truck, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui';
import { cn } from '@/lib/utils';
import type { NotificationItem, PageProps } from '@/types';

type DashboardArea = 'creator' | 'admin' | 'member' | 'affiliate';

const toneClass: Record<string, string> = {
    success: 'bg-emerald-50 text-emerald-600',
    warning: 'bg-amber-50 text-amber-700',
    danger: 'bg-rose-50 text-rose-600',
    info: 'bg-sky-50 text-sky-600',
};

function NotificationIcon({ item }: { item: NotificationItem }) {
    const className = 'size-4';
    if (item.category === 'orders') return <ShoppingBag className={className} />;
    if (item.category === 'shipping') return <Truck className={className} />;
    if (item.category === 'inventory') return <Package className={className} />;
    if (item.category === 'finance' || item.category === 'payments') return <CircleDollarSign className={className} />;
    if (item.category === 'security') return <ShieldCheck className={className} />;
    return <AlertTriangle className={className} />;
}

export default function NotificationBell({ area }: { area: DashboardArea }) {
    const { notifications } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState<'all' | 'action'>('all');
    const root = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (root.current && !root.current.contains(event.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    useEffect(() => {
        const poll = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['notifications'] });
            }
        }, Math.max(20, notifications.poll_seconds) * 1000);
        return () => window.clearInterval(poll);
    }, [notifications.poll_seconds]);

    const items = useMemo(
        () => tab === 'action' ? notifications.items.filter((item) => item.action_required && !item.is_resolved) : notifications.items,
        [notifications.items, tab],
    );

    const markAllRead = () => router.post(notifications.read_all_url, {}, {
        preserveScroll: true,
        onSuccess: () => setOpen(false),
    });

    return <div ref={root} className="relative">
        <Button
            variant="ghost"
            size="icon"
            className="relative rounded-full"
            onClick={() => setOpen((value) => !value)}
            aria-label={`${notifications.unread_count} notifikasi belum dibaca`}
            aria-expanded={open}
        >
            <Bell className="size-5" />
            {notifications.unread_count > 0 && <span className="absolute -right-0.5 -top-0.5 grid min-w-4.5 place-items-center rounded-full bg-rose-500 px-1 text-[9px] font-black leading-[18px] text-white ring-2 ring-white">
                {notifications.unread_count > 99 ? '99+' : notifications.unread_count}
            </span>}
        </Button>

        {open && <div className="absolute right-0 top-12 z-50 w-[min(23rem,calc(100vw-1.5rem))] overflow-hidden rounded-2xl border border-line bg-surface shadow-lift">
            <div className="flex items-start justify-between border-b border-line px-4 py-3.5">
                <div><p className="text-sm font-extrabold">Notifikasi</p><p className="mt-0.5 text-[11px] text-muted">{notifications.unread_count} belum dibaca · {notifications.action_count} perlu tindakan</p></div>
                <button type="button" onClick={() => setOpen(false)} className="grid size-8 place-items-center rounded-full hover:bg-surface-2" aria-label="Tutup"><X className="size-4" /></button>
            </div>
            <div className="flex items-center gap-1 border-b border-line px-3 py-2">
                <button type="button" onClick={() => setTab('all')} className={cn('rounded-full px-3 py-1.5 text-[11px] font-bold', tab === 'all' ? 'bg-violet-100 text-violet-700' : 'text-muted')}>Semua</button>
                <button type="button" onClick={() => setTab('action')} className={cn('rounded-full px-3 py-1.5 text-[11px] font-bold', tab === 'action' ? 'bg-amber-100 text-amber-800' : 'text-muted')}>Perlu tindakan</button>
                {notifications.unread_count > 0 && <button type="button" onClick={markAllRead} className="ml-auto flex items-center gap-1 text-[10px] font-bold text-violet-700"><CheckCheck className="size-3.5" /> Baca semua</button>}
            </div>
            {items.length === 0 ? <div className="px-6 py-10 text-center"><span className="mx-auto grid size-11 place-items-center rounded-2xl bg-surface-2 text-muted"><Bell className="size-5" /></span><p className="mt-3 text-sm font-bold">Sudah beres</p><p className="mt-1 text-xs text-muted">Tidak ada notifikasi pada bagian ini.</p></div> : <ul className="max-h-[25rem] overflow-y-auto">
                {items.map((item) => <li key={item.id} className="border-b border-line last:border-0">
                    <a href={item.open_url} className="flex gap-3 px-4 py-3 transition hover:bg-surface-2">
                        <span className={cn('mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl', toneClass[item.tone] ?? toneClass.info)}><NotificationIcon item={item} /></span>
                        <span className="min-w-0 flex-1"><span className="flex items-start gap-2"><span className="line-clamp-1 flex-1 text-xs font-extrabold">{item.title}</span>{item.action_required && !item.is_resolved && <span className="mt-1 size-2 shrink-0 rounded-full bg-amber-500" />}</span><span className="mt-1 line-clamp-2 block text-[11px] leading-4 text-muted">{item.message}</span><span className="mt-1.5 flex items-center gap-2 text-[9px] font-semibold text-muted"><span>{item.created_at_human}</span>{(item.group_count ?? 1) > 1 && <span className="rounded-full bg-surface-2 px-1.5 py-0.5">{item.group_count} pembaruan</span>}</span></span>
                    </a>
                </li>)}
            </ul>}
            <Link href={`${notifications.index_url}?area=${area}`} onClick={() => setOpen(false)} className="block border-t border-line px-4 py-3 text-center text-xs font-extrabold text-violet-700 hover:bg-violet-50">Lihat semua & atur email</Link>
        </div>}
    </div>;
}
