import { ArrowRight, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { ButtonLink } from '@/components/ui';
import { cn } from '@/lib/utils';

export function PageHero({
    eyebrow,
    title,
    description,
    children,
    className,
}: {
    eyebrow: string;
    title: ReactNode;
    description: string;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <section className="px-3 pt-3 sm:px-5 sm:pt-5">
            <div className={cn('subpage-hero relative mx-auto overflow-hidden rounded-[2rem] border border-white/70 px-5 py-16 shadow-[0_24px_80px_rgba(91,61,145,.12)] sm:px-8 lg:px-14 lg:py-20', className)}>
                <div className="jy-orb jy-orb-one" aria-hidden="true" />
                <div className="jy-orb jy-orb-two" aria-hidden="true" />
                <div className={cn('relative z-10 mx-auto grid max-w-6xl items-center gap-12', children && 'lg:grid-cols-[.9fr_1.1fr] lg:gap-16')}>
                    <Reveal>
                        <div className={cn(!children && 'mx-auto max-w-3xl text-center')}>
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-white/80 bg-white/60 px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[.18em] text-violet-700 shadow-sm backdrop-blur-xl">
                                <Sparkles className="size-3.5" /> {eyebrow}
                            </span>
                            <h1 className="mt-6 text-balance text-4xl font-extrabold leading-[1.05] tracking-[-.05em] text-[#111119] sm:text-5xl lg:text-6xl">
                                {title}
                            </h1>
                            <p className={cn('mt-5 max-w-xl text-base leading-7 text-neutral-600 sm:text-lg', !children && 'mx-auto')}>{description}</p>
                        </div>
                    </Reveal>
                    {children && <Reveal delay={120}>{children}</Reveal>}
                </div>
            </div>
        </section>
    );
}

export function Reveal({ children, delay = 0, className }: { children: ReactNode; delay?: number; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const node = ref.current;
        if (!node) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setVisible(true);
            return;
        }
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setVisible(true);
                observer.disconnect();
            }
        }, { threshold: 0.1, rootMargin: '0px 0px -35px' });
        observer.observe(node);
        return () => observer.disconnect();
    }, []);

    return <div ref={ref} className={cn('jy-reveal', visible && 'is-visible', className)} style={{ transitionDelay: `${delay}ms` }}>{children}</div>;
}

export function PageCta({
    eyebrow = 'Mulai hari ini',
    title,
    description,
    secondaryHref,
    secondaryLabel,
}: {
    eyebrow?: string;
    title: string;
    description: string;
    secondaryHref?: string;
    secondaryLabel?: string;
}) {
    return (
        <section className="mx-auto max-w-6xl px-5 pb-8 pt-6 sm:px-6">
            <Reveal>
                <div className="relative overflow-hidden rounded-[2rem] bg-[#6c2ee8] px-6 py-14 text-center text-white sm:px-12 sm:py-16">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_20%,rgba(255,255,255,.2),transparent_30%),radial-gradient(circle_at_86%_84%,rgba(255,112,112,.3),transparent_36%)]" />
                    <div className="relative z-10 mx-auto max-w-2xl">
                        <p className="text-[10px] font-extrabold uppercase tracking-[.2em] text-white/65">{eyebrow}</p>
                        <h2 className="mt-4 text-balance text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">{title}</h2>
                        <p className="mx-auto mt-4 max-w-xl text-sm leading-7 text-white/75">{description}</p>
                        <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                            <ButtonLink href="/register" size="lg" className="rounded-full bg-white px-7 text-violet-700 shadow-xl hover:bg-white/90">
                                Mulai jualan gratis <ArrowRight />
                            </ButtonLink>
                            {secondaryHref && secondaryLabel && (
                                <ButtonLink href={secondaryHref} size="lg" className="rounded-full border border-white/25 bg-white/10 px-7 text-white hover:bg-white/15">
                                    {secondaryLabel}
                                </ButtonLink>
                            )}
                        </div>
                    </div>
                </div>
            </Reveal>
        </section>
    );
}
