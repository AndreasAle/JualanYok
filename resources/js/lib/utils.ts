import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Indonesian rupiah, no decimals — the convention buyers expect. */
export function formatIDR(value: number | string | null | undefined): string {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatNumber(value: number | string | null | undefined): string {
    return new Intl.NumberFormat('id-ID').format(Number(value ?? 0));
}

export function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        ...(withTime ? { timeStyle: 'short' } : {}),
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}

export function formatPercent(value: number | null | undefined, digits = 1): string {
    if (value === null || value === undefined) return '—';

    return `${value > 0 ? '+' : ''}${value.toFixed(digits)}%`;
}

/** Idempotency keys for checkout submissions. */
export function uid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export function initials(name: string | null | undefined): string {
    if (!name) return '?';

    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export function truncate(text: string | null | undefined, max = 120): string {
    if (!text) return '';

    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}
