import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Badge, Button, Card, EmptyState, Input, Skeleton, statusTone } from '@/components/ui';
import { cn, formatIDR, formatPercent } from '@/lib/utils';
import type { Paginated } from '@/types';

/* ==========================================================================
   Page chrome
   ========================================================================== */

export function PageHeader({
    title,
    description,
    breadcrumbs,
    actions,
}: {
    title: string;
    description?: string;
    breadcrumbs?: { label: string; href?: string }[];
    actions?: ReactNode;
}) {
    return (
        <div className="mb-6">
            {breadcrumbs && breadcrumbs.length > 0 && (
                <nav aria-label="Breadcrumb" className="mb-2">
                    <ol className="flex flex-wrap items-center gap-1.5 text-xs text-muted">
                        {breadcrumbs.map((crumb, i) => (
                            <li key={i} className="flex items-center gap-1.5">
                                {i > 0 && <span aria-hidden="true">/</span>}
                                {crumb.href ? (
                                    <Link href={crumb.href} className="hover:text-fg transition-colors">
                                        {crumb.label}
                                    </Link>
                                ) : (
                                    <span className="text-fg font-medium">{crumb.label}</span>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>
            )}

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-balance text-[1.375rem] font-semibold tracking-[-.02em] sm:text-2xl">{title}</h1>
                    {description && <p className="mt-1.5 max-w-2xl text-sm leading-6 text-muted">{description}</p>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
            </div>
        </div>
    );
}

/* ==========================================================================
   Stats
   ========================================================================== */

/**
 * One number, stated plainly.
 *
 * The previous version shouted: a coloured icon tile, a black-weight figure, a
 * lift on hover and — for the first card in every row — a dark panel with a
 * blurred glow behind it. Four cards side by side then competed rather than
 * comparing. Here the label leads, the figure carries the weight, and the only
 * colour on the card is the direction of the change.
 */
export function StatCard({
    label,
    value,
    change,
    hint,
    icon,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    change?: number | null;
    hint?: string;
    icon?: ReactNode;
    /** Kept for call-site compatibility; emphasis is now a hairline, not a theme. */
    tone?: 'default' | 'brand' | 'success' | 'warning';
}) {
    const positive = (change ?? 0) >= 0;

    return (
        <Card className={cn('p-4 sm:p-5', tone === 'brand' && 'ring-1 ring-inset ring-[var(--primary)]/15')}>
            <div className="flex items-center gap-2">
                {icon && <span className="text-muted [&>svg]:size-4">{icon}</span>}
                <p className="truncate text-[0.8125rem] font-medium text-muted">{label}</p>
            </div>

            <p className="jy-num mt-2.5 text-[1.6rem] font-semibold leading-none">{value}</p>

            {(change !== null && change !== undefined) || hint ? (
                <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                    {change !== null && change !== undefined && (
                        <span
                            className={cn(
                                'inline-flex items-center gap-0.5 text-xs font-medium',
                                positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400',
                            )}
                        >
                            {positive ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" />}
                            {formatPercent(Math.abs(change), 1).replace('+', '')}
                        </span>
                    )}
                    {hint && <span className="text-xs text-muted">{hint}</span>}
                </div>
            ) : null}
        </Card>
    );
}

/* ==========================================================================
   Search + pagination
   ========================================================================== */

/** Debounced server-side search that preserves the rest of the query string. */
export function SearchInput({
    routeName,
    value,
    placeholder = 'Cari...',
    extra = {},
}: {
    routeName: string;
    value?: string;
    placeholder?: string;
    extra?: Record<string, string | undefined>;
}) {
    const [term, setTerm] = useState(value ?? '');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                routeName,
                { ...extra, q: term || undefined },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [term]);

    return (
        <div className="relative w-full sm:max-w-xs">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
            <Input
                type="search"
                value={term}
                onChange={(e) => setTerm(e.target.value)}
                placeholder={placeholder}
                className="pl-9"
                aria-label={placeholder}
            />
        </div>
    );
}

export function Pagination<T>({ meta }: { meta: Paginated<T> }) {
    if (meta.last_page <= 1) return null;

    return (
        <nav className="mt-5 flex items-center justify-between gap-3" aria-label="Navigasi halaman">
            <p className="text-xs text-muted">
                {meta.from ?? 0}–{meta.to ?? 0} dari {meta.total}
            </p>

            <div className="flex items-center gap-1">
                {meta.links.map((link, i) => {
                    const isPrev = i === 0;
                    const isNext = i === meta.links.length - 1;
                    const label = isPrev ? <ChevronLeft className="size-4" /> : isNext ? <ChevronRight className="size-4" /> : link.label;

                    if (!link.url) {
                        return (
                            <span key={i} className="grid h-9 min-w-9 place-items-center px-2 text-sm text-muted/50">
                                {label}
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                'grid h-9 min-w-9 place-items-center rounded-[var(--radius-field)] px-2 text-sm font-medium transition-colors',
                                link.active
                                    ? 'bg-[var(--primary)] text-[var(--primary-fg)]'
                                    : 'text-muted hover:bg-surface-2 hover:text-fg',
                            )}
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}

/* ==========================================================================
   Responsive data list
   ========================================================================== */

export interface Column<T> {
    key: string;
    header: string;
    render: (row: T) => ReactNode;
    /** Hidden on mobile cards when false; defaults to true. */
    mobile?: boolean;
    align?: 'left' | 'right';
    className?: string;
}

/**
 * A table on desktop and a stack of cards on mobile — never a squeezed table.
 */
export function DataList<T>({
    rows,
    columns,
    empty,
    rowKey,
    rowHref,
    loading,
}: {
    rows: T[];
    columns: Column<T>[];
    empty: ReactNode;
    rowKey: (row: T) => string | number;
    rowHref?: (row: T) => string;
    loading?: boolean;
}) {
    if (loading) {
        return (
            <div className="space-y-2">
                {Array.from({ length: 5 }).map((_, i) => (
                    <Skeleton key={i} className="h-16 w-full" />
                ))}
            </div>
        );
    }

    if (rows.length === 0) {
        return <Card>{empty}</Card>;
    }

    return (
        <>
            {/* Desktop */}
            <Card className="hidden overflow-hidden md:block">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-line bg-[#f8f7fa] dark:bg-white/[.035]">
                                {columns.map((col) => (
                                    <th
                                        key={col.key}
                                        scope="col"
                                        className={cn(
                                            'px-5 py-3.5 text-[11px] font-semibold uppercase tracking-[.06em] text-muted',
                                            col.align === 'right' ? 'text-right' : 'text-left',
                                        )}
                                    >
                                        {col.header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={rowKey(row)}
                                    className="border-b border-line last:border-0 transition-colors hover:bg-violet-50/45 dark:hover:bg-violet-500/5"
                                >
                                    {columns.map((col) => (
                                        <td
                                            key={col.key}
                                            className={cn(
                                                'px-5 py-4 align-middle',
                                                col.align === 'right' && 'text-right tabular-nums',
                                                col.className,
                                            )}
                                        >
                                            {col.render(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {/* Mobile */}
            <div className="space-y-2.5 md:hidden">
                {rows.map((row) => {
                    const inner = (
                        <Card key={rowKey(row)} className="p-4 transition active:scale-[.99]">
                            <dl className="space-y-2">
                                {columns
                                    .filter((c) => c.mobile !== false)
                                    .map((col) => (
                                        <div key={col.key} className="flex items-start justify-between gap-3">
                                            <dt className="text-xs font-semibold uppercase tracking-wide text-muted shrink-0">
                                                {col.header}
                                            </dt>
                                            <dd className="text-sm text-right min-w-0">{col.render(row)}</dd>
                                        </div>
                                    ))}
                            </dl>
                        </Card>
                    );

                    return rowHref ? (
                        <Link key={rowKey(row)} href={rowHref(row)} className="block">
                            {inner}
                        </Link>
                    ) : (
                        inner
                    );
                })}
            </div>
        </>
    );
}

/* ==========================================================================
   Charts (dependency-free SVG)
   ========================================================================== */

export function AreaChart({
    data,
    valueKey = 'gross',
    labelKey = 'date',
    format = formatIDR,
    height = 200,
}: {
    data: Record<string, any>[];
    valueKey?: string;
    labelKey?: string;
    format?: (v: number) => string;
    height?: number;
}) {
    if (!data || data.length === 0) {
        return (
            <div className="grid place-items-center rounded-[var(--radius-field)] bg-surface-2 text-sm text-muted" style={{ height }}>
                Belum ada data di rentang ini.
            </div>
        );
    }

    const values = data.map((d) => Number(d[valueKey] ?? 0));
    const max = Math.max(...values, 1);
    const width = 100;
    const step = data.length > 1 ? width / (data.length - 1) : width;

    const points = values.map((v, i) => `${i * step},${100 - (v / max) * 92}`);
    const line = points.join(' ');
    const area = `0,100 ${line} ${(values.length - 1) * step},100`;

    return (
        <div>
            <svg
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                style={{ height }}
                className="w-full"
                role="img"
                aria-label={`Grafik ${valueKey}, puncak ${format(max)}`}
            >
                <defs>
                    <linearGradient id="jy-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="var(--primary)" stopOpacity="0.28" />
                        <stop offset="100%" stopColor="var(--primary)" stopOpacity="0" />
                    </linearGradient>
                </defs>
                {[24, 49, 74, 99].map((y) => <line key={y} x1="0" x2="100" y1={y} y2={y} stroke="var(--sf-line, rgba(16,24,40,.08))" strokeWidth=".35" vectorEffect="non-scaling-stroke" />)}
                <polygon points={area} fill="url(#jy-area)" />
                <polyline
                    points={line}
                    fill="none"
                    stroke="var(--primary)"
                    strokeWidth="2"
                    vectorEffect="non-scaling-stroke"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            </svg>

            <div className="mt-2 flex justify-between text-xs text-muted">
                <span>{data[0]?.[labelKey]}</span>
                <span className="font-semibold text-fg">Puncak {format(max)}</span>
                <span>{data[data.length - 1]?.[labelKey]}</span>
            </div>
        </div>
    );
}

export function BarList({
    items,
    format = (v: number) => String(v),
}: {
    items: { label: string; value: number; hint?: string }[];
    format?: (v: number) => string;
}) {
    if (items.length === 0) {
        return <EmptyState title="Belum ada data" description="Data akan muncul setelah ada aktivitas." />;
    }

    const max = Math.max(...items.map((i) => i.value), 1);

    return (
        <ul className="space-y-3">
            {items.map((item, i) => (
                <li key={i}>
                    <div className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="font-medium truncate">{item.label}</span>
                        <span className="shrink-0 tabular-nums font-semibold">{format(item.value)}</span>
                    </div>
                    <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2">
                        <div
                            className="h-full rounded-full bg-violet-600 transition-all duration-500"
                            style={{ width: `${Math.max(4, (item.value / max) * 100)}%` }}
                        />
                    </div>
                    {item.hint && <p className="mt-1 text-xs text-muted">{item.hint}</p>}
                </li>
            ))}
        </ul>
    );
}

/* ==========================================================================
   Confirmation
   ========================================================================== */

export function ConfirmButton({
    onConfirm,
    title,
    message,
    confirmLabel = 'Ya, lanjutkan',
    children,
    variant = 'danger',
}: {
    onConfirm: () => void;
    title: string;
    message: string;
    confirmLabel?: string;
    children: ReactNode;
    variant?: 'danger' | 'primary';
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <span onClick={() => setOpen(true)} className="contents">
                {children}
            </span>

            {open && (
                <div
                    className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-title"
                    onClick={(e) => e.target === e.currentTarget && setOpen(false)}
                >
                    <Card className="w-full max-w-md animate-rise p-6">
                        <h2 id="confirm-title" className="text-lg font-bold">
                            {title}
                        </h2>
                        <p className="mt-2 text-sm text-muted">{message}</p>

                        <div className="mt-6 flex justify-end gap-2">
                            <Button variant="ghost" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button
                                variant={variant}
                                onClick={() => {
                                    onConfirm();
                                    setOpen(false);
                                }}
                            >
                                {confirmLabel}
                            </Button>
                        </div>
                    </Card>
                </div>
            )}
        </>
    );
}

export function StatusBadge({ status, label }: { status: string; label: string }) {
    return <Badge tone={statusTone(status)}>{label}</Badge>;
}
