import { Head, Link, usePage } from '@inertiajs/react';
import { Mail, MapPin, Menu, Phone, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

const NAV = [
    { label: 'Jelajahi', href: '/explore' },
    { label: 'Lacak Barangmu', href: '/lacak' },
    { label: 'Fitur', href: '/features' },
    { label: 'Template', href: '/templates' },
    { label: 'Harga', href: '/pricing' },
];

export function Logo({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex', className)}>
            <img src="/images/jualanyok-logo.png" alt="JualanYok" className="h-8 w-auto select-none dark:hidden" />
            <img src="/images/jualanyok-logo-light.png" alt="JualanYok" className="hidden h-8 w-auto select-none dark:block" />
        </span>
    );
}

export default function MarketingLayout({
    children,
    title,
    description,
}: {
    children: ReactNode;
    title?: string;
    description?: string;
}) {
    const page = usePage<PageProps>();
    const { auth, business } = page.props;
    const currentPath = page.url.split('?')[0];
    const [open, setOpen] = useState(false);

    useEffect(() => {
        // Public marketing pages use one deliberate light visual direction.
        // This also covers Inertia navigation from a dark authenticated page.
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }, []);

    return (
        <div className="marketing-light min-h-screen bg-[#fcfbfe] text-[#171722]">
            <Head title={title}>
                {description && <meta name="description" content={description} />}
            </Head>

            <header className="pointer-events-none sticky top-0 z-50 px-3 py-3 sm:px-5">
                <div className="pointer-events-auto relative mx-auto max-w-6xl rounded-[1.15rem] border border-black/[.07] bg-white/92 shadow-[0_12px_40px_rgba(24,18,43,.09)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#17171d]/92">
                    <div className="flex h-[4.25rem] items-center justify-between gap-4 px-4 sm:px-5">
                        <Link href="/" aria-label="JualanYok beranda" className="shrink-0 rounded-lg outline-none ring-violet-500 transition focus-visible:ring-2 [&_img]:h-7">
                            <Logo />
                        </Link>

                        <nav className="absolute left-1/2 hidden -translate-x-1/2 items-center rounded-full border border-black/[.05] bg-black/[.025] p-1 md:flex dark:border-white/10 dark:bg-white/[.045]" aria-label="Navigasi utama">
                            {NAV.map((item) => {
                                const active = currentPath === item.href || currentPath.startsWith(`${item.href}/`);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'relative rounded-full px-4 py-2 text-[13px] font-bold transition duration-200',
                                            active
                                                ? 'bg-white text-[#171722] shadow-[0_3px_12px_rgba(24,18,43,.08)] dark:bg-white/10 dark:text-white'
                                                : 'text-neutral-500 hover:text-[#171722] dark:text-white/55 dark:hover:text-white',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="flex items-center gap-1.5 sm:gap-2">
                            {auth.user ? (
                                <ButtonLink
                                    href={auth.store ? '/dashboard' : '/onboarding'}
                                    variant="ghost"
                                    size="sm"
                                    className="rounded-full bg-[#171722] px-5 text-white shadow-[0_8px_20px_rgba(23,23,34,.16)] hover:bg-black hover:text-white dark:bg-white dark:text-[#171722] dark:hover:bg-white/90"
                                >
                                    Dashboard
                                </ButtonLink>
                            ) : (
                                <>
                                    <ButtonLink href="/login" variant="ghost" size="sm" className="hidden rounded-full px-4 font-bold text-neutral-700 hover:bg-neutral-100 hover:text-black sm:inline-flex dark:text-white/75 dark:hover:bg-white/10 dark:hover:text-white">
                                        Sign in
                                    </ButtonLink>
                                    <ButtonLink href="/register" variant="ghost" size="sm" className="rounded-full bg-[#171722] px-4 font-bold text-white shadow-[0_8px_20px_rgba(23,23,34,.16)] hover:bg-black hover:text-white sm:px-5 dark:bg-white dark:text-[#171722] dark:hover:bg-white/90">
                                        Create account
                                    </ButtonLink>
                                </>
                            )}

                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-9 rounded-full md:hidden"
                                onClick={() => setOpen((v) => !v)}
                                aria-label={open ? 'Tutup menu' : 'Buka menu'}
                                aria-expanded={open}
                            >
                                {open ? <X className="size-4.5" /> : <Menu className="size-4.5" />}
                            </Button>
                        </div>
                    </div>

                    {open && (
                        <nav className="border-t border-black/[.06] px-3 pb-3 pt-2 md:hidden dark:border-white/10" aria-label="Navigasi mobile">
                            <div className="grid gap-1">
                                {NAV.map((item) => {
                                    const active = currentPath === item.href || currentPath.startsWith(`${item.href}/`);
                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            onClick={() => setOpen(false)}
                                            aria-current={active ? 'page' : undefined}
                                            className={cn(
                                                'flex items-center justify-between rounded-xl px-3.5 py-3 text-sm font-bold transition',
                                                active ? 'bg-neutral-100 text-[#171722] dark:bg-white/10 dark:text-white' : 'text-neutral-500 hover:bg-neutral-50 hover:text-[#171722] dark:text-white/55 dark:hover:bg-white/5 dark:hover:text-white',
                                            )}
                                        >
                                            {item.label}
                                            <span className={cn('size-1.5 rounded-full bg-violet-600', !active && 'opacity-0')} />
                                        </Link>
                                    );
                                })}
                            </div>
                            {!auth.user && (
                                <Link
                                    href="/login"
                                    onClick={() => setOpen(false)}
                                    className="mt-2 flex h-11 items-center justify-center rounded-xl border border-black/10 text-sm font-bold text-[#171722] dark:border-white/10 dark:text-white"
                                >
                                    Sign in
                                </Link>
                            )}
                        </nav>
                    )}
                </div>
            </header>

            <main>{children}</main>

            <footer className="mt-20 border-t border-line bg-subtle">
                <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="lg:col-span-1">
                            <Logo />
                            <p className="mt-3 max-w-xs text-sm text-muted">
                                Bikin toko online, jual apa aja, terima duitnya. Buat kreator Indonesia.
                            </p>
                        </div>

                        <FooterColumn
                            title="Produk"
                            links={[
                                { label: 'Jelajahi', href: '/explore' },
                                { label: 'Fitur', href: '/features' },
                                { label: 'Template', href: '/templates' },
                                { label: 'Harga', href: '/pricing' },
                            ]}
                        />
                        <FooterColumn
                            title="Dukungan"
                            links={[
                                { label: 'Pusat Bantuan', href: '/contact' },
                                { label: 'FAQ', href: '/faq' },
                                { label: 'Kebijakan Refund', href: '/refund-policy' },
                            ]}
                        />
                        <FooterColumn
                            title="Legal"
                            links={[
                                { label: 'Syarat & Ketentuan', href: '/terms' },
                                { label: 'Kebijakan Privasi', href: '/privacy' },
                            ]}
                        />
                    </div>

                    <div className="mt-9 grid gap-3 rounded-2xl border border-line bg-surface p-4 text-xs text-muted sm:grid-cols-3 sm:p-5">
                        <BusinessDetail icon={<Mail />} label="Email" value={business.email} href={business.email ? `mailto:${business.email}` : undefined} />
                        <BusinessDetail icon={<Phone />} label="Telepon" value={business.phone} href={business.phone ? `tel:${business.phone.replace(/\s/g, '')}` : undefined} />
                        <BusinessDetail icon={<MapPin />} label="Alamat usaha" value={business.address} />
                    </div>

                    <div className="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
                        <p>© {new Date().getFullYear()} JualanYok. Dibuat buat kreator Indonesia.</p>
                        <p>Harga dalam Rupiah (IDR). Waktu WIB.</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function BusinessDetail({ icon, label, value, href }: { icon: ReactNode; label: string; value: string; href?: string }) {
    const content = <><span className="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600 [&>svg]:size-3.5">{icon}</span><span><b className="block text-[9px] uppercase tracking-[.12em] text-neutral-400">{label}</b><span className="mt-0.5 block font-semibold text-fg">{value || 'Belum dikonfigurasi'}</span></span></>;

    return href
        ? <a href={href} className="flex items-start gap-3 rounded-xl p-2 transition hover:bg-subtle">{content}</a>
        : <div className="flex items-start gap-3 rounded-xl p-2">{content}</div>;
}

function FooterColumn({ title, links }: { title: string; links: { label: string; href: string }[] }) {
    return (
        <div>
            <h2 className="text-xs font-bold uppercase tracking-wide text-muted">{title}</h2>
            <ul className="mt-3 space-y-2">
                {links.map((link) => (
                    <li key={link.href}>
                        <Link href={link.href} className="text-sm text-muted transition-colors hover:text-fg">
                            {link.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
