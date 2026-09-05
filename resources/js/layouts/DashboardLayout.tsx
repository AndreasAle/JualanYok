import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, BarChart3, Blocks, Boxes, ChevronLeft, CircleHelp, CreditCard, ExternalLink, Eye, Gauge, Gift,
    MessageCircle,
    Handshake, IdCard, LayoutGrid, LifeBuoy, LogOut, Menu, Package, PieChart, Plug, QrCode, Receipt, Settings,
    Search, ShieldCheck, ShoppingBag, Star, Store, Ticket, TrendingUp, Truck, UserCircle, Users, Wallet, X,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Badge, Button } from '@/components/ui';
import NotificationBell from '@/components/notifications/NotificationBell';
import TourGuide from '@/components/TourGuide';
import { cn, initials } from '@/lib/utils';
import type { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: ReactNode;
    /** Shown in the mobile bottom bar. */
    primary?: boolean;
    /** Restricts sensitive links without exposing dead navigation to other admins. */
    roles?: string[];
    /** Names a live counter to show beside the label. */
    badge?: 'chat';
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
            { label: 'Chat', href: '/dashboard/chat', icon: <MessageCircle className="size-4.5" />, primary: true, badge: 'chat' },
            { label: 'Pengiriman', href: '/dashboard/pengiriman', icon: <Truck className="size-4.5" /> },
            { label: 'Ulasan', href: '/dashboard/ulasan', icon: <Star className="size-4.5" /> },
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
        items: [
            { label: 'Dashboard', href: '/admin', icon: <Gauge className="size-4.5" />, primary: true },
            { label: 'Unit Economics', href: '/admin/ekonomi', icon: <TrendingUp className="size-4.5" /> },
        ],
    },
    {
        group: 'Komunitas',
        items: [
            { label: 'Pengguna', href: '/admin/pengguna', icon: <Users className="size-4.5" />, primary: true },
            { label: 'Toko', href: '/admin/toko', icon: <Store className="size-4.5" />, primary: true },
            { label: 'Marketplace', href: '/admin/marketplace', icon: <Boxes className="size-4.5" />, roles: ['support-admin', 'super-admin'] },
        ],
    },
    {
        group: 'Transaksi',
        items: [
            { label: 'Pesanan', href: '/admin/pesanan', icon: <ShoppingBag className="size-4.5" />, primary: true },
            { label: 'Refund', href: '/admin/refund', icon: <Receipt className="size-4.5" /> },
            { label: 'Komplain', href: '/admin/komplain', icon: <AlertTriangle className="size-4.5" /> },
            { label: 'Penarikan', href: '/admin/penarikan', icon: <CreditCard className="size-4.5" />, primary: true },
            { label: 'Verifikasi Rekening', href: '/admin/rekening-pencairan', icon: <IdCard className="size-4.5" />, roles: ['finance-admin', 'super-admin'] },
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
    const { auth, tour, chatUnread } = usePage<PageProps>().props;
    const { url } = usePage();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [replayTour, setReplayTour] = useState(0);

    const userRoles = auth.user?.roles ?? [];
    const nav = NAV_BY_AREA[area].map((group) => ({
        ...group,
        items: group.items.filter((item) => !item.roles || item.roles.some((role) => userRoles.includes(role))),
    }));
    const primary = nav.flatMap((g) => g.items).filter((i) => i.primary).slice(0, 5);

    const isActive = (href: string) =>
        url === href || (href !== '/dashboard' && href !== '/admin' && href !== '/member' && href !== '/affiliate' && url.startsWith(href));

    return (
        <div className="jy-dashboard-shell min-h-screen bg-app">
            <Head title={title} />

            {/* Rendered last in the shell so it sits above every page's own
                stacking context, and only when the server says this creator has
                not already been through it. */}
            {tour && (!tour.seen || replayTour > 0) && (
                <TourGuide key={replayTour} tour={tour} onClose={() => setReplayTour(0)} />
            )}

            {/* Sidebar — desktop */}
            <aside
                data-tour="sidebar"
                className={cn(
                    'jy-tour-sidebar fixed inset-y-0 left-0 z-40 hidden flex-col overflow-hidden bg-[var(--nav)] text-white transition-[width] duration-200 lg:flex',
                    collapsed ? 'w-[76px]' : 'w-[264px]',
                )}
            >
                <div className="flex h-16 items-center justify-between px-4">
                    <Link href="/" aria-label="JualanYok beranda" className="min-w-0">
                        <Wordmark collapsed={collapsed} />
                    </Link>
                    {!collapsed && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 rounded-lg text-white/40 hover:bg-white/10 hover:text-white"
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
                        className="mx-auto size-8 rounded-lg text-white/50 hover:bg-white/10 hover:text-white"
                        onClick={() => setCollapsed(false)}
                        aria-label="Perbesar sidebar"
                    >
                        <Menu className="size-4" />
                    </Button>
                )}

                <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-3 [scrollbar-width:none]" aria-label="Navigasi dashboard">
                    {nav.map((group) => (
                        <div key={group.group}>
                            {!collapsed && (
                                <p className="mb-1 px-3 text-[0.6875rem] font-medium text-white/35">{group.group}</p>
                            )}
                            <ul className="space-y-0.5">
                                {group.items.map((item) => (
                                    <li key={item.href}>
                                        <NavLink
                                            item={item}
                                            active={isActive(item.href)}
                                            collapsed={collapsed}
                                            count={item.badge === 'chat' ? chatUnread : 0}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>

                {auth.store && area === 'creator' && !collapsed && (
                    <div className="border-t border-white/10 p-3">
                        <a
                            href={auth.store.public_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-[0.8125rem] font-medium text-white/70 transition hover:bg-white/[.07] hover:text-white"
                        >
                            <Eye className="size-4 shrink-0 text-white/40" />
                            <span className="min-w-0 flex-1 truncate">/{auth.store.username}</span>
                            <ExternalLink className="size-3.5 shrink-0 text-white/30" />
                        </a>
                        {!auth.store.is_published && (
                            <p className="mt-1.5 px-3 text-xs leading-5 text-white/40">Masih draft — belum bisa diakses publik.</p>
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
                    <div className="absolute inset-y-0 left-0 flex w-[84vw] max-w-80 flex-col bg-[var(--nav)] text-white shadow-2xl animate-rise">
                        <div className="flex h-16 items-center justify-between px-4">
                            <Wordmark />
                            <Button variant="ghost" size="icon" className="size-8 rounded-lg text-white/50 hover:bg-white/10 hover:text-white" onClick={() => setDrawerOpen(false)} aria-label="Tutup menu">
                                <X className="size-5" />
                            </Button>
                        </div>
                        <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                            {nav.map((group) => (
                                <div key={group.group}>
                                    <p className="mb-1 px-3 text-[0.6875rem] font-medium text-white/35">{group.group}</p>
                                    <ul className="space-y-0.5">
                                        {group.items.map((item) => (
                                            <li key={item.href}>
                                                <NavLink
                                                    item={item}
                                                    active={isActive(item.href)}
                                                    count={item.badge === 'chat' ? chatUnread : 0}
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
            <div className={cn('transition-[padding] duration-200', collapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]')}>
                <header className="sticky top-0 z-30 border-b border-line bg-app/85 backdrop-blur-xl">
                    <div className="mx-auto flex h-14 max-w-[1440px] items-center gap-2 px-4 sm:px-6 lg:px-8">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-9 rounded-lg lg:hidden"
                            onClick={() => setDrawerOpen(true)}
                            aria-label="Buka menu"
                        >
                            <Menu className="size-5" />
                        </Button>

                        <Link href="/" className="lg:hidden" aria-label="JualanYok beranda">
                            <img src="/images/jualanyok-mark.png" alt="" className="size-7 rounded-md" />
                        </Link>

                        {/* The page title lives here rather than being repeated in a
                            hero, so the first heading below is the content itself. */}
                        <p className="hidden min-w-0 truncate text-sm font-medium lg:block">{title}</p>

                        <label className="relative ml-auto hidden w-full max-w-sm xl:block">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                            <input
                                type="search"
                                placeholder="Cari menu atau data"
                                className="h-9 w-full rounded-[var(--radius-field)] border border-line bg-surface pl-9 pr-3 text-[0.8125rem] outline-none transition placeholder:text-muted focus:border-[var(--primary)]/40"
                            />
                        </label>

                        <div className="flex-1 xl:hidden" />

                        {/* Only offered on screens that actually have a tour, so it
                            never promises help it cannot give. */}
                        {tour && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-9 rounded-lg"
                                onClick={() => setReplayTour((n) => n + 1)}
                                aria-label={`Panduan: ${tour.title}`}
                                title={`Panduan: ${tour.title}`}
                            >
                                <CircleHelp className="size-5" />
                            </Button>
                        )}

                        <NotificationBell area={area} />

                        <span className="mx-1 hidden h-5 w-px bg-[var(--border)] sm:block" />

                        <div className="flex items-center gap-2.5">
                            <span className="hidden text-right leading-tight sm:block">
                                <span className="block text-[0.8125rem] font-medium">{auth.user?.name}</span>
                                <span className="block text-xs text-muted">@{auth.user?.username}</span>
                            </span>
                            {auth.user?.avatar_url ? (
                                <img src={auth.user.avatar_url} alt="" className="size-8 rounded-full object-cover" />
                            ) : (
                                <span className="grid size-8 place-items-center rounded-full bg-[var(--nav)] text-[0.6875rem] font-semibold text-white">
                                    {initials(auth.user?.name)}
                                </span>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="hidden size-9 rounded-lg sm:grid"
                                onClick={() => router.post('/logout')}
                                aria-label="Keluar"
                            >
                                <LogOut className="size-4.5" />
                            </Button>
                        </div>
                    </div>

                    {auth.impersonating && <ImpersonationBanner />}
                </header>

                <main className="mx-auto max-w-[1440px] px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-14">{children}</main>
            </div>

            {/* Bottom nav — mobile */}
            <nav
                className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-app/92 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden"
                aria-label="Navigasi cepat"
            >
                <ul className="flex">
                    {primary.map((item) => (
                        <li key={item.href} className="flex-1">
                            <Link
                                href={item.href}
                                className={cn(
                                    'relative flex flex-col items-center gap-1 py-2.5 text-[0.625rem] font-medium transition-colors',
                                    isActive(item.href) ? 'text-fg' : 'text-muted',
                                )}
                            >
                                {isActive(item.href) && <span className="absolute inset-x-4 top-0 h-0.5 bg-[var(--primary)]" />}
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

/**
 * The brand lockup.
 *
 * The mark used to be four crossing strokes that only resolved into letters at
 * poster size; in a 32px sidebar slot it was a squiggle. A filled tile with a
 * single silhouette inside survives being small, which is the only size it is
 * ever actually seen at.
 */
function Wordmark({ collapsed }: { collapsed?: boolean }) {
    if (collapsed) {
        return <img src="/images/jualanyok-mark.png" alt="JualanYok" className="size-8 rounded-lg" />;
    }

    return <img src="/images/jualanyok-logo-light.png" alt="JualanYok" className="h-7 w-auto" />;
}

function NavLink({
    item,
    active,
    collapsed,
    count = 0,
    onClick,
}: {
    item: NavItem;
    active: boolean;
    collapsed?: boolean;
    count?: number;
    onClick?: () => void;
}) {
    return (
        <Link
            href={item.href}
            onClick={onClick}
            title={collapsed ? item.label : undefined}
            className={cn(
                'group relative flex items-center gap-2.5 rounded-lg px-3 py-2 text-[0.8125rem] transition-colors',
                active
                    ? 'bg-white/[.10] font-medium text-white'
                    : 'font-normal text-white/60 hover:bg-white/[.05] hover:text-white',
                collapsed && 'justify-center px-0',
            )}
            aria-current={active ? 'page' : undefined}
        >
            {/* The current page is marked, not spotlit. A white pill with a drop
                shadow reads as a floating button rather than "you are here". */}
            {active && !collapsed && (
                <span className="absolute inset-y-1.5 left-0 w-0.5 rounded-r bg-[var(--color-brand-400)]" />
            )}
            <span className={cn('shrink-0 transition-colors [&>svg]:size-[17px]', active ? 'text-white' : 'text-white/45 group-hover:text-white/80')}>{item.icon}</span>
            {!collapsed && <span className="truncate">{item.label}</span>}
            {count > 0 && (
                <span
                    className={cn(
                        'grid min-w-4 place-items-center rounded-full bg-[var(--color-brand-500)] px-1 text-[0.625rem] font-semibold text-white',
                        collapsed && 'absolute right-3 top-1.5 size-2 min-w-0 p-0 text-[0]',
                    )}
                >
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}

/**
 * Shown while a super admin is signed in as someone else. Deliberately loud —
 * an admin must never forget they are acting as another user.
 */
function ImpersonationBanner() {
    return (
        <div className="flex items-center justify-between gap-3 bg-[var(--warning)] px-4 py-2 text-xs font-semibold text-black sm:px-6">
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
