import { ChevronLeft, ChevronRight, MoveHorizontal } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatNumber } from '@/lib/utils';

/**
 * Reveals a block once it scrolls into view.
 *
 * The hidden state lives in CSS behind `@media (scripting: enabled)`, so a page
 * whose bundle never loads shows its content instead of a column of invisible
 * blocks. The reveal itself is written as inline style rather than a class:
 * inline always wins the cascade, so no later utility or media block can leave
 * a paid-for storefront permanently blank.
 *
 * `prefers-reduced-motion` still overrides both, because that rule is
 * `!important`.
 */
function reveal(node: HTMLElement): void {
    node.style.opacity = '1';
    node.style.transform = 'none';
    node.style.filter = 'none';
}

export function useReveal<T extends HTMLElement>(enabled: boolean) {
    const ref = useRef<T | null>(null);

    useEffect(() => {
        const node = ref.current;

        if (!enabled || !node) return;

        if (typeof IntersectionObserver === 'undefined') {
            reveal(node);

            return;
        }

        /*
         * A block that never reveals is worse than one that never animates: the
         * visitor sees an empty gap where a product was meant to be.
         *
         * An observer reports on the element as soon as it is observed, even
         * when it is off-screen. If that first report never arrives, something
         * is stopping the observer from running and we show the content rather
         * than trust it any longer.
         */
        let reported = false;

        const observer = new IntersectionObserver(
            ([entry]) => {
                reported = true;

                if (entry.isIntersecting) {
                    reveal(entry.target as HTMLElement);
                    observer.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );

        observer.observe(node);

        const failsafe = window.setTimeout(() => {
            if (!reported) {
                reveal(node);
                observer.disconnect();
            }
        }, 1500);

        return () => {
            window.clearTimeout(failsafe);
            observer.disconnect();
        };
    }, [enabled]);

    return ref;
}

/* -------------------------------------------------------------------------- */

interface Slide {
    image?: string;
    title?: string;
    subtitle?: string;
    url?: string;
}

/**
 * Image carousel.
 *
 * Native scroll-snap rather than a JS slider: swipe works on touch for free,
 * keyboard scrolling works, and it degrades to a plain scrollable row if the
 * script never runs.
 */
export function CarouselBlock({
    slides,
    theme,
    aspect = 'wide',
    autoplay = false,
}: {
    slides: Slide[];
    theme: StorefrontTheme;
    aspect?: string;
    autoplay?: boolean;
}) {
    const trackRef = useRef<HTMLDivElement | null>(null);
    const [active, setActive] = useState(0);

    const ratio =
        aspect === 'square' ? 'aspect-square' : aspect === 'tall' ? 'aspect-[3/4]' : 'aspect-[16/9]';

    const goTo = (index: number) => {
        const track = trackRef.current;

        if (!track) return;

        const target = track.children[index] as HTMLElement | undefined;

        target?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    };

    useEffect(() => {
        const track = trackRef.current;

        if (!track) return;

        const onScroll = () => {
            const index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
            setActive(Math.min(slides.length - 1, Math.max(0, index)));
        };

        track.addEventListener('scroll', onScroll, { passive: true });

        return () => track.removeEventListener('scroll', onScroll);
    }, [slides.length]);

    useEffect(() => {
        // Autoplay stops the moment someone interacts; nothing is more annoying
        // than a carousel that yanks itself away mid-read.
        if (!autoplay || slides.length < 2) return;

        let paused = false;
        const track = trackRef.current;
        const pause = () => {
            paused = true;
        };

        track?.addEventListener('pointerdown', pause, { once: true });

        const timer = window.setInterval(() => {
            if (paused) return;
            setActive((current) => {
                const next = (current + 1) % slides.length;
                goTo(next);

                return next;
            });
        }, 5000);

        return () => {
            window.clearInterval(timer);
            track?.removeEventListener('pointerdown', pause);
        };
    }, [autoplay, slides.length]);

    if (slides.length === 0) return null;

    return (
        <div className="relative">
            <div
                ref={trackRef}
                className="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                role="region"
                aria-roledescription="carousel"
                aria-label="Galeri geser"
            >
                {slides.map((slide, index) => {
                    const inner = (
                        <>
                            {slide.image ? (
                                <img
                                    src={slide.image}
                                    alt={slide.title ?? ''}
                                    loading={index === 0 ? 'eager' : 'lazy'}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <span className="grid size-full place-items-center bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)]" />
                            )}

                            {(slide.title || slide.subtitle) && (
                                <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent p-5 text-left">
                                    {slide.title && (
                                        <span className="block text-lg font-black leading-tight text-white sm:text-xl">
                                            {slide.title}
                                        </span>
                                    )}
                                    {slide.subtitle && (
                                        <span className="mt-1 block text-sm text-white/80">{slide.subtitle}</span>
                                    )}
                                </span>
                            )}
                        </>
                    );

                    const shell = cn(
                        'relative w-full shrink-0 snap-start overflow-hidden',
                        theme.radius,
                        ratio,
                    );

                    return slide.url ? (
                        <a key={index} href={slide.url} className={shell} target="_blank" rel="noopener noreferrer">
                            {inner}
                        </a>
                    ) : (
                        <div key={index} className={shell}>
                            {inner}
                        </div>
                    );
                })}
            </div>

            {slides.length > 1 && (
                <>
                    <button
                        type="button"
                        aria-label="Sebelumnya"
                        onClick={() => goTo(Math.max(0, active - 1))}
                        className="absolute left-2 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/65 sm:grid"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        aria-label="Berikutnya"
                        onClick={() => goTo(Math.min(slides.length - 1, active + 1))}
                        className="absolute right-2 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/65 sm:grid"
                    >
                        <ChevronRight className="size-5" />
                    </button>

                    <div className="mt-3 flex justify-center gap-1.5">
                        {slides.map((_, index) => (
                            <button
                                key={index}
                                type="button"
                                aria-label={`Ke slide ${index + 1}`}
                                onClick={() => goTo(index)}
                                className={cn(
                                    'h-1.5 rounded-full transition-all',
                                    index === active
                                        ? 'w-6 bg-[var(--sf-primary)]'
                                        : 'w-1.5 bg-[var(--sf-line)]',
                                )}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */

/** Scrolling ticker. The list is duplicated so the loop has no visible seam. */
export function MarqueeBlock({
    items,
    speed = 'normal',
    reverse = false,
    separator = '✦',
}: {
    items: string[];
    speed?: string;
    reverse?: boolean;
    separator?: string;
}) {
    if (items.length === 0) return null;

    const duration = speed === 'slow' ? '48s' : speed === 'fast' ? '16s' : '28s';

    return (
        <div className="overflow-hidden" aria-label={items.join(', ')}>
            <div
                className={cn('jy-marquee-track', reverse && 'jy-marquee-reverse')}
                style={{ ['--jy-marquee-duration' as string]: duration }}
                aria-hidden
            >
                {[0, 1].map((copy) => (
                    <div key={copy} className="flex shrink-0 items-center">
                        {items.map((item, index) => (
                            <span key={`${copy}-${index}`} className="flex items-center">
                                <span className="whitespace-nowrap px-5 text-lg font-black tracking-tight sm:text-2xl">
                                    {item}
                                </span>
                                <span className="text-[var(--sf-primary)]">{separator}</span>
                            </span>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

/* -------------------------------------------------------------------------- */

interface Stat {
    value?: string | number;
    label?: string;
    suffix?: string;
}

/** Headline numbers. Counts up on reveal, but only when motion is welcome. */
export function StatsBlock({ stats, theme }: { stats: Stat[]; theme: StorefrontTheme }) {
    const ref = useRef<HTMLDivElement | null>(null);
    const [run, setRun] = useState(false);

    useEffect(() => {
        const node = ref.current;

        if (!node || typeof IntersectionObserver === 'undefined') {
            setRun(true);

            return;
        }

        // Same failsafe as useReveal: a figure stuck at zero misrepresents the
        // seller, which is worse than starting the count a little early.
        let reported = false;

        const observer = new IntersectionObserver(
            ([entry]) => {
                reported = true;

                if (entry.isIntersecting) {
                    setRun(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.3 },
        );

        observer.observe(node);

        const failsafe = window.setTimeout(() => {
            if (!reported) setRun(true);
        }, 1500);

        return () => {
            window.clearTimeout(failsafe);
            observer.disconnect();
        };
    }, []);

    if (stats.length === 0) return null;

    return (
        <div
            ref={ref}
            className={cn(
                'grid gap-4',
                stats.length === 2 ? 'grid-cols-2' : stats.length === 3 ? 'grid-cols-3' : 'grid-cols-2 @lg:grid-cols-4',
            )}
        >
            {stats.map((stat, index) => (
                <div key={index} className="text-center">
                    <p className="text-3xl font-black tracking-[-.04em] text-[var(--sf-primary)] tabular-nums sm:text-4xl">
                        <CountUp value={stat.value} run={run} />
                        {stat.suffix}
                    </p>
                    <p className={cn('mt-1 text-xs font-semibold sm:text-sm', theme.muted)}>{stat.label}</p>
                </div>
            ))}
        </div>
    );
}

/** Animates to a number, and shows non-numeric values untouched. */
function CountUp({ value, run }: { value?: string | number; run: boolean }) {
    const target = typeof value === 'number' ? value : Number(String(value ?? '').replace(/[^\d.-]/g, ''));
    const isNumeric = Number.isFinite(target) && String(value ?? '').trim() !== '';
    const decimals = (String(value ?? '').split('.')[1] ?? '').length;
    const [shown, setShown] = useState(0);

    useEffect(() => {
        if (!run || !isNumeric) return;

        const reduced =
            typeof window !== 'undefined' &&
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        if (reduced) {
            setShown(target);

            return;
        }

        const duration = 900;
        const started = performance.now();
        let frame = 0;

        const step = (now: number) => {
            const progress = Math.min(1, (now - started) / duration);
            // Ease-out so it decelerates into the final figure.
            const current = target * (1 - (1 - progress) ** 3);

            // Rounding to whole numbers would turn a 4.9 rating into 5 — a
            // different claim about the seller, not a formatting detail.
            setShown(decimals > 0 ? Number(current.toFixed(decimals)) : Math.round(current));

            if (progress < 1) frame = requestAnimationFrame(step);
        };

        frame = requestAnimationFrame(step);

        /*
         * Browsers throttle requestAnimationFrame in background tabs, so a
         * visitor who opens the storefront in one and returns later could find
         * the figure frozen at zero. This guarantees it lands on the real
         * number; when the animation did run, it is already there and nothing
         * moves.
         */
        const settle = window.setTimeout(() => setShown(target), duration + 400);

        return () => {
            cancelAnimationFrame(frame);
            window.clearTimeout(settle);
        };
    }, [run, target, isNumeric]);

    if (!isNumeric) return <>{value}</>;

    const display = run ? shown : 0;

    return <>{decimals > 0 ? display.toFixed(decimals).replace('.', ',') : formatNumber(display)}</>;
}

/* -------------------------------------------------------------------------- */

/** "Dipercaya oleh" strip. Greyscale until hover keeps it from shouting. */
export function LogoCloudBlock({ logos, grayscale = true }: { logos: { image?: string; name?: string }[]; grayscale?: boolean }) {
    if (logos.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-6 sm:gap-x-12">
            {logos.map((logo, index) =>
                logo.image ? (
                    <img
                        key={index}
                        src={logo.image}
                        alt={logo.name ?? ''}
                        loading="lazy"
                        className={cn(
                            'h-8 w-auto max-w-[140px] object-contain transition sm:h-10',
                            grayscale && 'opacity-60 grayscale hover:opacity-100 hover:grayscale-0',
                        )}
                    />
                ) : (
                    <span key={index} className="text-sm font-black uppercase tracking-wider opacity-50">
                        {logo.name}
                    </span>
                ),
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */

/**
 * Before/after comparison.
 *
 * Driven by a range input rather than pointer maths: it is draggable, keyboard
 * accessible, and announced correctly by screen readers with no extra work.
 */
export function BeforeAfterBlock({
    before,
    after,
    beforeLabel = 'Sebelum',
    afterLabel = 'Sesudah',
    theme,
}: {
    before?: string;
    after?: string;
    beforeLabel?: string;
    afterLabel?: string;
    theme: StorefrontTheme;
}) {
    const [position, setPosition] = useState(50);

    if (!before || !after) return null;

    return (
        <div className={cn('relative select-none overflow-hidden', theme.radius)}>
            <img src={after} alt={afterLabel} className="block aspect-[4/3] w-full object-cover" />

            <div
                className="absolute inset-0 overflow-hidden"
                style={{ clipPath: `inset(0 ${100 - position}% 0 0)` }}
            >
                <img src={before} alt={beforeLabel} className="block aspect-[4/3] w-full object-cover" />
            </div>

            <span className="pointer-events-none absolute left-3 top-3 rounded-full bg-black/55 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-white backdrop-blur-sm">
                {beforeLabel}
            </span>
            <span className="pointer-events-none absolute right-3 top-3 rounded-full bg-black/55 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-white backdrop-blur-sm">
                {afterLabel}
            </span>

            <div
                className="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow-[0_0_12px_rgba(0,0,0,.5)]"
                style={{ left: `${position}%` }}
            >
                <span className="absolute left-1/2 top-1/2 grid size-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white text-[#12131a] shadow-lg">
                    <MoveHorizontal className="size-5" />
                </span>
            </div>

            <input
                type="range"
                min={0}
                max={100}
                value={position}
                onChange={(event) => setPosition(Number(event.target.value))}
                aria-label={`Geser untuk membandingkan ${beforeLabel} dan ${afterLabel}`}
                className="absolute inset-0 size-full cursor-ew-resize opacity-0"
            />
        </div>
    );
}

/* -------------------------------------------------------------------------- */

interface Step {
    title?: string;
    description?: string;
}

/** Numbered process. Vertical on phones, horizontal once there is room. */
export function StepsBlock({
    steps,
    theme,
    layout = 'vertical',
}: {
    steps: Step[];
    theme: StorefrontTheme;
    layout?: string;
}) {
    if (steps.length === 0) return null;

    if (layout === 'horizontal') {
        return (
            <div className="grid gap-5 @lg:grid-cols-3">
                {steps.map((step, index) => (
                    <div key={index} className="relative">
                        <span className="grid size-11 place-items-center rounded-2xl bg-[var(--sf-primary)] text-lg font-black text-[var(--sf-on-primary)]">
                            {index + 1}
                        </span>
                        <p className="mt-3 font-black leading-snug">{step.title}</p>
                        {step.description && (
                            <p className={cn('mt-1 text-sm leading-6', theme.muted)}>{step.description}</p>
                        )}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <ol className="relative space-y-6">
            {steps.map((step, index) => (
                <li key={index} className="relative flex gap-4">
                    <div className="flex flex-col items-center">
                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-[var(--sf-primary)] font-black text-[var(--sf-on-primary)]">
                            {index + 1}
                        </span>
                        {index < steps.length - 1 && (
                            <span className="mt-1 w-0.5 flex-1 bg-[var(--sf-line)]" aria-hidden />
                        )}
                    </div>

                    <div className="pb-1 pt-1.5">
                        <p className="font-black leading-snug">{step.title}</p>
                        {step.description && (
                            <p className={cn('mt-1 text-sm leading-6', theme.muted)}>{step.description}</p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

/** Shared empty state so an unconfigured block reads as unfinished, not broken. */
export function BlockPlaceholder({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-2xl border border-dashed border-[var(--sf-line)] px-6 py-10 text-center text-sm text-[var(--sf-muted)]">
            {children}
        </div>
    );
}
