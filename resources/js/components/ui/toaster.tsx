import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { PageProps } from '@/types';
import { cn } from '@/lib/utils';

type Toast = { id: number; tone: 'success' | 'error' | 'info' | 'warning'; message: string };

/**
 * Renders server flash messages as toasts. Deliberately one toast per flash —
 * the app never stacks a wall of notifications.
 */
export function Toaster() {
    const { flash } = usePage<PageProps>().props;
    const [toasts, setToasts] = useState<Toast[]>([]);

    useEffect(() => {
        const next: Toast[] = [];

        if (flash?.success) next.push({ id: Date.now(), tone: 'success', message: flash.success });
        if (flash?.error) next.push({ id: Date.now() + 1, tone: 'error', message: flash.error });
        if (flash?.info) next.push({ id: Date.now() + 2, tone: 'info', message: flash.info });
        if (flash?.warning) next.push({ id: Date.now() + 3, tone: 'warning', message: flash.warning });

        if (next.length === 0) return;

        setToasts((current) => [...current, ...next]);

        const timer = setTimeout(() => {
            setToasts((current) => current.filter((t) => !next.some((n) => n.id === t.id)));
        }, 6000);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.error, flash?.info, flash?.warning]);

    if (toasts.length === 0) return null;

    return (
        <div
            className="fixed inset-x-4 bottom-4 z-[100] flex flex-col gap-2 sm:left-auto sm:right-6 sm:bottom-6 sm:w-96"
            role="status"
            aria-live="polite"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn(
                        'animate-rise flex items-start gap-3 rounded-[var(--radius-field)] border px-4 py-3 shadow-lift bg-surface',
                        toast.tone === 'success' && 'border-emerald-300 dark:border-emerald-800',
                        toast.tone === 'error' && 'border-rose-300 dark:border-rose-800',
                        toast.tone === 'info' && 'border-sky-300 dark:border-sky-800',
                        toast.tone === 'warning' && 'border-amber-300 dark:border-amber-800',
                    )}
                >
                    {toast.tone === 'success' && <CheckCircle2 className="size-5 shrink-0 text-emerald-500" />}
                    {toast.tone === 'error' && <XCircle className="size-5 shrink-0 text-rose-500" />}
                    {toast.tone === 'info' && <Info className="size-5 shrink-0 text-sky-500" />}
                    {toast.tone === 'warning' && <AlertTriangle className="size-5 shrink-0 text-amber-500" />}

                    <p className="flex-1 text-sm font-medium break-words">{toast.message}</p>

                    <button
                        type="button"
                        onClick={() => setToasts((c) => c.filter((t) => t.id !== toast.id))}
                        className="text-muted hover:text-fg"
                        aria-label="Tutup notifikasi"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
