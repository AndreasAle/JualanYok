import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, X } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useState } from 'react';
import { Button } from '@/components/ui';
import type { Tour, TourStep } from '@/types';

interface Box {
    top: number;
    left: number;
    width: number;
    height: number;
}

/** Breathing room between the highlight and the element inside it. */
const PAD = 8;
const CARD_WIDTH = 340;
const GAP = 14;

function boxFor(target: string | null): Box | null {
    if (!target) {
        return null;
    }

    const el = document.querySelector<HTMLElement>(`[data-tour="${CSS.escape(target)}"]`);

    if (!el) {
        return null;
    }

    const rect = el.getBoundingClientRect();

    // An element scrolled out of view, or collapsed to nothing at this
    // breakpoint, is not something to point at.
    if (rect.width === 0 && rect.height === 0) {
        return null;
    }

    return {
        top: rect.top - PAD,
        left: rect.left - PAD,
        width: rect.width + PAD * 2,
        height: rect.height + PAD * 2,
    };
}

/**
 * Places the card next to the highlight without letting it leave the viewport.
 *
 * The requested placement is a preference, not a promise: a card pinned below
 * an element near the bottom of a phone screen would be half off-screen, so it
 * flips. Everything is finally clamped, which is what stops the last step of a
 * tour from being unreadable on a small display.
 */
function placeCard(box: Box | null, placement: TourStep['placement'], cardHeight: number) {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const margin = 12;
    const width = Math.min(CARD_WIDTH, vw - margin * 2);

    if (!box) {
        return {
            top: Math.max(margin, vh / 2 - cardHeight / 2),
            left: vw / 2 - width / 2,
            width,
        };
    }

    let top: number;
    let left: number;

    const below = box.top + box.height + GAP;
    const above = box.top - cardHeight - GAP;

    switch (placement) {
        case 'right':
            top = box.top;
            left = box.left + box.width + GAP;
            break;
        case 'left':
            top = box.top;
            left = box.left - width - GAP;
            break;
        case 'top':
            top = above;
            left = box.left;
            break;
        default:
            top = below;
            left = box.left;
    }

    // Flip a vertical placement that would run off the edge, and only then
    // clamp — clamping first would silently park the card on top of the very
    // thing it is describing.
    if (placement === 'top' && above < margin) {
        top = below;
    }
    if ((placement === 'bottom' || !placement) && below + cardHeight > vh - margin) {
        top = above >= margin ? above : margin;
    }
    if (placement === 'left' && left < margin) {
        left = box.left + box.width + GAP;
    }
    if (placement === 'right' && left + width > vw - margin) {
        left = box.left - width - GAP;
    }

    return {
        top: Math.min(Math.max(margin, top), Math.max(margin, vh - cardHeight - margin)),
        left: Math.min(Math.max(margin, left), Math.max(margin, vw - width - margin)),
        width,
    };
}

/**
 * The guided tour overlay.
 *
 * A spotlight rather than a modal: the control being explained stays visible
 * and in place, so the explanation is attached to the thing itself instead of
 * to a picture of it. Steps whose target is not on the page are dropped before
 * the tour starts, which is what lets one tour definition serve a screen that
 * renders different controls for different creators.
 */
export default function TourGuide({ tour, onClose }: { tour: Tour; onClose?: () => void }) {
    const [steps, setSteps] = useState<TourStep[]>([]);
    const [index, setIndex] = useState(0);
    const [box, setBox] = useState<Box | null>(null);
    const [card, setCard] = useState({ top: 0, left: 0, width: CARD_WIDTH });
    const [cardEl, setCardEl] = useState<HTMLDivElement | null>(null);
    const [ready, setReady] = useState(false);

    // Wait a frame before measuring: on a fresh Inertia visit the page is in
    // the DOM but has not been laid out, and every target would measure zero.
    useEffect(() => {
        const id = requestAnimationFrame(() => {
            setSteps(tour.steps.filter((step) => !step.target || boxFor(step.target)));
            setIndex(0);
            setReady(true);
        });

        return () => cancelAnimationFrame(id);
    }, [tour]);

    const step: TourStep | undefined = steps[index];

    const finish = useCallback(
        (outcome: 'completed' | 'skipped') => {
            setReady(false);
            onClose?.();
            router.post(
                `/panduan/${tour.id}`,
                { outcome, step: index },
                { preserveScroll: true, preserveState: true, only: ['tour'] },
            );
        },
        [tour.id, index, onClose],
    );

    // Bring the target into view, then measure it and the card together.
    useLayoutEffect(() => {
        if (!ready || !step) {
            return;
        }

        if (step.target) {
            document
                .querySelector<HTMLElement>(`[data-tour="${CSS.escape(step.target)}"]`)
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }

        const measure = () => {
            const next = boxFor(step.target);
            setBox(next);
            setCard(placeCard(next, step.placement, cardEl?.offsetHeight ?? 200));
        };

        measure();

        // The smooth scroll above settles over a few frames, and a resize or a
        // sidebar collapse moves the target under the highlight.
        const timer = window.setTimeout(measure, 320);
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);

        return () => {
            window.clearTimeout(timer);
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [ready, step, cardEl]);

    useEffect(() => {
        if (!ready) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                finish('skipped');
            }
            if (event.key === 'ArrowRight') {
                setIndex((i) => Math.min(i + 1, steps.length - 1));
            }
            if (event.key === 'ArrowLeft') {
                setIndex((i) => Math.max(i - 1, 0));
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [ready, steps.length, finish]);

    if (!ready || !step) {
        return null;
    }

    const last = index === steps.length - 1;

    return (
        <div className="fixed inset-0 z-[100]" role="dialog" aria-modal="true" aria-label={tour.title}>
            {/*
                The dimming is one enormous spread shadow cast by the highlight
                rectangle, so there is exactly one hole and no seams between
                four separately positioned panels.
            */}
            {box ? (
                <div
                    className="pointer-events-none absolute rounded-xl ring-2 ring-white/70 transition-all duration-300 ease-out"
                    style={{
                        top: box.top,
                        left: box.left,
                        width: box.width,
                        height: box.height,
                        boxShadow: '0 0 0 9999px rgba(12, 10, 22, .62)',
                    }}
                />
            ) : (
                <div className="absolute inset-0 bg-[rgba(12,10,22,.62)]" />
            )}

            {/* Clicking the dimmed area leaves the tour, which is what people
                expect of an overlay and saves hunting for the close button. */}
            <button
                type="button"
                className="absolute inset-0 cursor-default"
                onClick={() => finish('skipped')}
                tabIndex={-1}
                aria-label="Tutup panduan"
            />

            <div
                ref={setCardEl}
                className="absolute rounded-[1rem] bg-white p-4 text-[#1a1824] shadow-[0_18px_60px_rgba(10,8,20,.45)] dark:bg-[#1c1b25] dark:text-white"
                style={{ top: card.top, left: card.left, width: card.width }}
            >
                <div className="flex items-start justify-between gap-3">
                    <p className="text-[0.9375rem] font-semibold leading-snug">{step.title}</p>
                    <button
                        type="button"
                        onClick={() => finish('skipped')}
                        className="-mr-1 -mt-1 grid size-7 shrink-0 place-items-center rounded-lg text-black/40 transition hover:bg-black/5 hover:text-black/70 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Lewati panduan"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <p className="mt-2 text-[0.8125rem] leading-6 text-black/65 dark:text-white/65">{step.body}</p>

                <div className="mt-4 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-1.5" aria-hidden="true">
                        {steps.map((_, i) => (
                            <span
                                key={i}
                                className={
                                    i === index
                                        ? 'h-1.5 w-5 rounded-full bg-[var(--color-brand-600)] transition-all'
                                        : 'size-1.5 rounded-full bg-black/15 transition-all dark:bg-white/20'
                                }
                            />
                        ))}
                    </div>

                    <div className="flex items-center gap-1.5">
                        {index > 0 && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setIndex((i) => i - 1)}
                                aria-label="Langkah sebelumnya"
                            >
                                <ArrowLeft />
                            </Button>
                        )}
                        <Button size="sm" onClick={() => (last ? finish('completed') : setIndex((i) => i + 1))}>
                            {last ? 'Selesai' : <>Lanjut <ArrowRight /></>}
                        </Button>
                    </div>
                </div>

                <p className="mt-3 text-center text-[0.6875rem] text-black/35 dark:text-white/35">
                    Langkah {index + 1} dari {steps.length} · tekan Esc untuk keluar
                </p>
            </div>
        </div>
    );
}
