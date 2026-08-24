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
    /** Dedicated contact/WhatsApp call to action. */
    btnContact: string;
    /** Card surface used by every block. */
    card: string;
    /** Creator-selected rhythm between storefront blocks. */
    sectionSpacing: string;
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
    manrope: 'Manrope, ui-sans-serif, system-ui, sans-serif',
    'dm-sans': '"DM Sans", ui-sans-serif, system-ui, sans-serif',
    outfit: 'Outfit, ui-sans-serif, system-ui, sans-serif',
    sora: 'Sora, ui-sans-serif, system-ui, sans-serif',
    playfair: '"Playfair Display", Georgia, ui-serif, serif',
    lora: 'Lora, Georgia, ui-serif, serif',
    system: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
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
    const extras = theme.extras ?? {};

    const radius =
        theme.button_style === 'pill'
            ? 'rounded-full'
            : theme.button_style === 'square'
              ? 'rounded-lg'
              : 'rounded-xl';

    const selectedBackground = theme.background_value?.trim();
    const pageBackground =
        theme.background_type === 'image' && selectedBackground
            ? `url("${selectedBackground.replace(/"/g, '%22')}") center top / cover no-repeat fixed`
            : theme.background_type === 'gradient'
              ? selectedBackground?.includes('gradient(')
                  ? selectedBackground
                  : `linear-gradient(145deg, color-mix(in srgb, ${primary} 8%, #F8FAFC), color-mix(in srgb, ${accent} 7%, #FFFFFF))`
              : selectedBackground || (dark ? '#0F1115' : '#F6F7FB');

    // The profile cover stays brand-forward even when the page uses a subtle
    // background preset. A custom image is also reused as cover artwork.
    const coverBackground =
        theme.background_type === 'image' && selectedBackground
            ? `url("${selectedBackground.replace(/"/g, '%22')}") center / cover no-repeat`
            : `linear-gradient(135deg, ${primary}, ${accent})`;

    const surface = dark ? '#0F1115' : '#F6F7FB';
    const cardBg = extras.surface_color ?? (dark ? '#191C23' : '#FFFFFF');
    const text = dark ? '#F3F4F6' : '#111827';
    const cardText = readableOn(cardBg);
    const cardIsLight = luminance(cardBg) > 0.55;
    const badgeBg = extras.badge_background_color ?? (dark ? '#262A34' : '#F5F3FF');
    const badgeText = extras.badge_text_color ?? primary;
    const contact = extras.contact_button_color ?? '#25D366';
    const sectionSpacing = extras.spacing === 'compact'
        ? 'space-y-6 sm:space-y-8'
        : extras.spacing === 'airy'
          ? 'space-y-12 sm:space-y-16'
          : 'space-y-10 sm:space-y-14';

    const card =
        theme.card_style === 'outline'
            ? 'rounded-2xl border border-[var(--sf-line)] bg-[var(--sf-card)] text-[var(--sf-card-fg)]'
            : theme.card_style === 'flat'
              ? 'rounded-2xl bg-[var(--sf-card)] text-[var(--sf-card-fg)]'
              : 'rounded-2xl bg-[var(--sf-card)] text-[var(--sf-card-fg)] border border-[var(--sf-line)] shadow-[0_1px_2px_rgba(16,24,40,.04),0_8px_24px_rgba(16,24,40,.06)]';

    const vars = {
        '--sf-primary': primary,
        '--sf-accent': accent,
        '--sf-on-primary': readableOn(primary),
        '--sf-card': cardBg,
        '--sf-card-fg': cardText,
        '--sf-badge': badgeBg,
        '--sf-on-badge': badgeText,
        '--sf-contact': contact,
        '--sf-on-contact': readableOn(contact),
        '--sf-fg': text,
        '--sf-line': cardIsLight ? 'rgba(16,24,40,.08)' : 'rgba(255,255,255,.12)',
        '--sf-muted': cardIsLight ? 'rgba(17,24,39,.58)' : 'rgba(243,244,246,.68)',
    } as CSSProperties;

    return {
        dark,
        radius,
        vars,

        pageStyle: {
            ...vars,
            background: pageBackground || surface,
            color: text,
            fontFamily: FONT_STACKS[theme.font_family ?? 'jakarta'] ?? FONT_STACKS.jakarta,
        } as CSSProperties,

        coverStyle: { background: coverBackground },

        btnPrimary: `${radius} inline-flex items-center justify-center gap-2 bg-[var(--sf-primary)] text-[var(--sf-on-primary)] font-bold transition-all duration-200 hover:brightness-110 hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:hover:translate-y-0`,

        btnOutline: `${radius} inline-flex items-center justify-center gap-2 border border-[var(--sf-primary)] text-[var(--sf-primary)] font-bold transition-colors hover:bg-[var(--sf-primary)] hover:text-[var(--sf-on-primary)]`,

        btnContact: `${radius} inline-flex items-center justify-center gap-2 bg-[var(--sf-contact)] text-[var(--sf-on-contact)] font-bold shadow-md transition-all duration-200 hover:brightness-105 hover:-translate-y-0.5 active:translate-y-0`,

        card,
        sectionSpacing,
        muted: 'text-[var(--sf-muted)]',
        line: 'border-[var(--sf-line)]',
    };
}
