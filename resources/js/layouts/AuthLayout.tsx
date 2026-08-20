import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import type { ReactNode } from 'react';
import { ThemeToggle } from '@/components/theme-toggle';
import { Logo } from '@/layouts/MarketingLayout';

export default function AuthLayout({
    children,
    title,
    heading,
    subheading,
    footer,
}: {
    children: ReactNode;
    title: string;
    heading: string;
    subheading?: string;
    footer?: ReactNode;
}) {
    return (
        <div className="auth-canvas relative min-h-screen overflow-hidden bg-[#f4f8f7] dark:bg-[#111116]">
            <Head title={title} />

            <div className="pointer-events-none absolute -left-32 bottom-[-9rem] size-[28rem] rounded-full bg-emerald-200/55 blur-[90px] dark:bg-emerald-950/15" />
            <div className="pointer-events-none absolute -right-32 top-[-8rem] size-[28rem] rounded-full bg-violet-200/55 blur-[90px] dark:bg-violet-950/20" />

            <Link href="/" className="absolute left-5 top-5 z-20 inline-flex items-center gap-2 text-xs font-bold text-neutral-500 transition hover:text-violet-600 sm:left-8 sm:top-7">
                <ArrowLeft className="size-4" /> Beranda
            </Link>
            <ThemeToggle className="absolute right-5 top-5 z-20 sm:right-8 sm:top-7" />

            <main className="relative z-10 flex min-h-screen items-center justify-center px-4 py-20 sm:px-6">
                <div className="w-full max-w-[430px]">
                    <div className="text-center">
                        <Link href="/" className="inline-flex" aria-label="JualanYok beranda"><Logo /></Link>
                        <h1 className="mt-6 text-2xl font-extrabold tracking-[-.035em] text-neutral-950 dark:text-white sm:text-[1.7rem]">{heading}</h1>
                        {subheading && <p className="mt-2 text-sm leading-6 text-muted">{subheading}</p>}
                    </div>

                    <div className="mt-7 rounded-[1.5rem] border border-white/90 bg-white/92 p-5 shadow-[0_22px_65px_rgba(38,56,52,.12)] backdrop-blur-xl sm:p-7 dark:border-white/10 dark:bg-[#1d1d24]/95">
                        {children}
                    </div>

                    {footer && <div className="mt-6 text-center text-sm text-muted">{footer}</div>}

                    <p className="mt-7 flex items-center justify-center gap-1.5 text-[10px] font-semibold text-neutral-400">
                        <LockKeyhole className="size-3.5" /> Data login dikirim melalui koneksi terenkripsi
                    </p>
                </div>
            </main>
        </div>
    );
}
