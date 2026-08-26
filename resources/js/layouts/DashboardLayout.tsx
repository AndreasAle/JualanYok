import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, BarChart3, Bell, Blocks, Boxes, ChevronLeft, CreditCard, ExternalLink, Eye, Gauge, Gift,
    Handshake, LayoutGrid, LifeBuoy, LogOut, Menu, Package, PieChart, Plug, QrCode, Receipt, Settings,
    Search, ShieldCheck, ShoppingBag, Store, Ticket, Truck, UserCircle, Users, Wallet, X,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge, Button } from '@/components/ui';
import { cn, initials } from '@/lib/utils';
import type { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    /** Shown in the mobile bottom bar. */
    primary?: boolean;
}

const CREATOR_NAV: { group: string; items: NavItem[] }[] = [
    {
        group: 'Toko',
        items: [
            { label: 'Ringkasan', href: '/dashboard', icon: <Gauge className="size-4.5" />, primary: true },
            { label: 'Atur Tampilan', href: '/dashboard/toko', icon: <Blocks className="size-4.5" />, primary: true },
            { label: 'Produk', href: '/dashboard/produk', icon: <Package className="size-4.5" />, primary: true },
        ],
    },
    {
        group: 'Penjualan',
        items: [
            { label: 'Pesanan', href: '/dashboard/pesanan', icon: <ShoppingBag className="size-4.5" />, primary: true },
            { label: 'Pengiriman', href: '/dashboard/pengiriman', icon: <Truck className="size-4.5" /> },
            { label: 'Pelanggan', href: '/dashboard/pelanggan', icon: <Users className="size-4.5" /> },
            { label: 'Leads', href: '/dashboard/leads', icon: <Gift className="size-4.5" /> },
        ],
    },
    {
        group: 'Tumbuh',
        items: [
            { label: 'Kupon', href: '/dashboard/kupon', icon: <Ticket className="size-4.5" /> },
            { label: 'Affiliate', href: '/dashboard/affiliate', icon: <Handshake className="size-4.5" /> },
            { label: 'Cari Produk Affiliate', href: '/affiliate/marketplace', icon: <Boxes className="size-4.5" /> },
            { label: 'Analitik', href: '/dashboard/analitik', icon: <BarChart3 className="size-4.5" /> },
            { label: 'Integrasi', href: '/dashboard/integrasi', icon: <Plug className="size-4.5" /> },
        ],
    },
    {
        group: 'Uang',
        items: [
            { label: 'Saldo', href: '/dashboard/saldo', icon: <Wallet className="size-4.5" /> },
            { label: 'Penarikan', href: '/dashboard/penarikan', icon: <CreditCard className="size-4.5" /> },
            { label: 'Langganan', href: '/dashboard/langganan', icon: <Receipt className="size-4.5" /> },
        ],
    },
    {
        group: 'Lainnya',
        items: [
            { label: 'Pengaturan', href: '/dashboard/pengaturan', icon: <Settings className="size-4.5" /> },
            { label: 'Bantuan', href: '/contact', icon: <LifeBuoy className="size-4.5" /> },
        ],
    },
];

const ADMIN_NAV: { group: string; items: NavItem[] }[] = [
    {
        group: 'Ringkasan',
        items: [{ label: 'Dashboard', href: '/admin', icon: <Gauge className="size-4.5" />, primary: true }],
    },
    {
        group: 'Komunitas',
        items: [
            { label: 'Pengguna', href: '/admin/pengguna', icon: <Users className="size-4.5" />, primary: true },
            { label: 'Toko', href: '/admin/toko', icon: <Store className="size-4.5" />, primary: true },
        ],
    },
    {
        group: 'Transaksi',
        items: [
            { label: 'Pesanan', href: '/admin/pesanan', icon: <ShoppingBag className="size-4.5" />, primary: true },
            { label: 'Refund', href: '/admin/refund', icon: <Receipt className="size-4.5" /> },
            { label: 'Komplain', href: '/admin/komplain', icon: <AlertTriangle className="size-4.5" /> },
            { label: 'Penarikan', href: '/admin/penarikan', icon: <CreditCard className="size-4.5" />, primary: true },
            { label: 'Ledger', href: '/admin/ledger', icon: <PieChart className="size-4.5" /> },
        ],
    },
    {
        group: 'Platform',
        items: [
            { label: 'Paket', href: '/admin/paket', icon: <LayoutGrid className="size-4.5" /> },
            { label: 'Bayar Langganan', href: '/admin/pembayaran-langganan', icon: <QrCode className="size-4.5" />, primary: true },
            { label: 'Bayar Pesanan', href: '/admin/pembayaran-qris', icon: <ShoppingBag className="size-4.5" />, primary: true },
            { label: 'Pengaturan', href: '/admin/pengaturan', icon: <Settings className="size-4.5" /> },
            { label: 'Audit Log', href: '/admin/audit', icon: <ShieldCheck className="size-4.5" /> },
        ],
    },
];

const MEMBER_NAV: { group: string; items: NavItem[] }[] = [
    {
        group: 'Akun Saya',
        items: [
            { label: 'Ringkasan', href: '/member', icon: <Gauge className="size-4.5" />, primary: true },
            { label: 'Pembelian', href: '/member/pembelian', icon: <ShoppingBag className="size-4.5" />, primary: true },
            { label: 'Kelas Saya', href: '/member/kelas', icon: <Package className="size-4.5" />, primary: true },
            { label: 'Profil', href: '/member/profil', icon: <UserCircle className="size-4.5" />, primary: true },
        ],
    },
];

const AFFILIATE_NAV: { group: string; items: NavItem[] }[] = [
    {
        group: 'Affiliate',
        items: [
            { label: 'Ringkasan', href: '/affiliate', icon: <Gauge className="size-4.5" />, primary: true },
            { label: 'Marketplace', href: '/affiliate/marketplace', icon: <Boxes className="size-4.5" />, primary: true },
            { label: 'Link Saya', href: '/affiliate/link', icon: <ExternalLink className="size-4.5" />, primary: true },
            { label: 'Komisi', href: '/affiliate/komisi', icon: <Wallet className="size-4.5" />, primary: true },
        ],
    },
];

export type DashboardArea = 'creator' | 'admin' | 'member' | 'affiliate';

const NAV_BY_AREA: Record<DashboardArea, { group: string; items: NavItem[] }[]> = {
    creator: CREATOR_NAV,
    admin: ADMIN_NAV,
    member: MEMBER_NAV,
    affiliate: AFFILIATE_NAV,
};

export default function DashboardLayout({
    children,
    title,
    area = 'creator',
}: {
    children: ReactNode;
    title: string;
    area?: DashboardArea;
}) {
    const { auth, notifications } = usePage<PageProps>().props;
    const { url } = usePage();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [bellOpen, setBellOpen] = useState(false);

    const nav = NAV_BY_AREA[area];
    const primary = nav.flatMap((g) => g.items).filter((i) => i.primary).slice(0, 5);

    const isActive = (href: string) =>
        url === href || (href !== '/dashboard' && href !== '/admin' && href !== '/member' && href !== '/affiliate' && url.startsWith(href));

    return (
        <div className="jy-dashboard-shell min-h-screen bg-[#f4f3f8] bg-[radial-gradient(circle_at_85%_0%,rgba(124,58,237,.08),transparent_26%),radial-gradient(circle_at_25%_100%,rgba(251,113,133,.06),transparent_24%)] dark:bg-[#0d0c12]">
            <Head title={title} />

            {/* Sidebar — desktop */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-40 hidden flex-col overflow-hidden border-r border-white/10 bg-[#171620] text-white shadow-[18px_0_60px_rgba(20,18,32,.08)] transition-[width] duration-300 lg:flex',
                    collapsed ? 'w-[88px]' : 'w-[280px]',
                )}
            >
                <div className="flex h-20 items-center justify-between px-5">
                    <Link href="/" aria-label="JualanYok beranda">
                        {collapsed ? (
                            <img src="/favicon.svg" alt="" className="size-9" aria-hidden="true" />
                        ) : (
                            <img src="/images/jualanyok-logo-light.svg" alt="JualanYok" className="h-8 w-auto" />
                        )}
                    </Link>
                    {!collapsed && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full text-white/50 hover:bg-white/10 hover:text-white"
                            onClick={() => setCollapsed(true)}
                            aria-label="Perkecil sidebar"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                    )}
                </div>

                {collapsed && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="mx-auto rounded-full text-white/60 hover:bg-white/10 hover:text-white"
                        onClick={() => setCollapsed(false)}
                        aria-label="Perbesar sidebar"
                    >
                        <Menu className="size-4" />
                    </Button>
                )}

                <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4 [scrollbar-width:none]" aria-label="Navigasi dashboard">
                    {nav.map((group) => (
                        <div key={group.group}>
                            {!collapsed && (
                                <p className="mb-2 px-3 text-[9px] font-black uppercase tracking-[.2em] text-white/30">
                                    {group.group}
                                </p>
                            )}
                            <ul className="space-y-0.5">
                                {group.items.map((item) => (
                                    <li key={item.href}>
                                        <NavLink item={item} active={isActive(item.href)} collapsed={collapsed} />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>

                {auth.store && area === 'creator' && !collapsed && (
                    <div className="border-t border-white/10 p-4">
                        <a
                            href={auth.store.public_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[.06] px-3.5 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            <Eye className="size-4 shrink-0" />
                            <span className="min-w-0 flex-1 truncate">/{auth.store.username}</span>
                            <ExternalLink className="size-3.5 shrink-0 text-white/40" />
                        </a>
                        {!auth.store.is_published && (
                            <p className="mt-2 px-1 text-xs text-white/40">Toko masih draft — belum bisa diakses publik.</p>
                        )}
                    </div>
                )}
            </aside>

            {/* Drawer — mobile */}
            {drawerOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
                        onClick={() => setDrawerOpen(false)}
                        aria-hidden="true"
                    />
                    <div className="absolute inset-y-0 left-0 flex w-[84vw] max-w-80 flex-col bg-[#171620] text-white shadow-2xl animate-rise">
                        <div className="flex h-20 items-center justify-between px-5">
                            <img src="/images/jualanyok-logo-light.svg" alt="JualanYok" className="h-8 w-auto" />
                            <Button variant="ghost" size="icon" className="rounded-full text-white/60 hover:bg-white/10 hover:text-white" onClick={() => setDrawerOpen(false)} aria-label="Tutup menu">
                                <X className="size-5" />
                            </Button>
                        </div>
                        <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                            {nav.map((group) => (
                                <div key={group.group}>
                                    <p className="mb-2 px-3 text-[9px] font-black uppercase tracking-[.2em] text-white/30">
                                        {group.group}
                                    </p>
                                    <ul className="space-y-0.5">
                                        {group.items.map((item) => (
                                            <li key={item.href}>
                                                <NavLink
                                                    item={item}
                                                    active={isActive(item.href)}
                                                    onClick={() => setDrawerOpen(false)}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </nav>
                    </div>
                </div>
            )}

            {/* Main */}
            <div className={cn('transition-[padding] duration-300', collapsed ? 'lg:pl-[88px]' : 'lg:pl-[280px]')}>
                <header className="sticky top-0 z-30 px-3 pt-3 sm:px-5 sm:pt-4">
                    <div className="mx-auto flex h-16 max-w-[1500px] items-center gap-2 rounded-2xl border border-black/[.06] bg-white/88 px-3 shadow-[0_10px_35px_rgba(28,24,45,.08)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#191820]/90 sm:px-4">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full lg:hidden"
                            onClick={() => setDrawerOpen(true)}
                            aria-label="Buka menu"
                        >
                            <Menu className="size-5" />
                        </Button>

                        <Link href="/" className="lg:hidden" aria-label="JualanYok beranda">
                            <img src="/favicon.svg" alt="" className="size-8" />
                        </Link>

                        <div className="hidden min-w-0 lg:block">
                            <p className="text-[9px] font-black uppercase tracking-[.18em] text-violet-600">{area === 'creator' ? 'Creator workspace' : area === 'admin' ? 'Platform control' : area === 'affiliate' ? 'Affiliate center' : 'Member area'}</p>
                            <p className="truncate text-sm font-extrabold">{title}</p>
                        </div>

                        <label className="relative ml-auto hidden w-full max-w-xs xl:block">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                            <input type="search" placeholder="Cari menu atau data..." className="h-9 w-full rounded-full border border-line bg-surface-2/60 pl-9 pr-4 text-xs outline-none transition focus:border-violet-300 focus:bg-surface" />
                        </label>

                        <div className="flex-1 xl:hidden" />

                        <div className="relative">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="rounded-full"
                                onClick={() => setBellOpen((v) => !v)}
                                aria-label="Notifikasi"
                                aria-expanded={bellOpen}
                            >
                                <Bell className="size-5" />
                                {notifications.length > 0 && (
                                    <span className="absolute right-2 top-2 size-2 rounded-full bg-[var(--danger)]" />
                                )}
                            </Button>

                            {bellOpen && (
                                <div className="absolute right-0 top-12 z-50 w-80 rounded-[var(--radius-card)] border border-line bg-surface p-2 shadow-lift">
                                    <p className="px-3 py-2 text-xs font-bold uppercase tracking-wide text-muted">
                                        Notifikasi
                                    </p>
                                    {notifications.length === 0 ? (
                                        <p className="px-3 pb-3 text-sm text-muted">Belum ada notifikasi baru.</p>
                                    ) : (
                                        <ul className="max-h-80 overflow-y-auto">
                                            {notifications.map((n) => (
                                                <li key={n.id}>
                                                    <Link
                                                        href={n.url ?? '#'}
                                                        className="block rounded-[var(--radius-field)] px-3 py-2.5 hover:bg-surface-2"
                                                    >
                                                        <p className="text-sm font-semibold">{n.title}</p>
                                                        <p className="text-xs text-muted">{n.message}</p>
                                                        <p className="mt-0.5 text-[11px] text-muted">{n.created_at}</p>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-2 rounded-full bg-surface-2/70 py-1 pl-2 pr-1 sm:pl-3">
                            <span className="hidden text-right sm:block">
                                <span className="block text-sm font-bold leading-tight">{auth.user?.name}</span>
                                <span className="block text-xs text-muted">@{auth.user?.username}</span>
                            </span>
                            {auth.user?.avatar_url ? (
                                <img
                                    src={auth.user.avatar_url}
                                    alt=""
                                    className="size-9 rounded-full object-cover"
                                />
                            ) : (
                                <span className="grid size-9 place-items-center rounded-full bg-[#6d3cf4] text-xs font-bold text-white shadow-sm">
                                    {initials(auth.user?.name)}
                                </span>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="hidden rounded-full sm:grid"
                                onClick={() => router.post('/logout')}
                                aria-label="Keluar"
                            >
                                <LogOut className="size-4.5" />
                            </Button>
                        </div>
                    </div>

                    {auth.impersonating && <ImpersonationBanner />}
                </header>

                <main className="mx-auto max-w-[1500px] px-4 pb-28 pt-8 sm:px-6 sm:pt-10 lg:px-8 lg:pb-12">{children}</main>
            </div>

            {/* Bottom nav — mobile */}
            <nav
                className="fixed inset-x-3 bottom-3 z-30 overflow-hidden rounded-2xl border border-black/[.06] bg-white/92 shadow-[0_16px_45px_rgba(24,18,43,.18)] backdrop-blur-xl dark:border-white/10 dark:bg-[#191820]/95 lg:hidden"
                aria-label="Navigasi cepat"
            >
                <ul className="flex">
                    {primary.map((item) => (
                        <li key={item.href} className="flex-1">
                            <Link
                                href={item.href}
                                className={cn(
                                    'relative flex flex-col items-center gap-1 py-2.5 text-[9px] font-bold transition-colors',
                                    isActive(item.href) ? 'text-violet-700 dark:text-violet-300' : 'text-muted',
                                )}
                            >
                                {isActive(item.href) && <span className="absolute inset-x-3 top-0 h-0.5 rounded-full bg-violet-600" />}
                                {item.icon}
                                <span className="max-w-full truncate px-1">{item.label}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}

function NavLink({
    item,
    active,
    collapsed,
    onClick,
}: {
    item: NavItem;
    active: boolean;
    collapsed?: boolean;
    onClick?: () => void;
}) {
    return (
        <Link
            href={item.href}
            onClick={onClick}
            title={collapsed ? item.label : undefined}
            className={cn(
                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-all',
                active
                    ? 'bg-white text-[#171620] shadow-[0_8px_24px_rgba(0,0,0,.16)]'
                    : 'text-white/55 hover:bg-white/[.07] hover:text-white',
                collapsed && 'justify-center px-0',
            )}
            aria-current={active ? 'page' : undefined}
        >
            <span className={cn('transition-colors [&>svg]:size-[18px]', active ? 'text-violet-600' : 'text-white/45 group-hover:text-white')}>{item.icon}</span>
            {!collapsed && <span className="truncate">{item.label}</span>}
        </Link>
    );
}

/**
 * Shown while a super admin is signed in as someone else. Deliberately loud —
 * an admin must never forget they are acting as another user.
 */
function ImpersonationBanner() {
    return (
        <div className="flex items-center justify-between gap-3 bg-[var(--warning)] px-4 py-2 text-xs font-bold text-black sm:px-6">
            <span className="flex items-center gap-2">
                <ShieldCheck className="size-4" />
                Kamu sedang mengakses akun ini sebagai admin.
            </span>
            <button
                type="button"
                onClick={() => router.post('/admin/stop-impersonate')}
                className="underline underline-offset-2"
            >
                Kembali ke akun admin
            </button>
        </div>
    );
}
