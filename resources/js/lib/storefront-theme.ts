import type { CSSProperties } from 'react';
import type { StoreTheme } from '@/types';

/**
 * Turns a creator's saved theme into a complete, self-contained design system
 * for their storefront.
 *
 * Two rules keep every store looking presentable no matter what colours the
 * creator picks:
 *
 * 1. The brand colour is confined to the cover band, buttons and accents. The
 *    page itself stays on a neutral surface — a full-page brand gradient is
 *    what makes a store look amateurish and hurts text contrast.
 * 2. Text colour is derived from the surface, never from the brand colour, so
 *    copy is always readable.
 */

export interface StorefrontTheme {
    /** Applied to the outer page wrapper (palette + surface + font). */
    pageStyle: CSSProperties;
    /**
     * Palette custom properties only — no background or colour. Use this on
     * overlays so they inherit the store's colours without painting over
     * their own scrim.
     */
    vars: CSSProperties;
    /** Brand-coloured band behind the profile header. */
    coverStyle: CSSProperties;
    dark: boolean;

    /** Solid brand button — the primary call to action. */
    btnPrimary: string;
    /** Bordered button that sits on a card. */
    btnOutline: string;
    /** Card surface used by every block. */
    card: string;
    /** Muted text on the storefront surface. */
    muted: string;
    /** Hairline divider matching the surface. */
    line: string;
    /** Rounding token matching the creator's button style. */
    radius: string;
}

const FONT_STACKS: Record<string, string> = {
    jakarta: '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif',
    inter: 'Inter, ui-sans-serif, system-ui, sans-serif',
    poppins: 'Poppins, ui-sans-serif, system-ui, sans-serif',
    nunito: 'Nunito, ui-sans-serif, system-ui, sans-serif',
    space: '"Space Grotesk", ui-sans-serif, system-ui, sans-serif',
};

/** Relative luminance, used to decide whether a brand colour needs light text. */
function luminance(hex: string): number {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;

    const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255);

    const channel = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** Black or white, whichever reads better on the given background. */
export function readableOn(hex: string): string {
    try {
        return luminance(hex) > 0.55 ? '#111827' : '#FFFFFF';
    } catch {
        return '#FFFFFF';
    }
}

export function buildStorefrontTheme(theme: Partial<StoreTheme>): StorefrontTheme {
    const primary = theme.primary_color ?? '#7C3AED';
    const accent = theme.accent_color ?? '#FB7185';
    const dark = theme.color_scheme === 'dark';

    const radius =
        theme.button_style === 'pill'
            ? 'rounded-full'
            : theme.button_style === 'square'
              ? 'rounded-lg'
              : 'rounded-xl';

    // The creator's background choice becomes the cover band, not the page.
    const coverBackground =
        theme.background_type === 'image' && theme.background_value
            ? `center / cover no-repeat url(${theme.background_value})`
            : theme.background_type === 'gradient'
              ? `linear-gradient(135deg, ${primary}, ${accent})`
              : `linear-gradient(135deg, ${primary}, ${primary})`;

    const surface = dark ? '#0F1115' : '#F6F7FB';
    const cardBg = dark ? '#191C23' : '#FFFFFF';
    const text = dark ? '#F3F4F6' : '#111827';

    const card =
        theme.card_style === 'outline'
            ? 'rounded-2xl border border-[var(--sf-line)] bg-[var(--sf-card)]'
            : theme.card_style === 'flat'
              ? 'rounded-2xl bg-[var(--sf-card)]'
              : 'rounded-2xl bg-[var(--sf-card)] border border-[var(--sf-line)] shadow-[0_1px_2px_rgba(16,24,40,.04),0_8px_24px_rgba(16,24,40,.06)]';

    const vars = {
        '--sf-primary': primary,
        '--sf-accent': accent,
        '--sf-on-primary': readableOn(primary),
        '--sf-card': cardBg,
        '--sf-line': dark ? 'rgba(255,255,255,.10)' : 'rgba(16,24,40,.08)',
        '--sf-muted': dark ? 'rgba(243,244,246,.62)' : 'rgba(17,24,39,.58)',
    } as CSSProperties;

    return {
        dark,
        radius,
        vars,

        pageStyle: {
            ...vars,
            background: surface,
            color: text,
            fontFamily: FONT_STACKS[theme.font_family ?? 'jakarta'] ?? FONT_STACKS.jakarta,
        } as CSSProperties,

        coverStyle: { background: coverBackground },

        btnPrimary: `${radius} inline-flex items-center justify-center gap-2 bg-[var(--sf-primary)] text-[var(--sf-on-primary)] font-bold transition-all duration-200 hover:brightness-110 hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:hover:translate-y-0`,

        btnOutline: `${radius} inline-flex items-center justify-center gap-2 border border-[var(--sf-primary)] text-[var(--sf-primary)] font-bold transition-colors hover:bg-[var(--sf-primary)] hover:text-[var(--sf-on-primary)]`,

        card,
        muted: 'text-[var(--sf-muted)]',
        line: 'border-[var(--sf-line)]',
    };
}
