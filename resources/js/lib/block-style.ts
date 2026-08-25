/**
 * Turns a block's design tokens into classes.
 *
 * Colour always comes from the storefront theme variables rather than fixed
 * values, so a styled block still belongs to the palette the creator picked and
 * can never end up with unreadable text on its own background.
 */

export interface BlockStyleTokens {
    background?: string;
    padding?: string;
    radius?: string;
    align?: string;
    width?: string;
    shadow?: string;
    animation?: string;
    animation_delay?: string;
    /* Every value is a token string, which also lets the object travel as a
       plain Inertia payload. Unknown keys are dropped server-side. */
    [key: string]: string | undefined;
}

const BACKGROUND: Record<string, string> = {
    none: '',
    subtle: 'bg-[color-mix(in_oklab,var(--sf-primary)_5%,var(--sf-card))]',
    primary: 'bg-[var(--sf-primary)] text-[var(--sf-on-primary)]',
    accent: 'bg-[var(--sf-accent)] text-[var(--sf-on-primary)]',
    dark: 'bg-[#12131a] text-white',
    gradient:
        'bg-gradient-to-br from-[var(--sf-primary)] via-[color-mix(in_oklab,var(--sf-primary)_60%,var(--sf-accent))] to-[var(--sf-accent)] text-[var(--sf-on-primary)]',
    outline: 'border border-[var(--sf-line)]',
};

const PADDING: Record<string, string> = {
    none: '',
    sm: 'p-3 sm:p-4',
    md: 'p-5 sm:p-6',
    lg: 'p-6 sm:p-9',
    xl: 'p-8 sm:p-14',
};

const RADIUS: Record<string, string> = {
    none: 'rounded-none',
    sm: 'rounded-lg',
    md: 'rounded-xl',
    lg: 'rounded-[1.5rem]',
    xl: 'rounded-[2rem]',
};

const ALIGN: Record<string, string> = {
    left: '',
    center: 'text-center',
    right: 'text-right',
};

const WIDTH: Record<string, string> = {
    normal: '',
    narrow: 'mx-auto max-w-xl',
    wide: 'mx-auto max-w-5xl',
    full: '',
};

const SHADOW: Record<string, string> = {
    none: '',
    soft: 'shadow-[0_8px_24px_-12px_rgba(16,24,40,.25)]',
    lift: 'shadow-[0_24px_60px_-24px_rgba(16,24,40,.4)]',
    glow: 'shadow-[0_0_50px_-12px_color-mix(in_oklab,var(--sf-primary)_55%,transparent)]',
};

/**
 * Reveal-on-scroll classes. The element starts hidden and `jy-reveal` is added
 * by the observer once it enters the viewport, so nothing is permanently
 * invisible if JavaScript never runs — see `useReveal`.
 */
const ANIMATION: Record<string, string> = {
    none: '',
    fade: 'jy-anim jy-anim-fade',
    'slide-up': 'jy-anim jy-anim-slide-up',
    'slide-left': 'jy-anim jy-anim-slide-left',
    'slide-right': 'jy-anim jy-anim-slide-right',
    zoom: 'jy-anim jy-anim-zoom',
    blur: 'jy-anim jy-anim-blur',
};

export function blockStyleClasses(style?: BlockStyleTokens | null): string {
    const s = style ?? {};

    return [
        BACKGROUND[s.background ?? 'none'] ?? '',
        PADDING[s.padding ?? 'none'] ?? '',
        // A radius only reads as one when something is drawn behind it.
        s.background && s.background !== 'none' ? (RADIUS[s.radius ?? 'lg'] ?? '') : '',
        ALIGN[s.align ?? 'left'] ?? '',
        WIDTH[s.width ?? 'normal'] ?? '',
        SHADOW[s.shadow ?? 'none'] ?? '',
        ANIMATION[s.animation ?? 'none'] ?? '',
    ]
        .filter(Boolean)
        .join(' ');
}

/** Inline delay, so five identical blocks can cascade instead of firing at once. */
export function blockStyleVars(style?: BlockStyleTokens | null): Record<string, string> {
    const delay = style?.animation_delay ?? '0';

    return delay !== '0' ? { '--jy-anim-delay': `${delay}ms` } : {};
}
