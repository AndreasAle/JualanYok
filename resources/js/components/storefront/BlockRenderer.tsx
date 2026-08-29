import { router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight, Camera, ChevronDown, Clock, ExternalLink, Link2, MessageCircle, Music2,
    PlaySquare, Quote, Star, Tag,
} from 'lucide-react';
import { useEffect, useState, type CSSProperties, type FormEvent } from 'react';
import { ProductCard } from '@/components/storefront/ProductCard';
import {
    BeforeAfterBlock, CarouselBlock, LogoCloudBlock, MarqueeBlock, StatsBlock, StepsBlock, useReveal,
} from '@/components/storefront/ShowcaseBlocks';
import { blockStyleClasses, blockStyleVars, type BlockStyleTokens } from '@/lib/block-style';
import { EMBED_PROVIDERS, toEmbedUrl } from '@/lib/embed';
import { cn } from '@/lib/utils';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import type { StorefrontBlock, StorefrontProduct } from '@/types';

const PRODUCT_BLOCKS = new Set(['PRODUCT', 'PRODUCT_COLLECTION', 'FEATURED_PRODUCTS', 'AFFILIATE_PRODUCT']);

export interface RendererContext {
    storeUsername: string;
    storeName?: string;
    theme: StorefrontTheme;
    productLayout: 'grid' | 'list';
    affiliateMode?: boolean;
    isPreview: boolean;
    onBuy: (product: StorefrontProduct) => void;
    /** Absent in the builder preview, where nothing is actually purchasable. */
    onAddToCart?: (product: StorefrontProduct) => void;
}

/**
 * Renders one storefront block. Every type in the BlockType enum has a real
 * renderer; an unknown type degrades to nothing rather than an empty box.
 */
export function BlockRenderer({ block, ctx }: { block: StorefrontBlock; ctx: RendererContext }) {
    const content = block.content ?? {};
    const t = ctx.theme;

    const trackClick = () => {
        if (ctx.isPreview) return;

        router.post(`/${ctx.storeUsername}/blocks/${block.id}/click`, {}, {
            preserveScroll: true,
            preserveState: true,
            only: [],
        });
    };

    const body = (() => {
        switch (block.type) {
            case 'HEADING':
                return (
                    <h2
                        className={cn(
                            'font-extrabold tracking-tight text-balance',
                            content.size === 'sm'
                                ? 'text-xl'
                                : content.size === 'lg'
                                  ? 'text-3xl sm:text-4xl'
                                  : 'text-2xl sm:text-3xl',
                            content.align === 'left' ? 'text-left' : 'text-center',
                        )}
                    >
                        {content.text ?? block.title}
                    </h2>
                );

            case 'TEXT':
                return (
                    <div
                        className={cn(
                            'space-y-3 text-[15px] leading-relaxed',
                            t.muted,
                            content.align === 'center' && 'text-center',
                        )}
                    >
                        {String(content.body ?? '')
                            .split('\n')
                            .filter(Boolean)
                            .map((paragraph: string, i: number) => (
                                <p key={i}>{paragraph}</p>
                            ))}
                    </div>
                );

            case 'LINK_BUTTON':
                return (
                    <a
                        href={content.url ?? '#'}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        onClick={trackClick}
                        className={cn(t.btnPrimary, 'h-14 w-full px-6 text-base shadow-md')}
                    >
                        {content.label ?? block.title ?? 'Buka Link'}
                        <ArrowUpRight className="size-4.5" />
                    </a>
                );

            case 'SOCIAL_LINKS': {
                const links = Object.entries((content.links ?? {}) as Record<string, string>).filter(
                    ([, url]) => !!url,
                );

                if (links.length === 0) return null;

                return (
                    <div className={cn(t.card, 'flex flex-wrap items-center justify-center gap-2 p-3')}>
                        {links.map(([platform, url]) => (
                            <a
                                key={platform}
                                href={url}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                onClick={trackClick}
                                aria-label={platform}
                                title={platform}
                                className="flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-semibold capitalize transition-colors hover:bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] hover:text-[var(--sf-primary)]"
                            >
                                <SocialIcon platform={platform} />
                                <span className="hidden sm:inline">{platform}</span>
                            </a>
                        ))}
                    </div>
                );
            }

            case 'IMAGE':
                return content.url ? (
                    <img
                        src={content.url}
                        alt={content.alt ?? ''}
                        loading="lazy"
                        className="w-full rounded-2xl object-cover shadow-sm"
                    />
                ) : null;

            case 'GALLERY': {
                const images = (content.images ?? []) as { url: string; alt?: string }[];

                if (images.length === 0) return null;

                return (
                    <div className="grid grid-cols-2 gap-2.5 @2xl:grid-cols-3 @4xl:grid-cols-4">
                        {images.map((image, i) => (
                            <img
                                key={i}
                                src={image.url}
                                alt={image.alt ?? ''}
                                loading="lazy"
                                className="aspect-square w-full rounded-xl object-cover transition-transform hover:scale-[1.03]"
                            />
                        ))}
                    </div>
                );
            }

            case 'VIDEO': {
                const target = toEmbedUrl(content.url ?? '');

                if (!target) {
                    return ctx.isPreview ? (
                        <p
                            className={cn(
                                'rounded-xl border border-dashed p-4 text-center text-xs',
                                t.line,
                                t.muted,
                            )}
                        >
                            Tempel link video YouTube atau Vimeo di pengaturan block ini.
                        </p>
                    ) : null;
                }

                return (
                    <div className="aspect-video overflow-hidden rounded-2xl shadow-sm">
                        <iframe
                            src={target.url}
                            title={block.title ?? 'Video'}
                            allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
                            allowFullScreen
                            loading="lazy"
                            className="size-full border-0"
                        />
                    </div>
                );
            }

            case 'DIVIDER':
                return <hr className={cn('border-t', t.line)} />;

            case 'SPACER':
                return <div style={{ height: `${content.height ?? 24}px` }} aria-hidden="true" />;

            case 'FAQ': {
                const items = (content.items ?? []) as { question: string; answer: string }[];

                if (items.length === 0) return null;

                return (
                    <div className={cn(t.card, 'divide-y overflow-hidden', t.line)}>
                        {items.map((item, i) => (
                            <details key={i} className="group">
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 p-4 font-semibold marker:hidden sm:p-5">
                                    {item.question}
                                    <ChevronDown className="size-4.5 shrink-0 transition-transform group-open:rotate-180" />
                                </summary>
                                <p className={cn('px-4 pb-4 text-sm leading-relaxed sm:px-5 sm:pb-5', t.muted)}>
                                    {item.answer}
                                </p>
                            </details>
                        ))}
                    </div>
                );
            }

            case 'TESTIMONIAL': {
                const items = (content.items ?? []) as {
                    name: string;
                    role?: string;
                    text: string;
                    rating?: number;
                }[];

                if (items.length === 0) return null;

                return (
                    <div className="grid gap-3 @2xl:grid-cols-2 @4xl:grid-cols-3">
                        {items.map((item, i) => (
                            <figure key={i} className={cn(t.card, 'flex flex-col p-5')}>
                                <Quote className="size-6 text-[var(--sf-primary)] opacity-30" />

                                {item.rating && (
                                    <div className="mt-2 flex gap-0.5" aria-label={`${item.rating} dari 5`}>
                                        {Array.from({ length: 5 }).map((_, s) => (
                                            <Star
                                                key={s}
                                                className={cn(
                                                    'size-4',
                                                    s < item.rating!
                                                        ? 'fill-amber-400 text-amber-400'
                                                        : 'text-current opacity-20',
                                                )}
                                            />
                                        ))}
                                    </div>
                                )}

                                <blockquote className="mt-2.5 flex-1 text-[15px] leading-relaxed">
                                    “{item.text}”
                                </blockquote>

                                <figcaption className="mt-4 flex items-center gap-3">
                                    <span
                                        className="grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold"
                                        style={{
                                            background: 'var(--sf-primary)',
                                            color: 'var(--sf-on-primary)',
                                        }}
                                    >
                                        {item.name?.[0]?.toUpperCase() ?? '?'}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-bold">{item.name}</span>
                                        {item.role && (
                                            <span className={cn('block truncate text-xs', t.muted)}>{item.role}</span>
                                        )}
                                    </span>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                );
            }

            case 'COUNTDOWN':
                return <Countdown target={content.ends_at} label={content.label} theme={t} />;

            case 'PROMO_BANNER':
                return (
                    <div
                        className="relative overflow-hidden rounded-2xl p-6 text-center shadow-lg sm:p-8"
                        style={{
                            background: content.image
                                ? `linear-gradient(135deg, color-mix(in oklab, var(--sf-primary) 78%, transparent), color-mix(in oklab, var(--sf-accent) 78%, transparent)), center / cover no-repeat url(${content.image})`
                                : 'linear-gradient(135deg, var(--sf-primary), var(--sf-accent))',
                            color: 'var(--sf-on-primary)',
                        }}
                    >
                        {!content.image && (
                            <div
                                className="pointer-events-none absolute inset-0 opacity-20"
                                style={{
                                    backgroundImage:
                                        'radial-gradient(circle at 18% 20%, #fff 1.5px, transparent 1.5px)',
                                    backgroundSize: '26px 26px',
                                }}
                                aria-hidden="true"
                            />
                        )}

                        <div className="relative">
                            <p className="text-xl font-extrabold text-balance sm:text-2xl">{content.headline}</p>
                            {content.subtext && (
                                <p className="mx-auto mt-2 max-w-md text-sm opacity-90">{content.subtext}</p>
                            )}

                            {content.code && (
                                <p className="mt-5 inline-flex items-center gap-2 rounded-xl border-2 border-dashed border-current/50 px-5 py-2.5 font-mono text-lg font-black tracking-[0.2em]">
                                    <Tag className="size-4" />
                                    {content.code}
                                </p>
                            )}
                        </div>
                    </div>
                );

            case 'LEAD_FORM':
                return <LeadForm block={block} ctx={ctx} />;

            case 'WHATSAPP_CTA': {
                const number = String(content.number ?? '').replace(/\D/g, '');
                const text = encodeURIComponent(content.message ?? 'Halo, aku mau tanya soal produkmu.');

                return (
                    <a
                        href={`https://wa.me/${number}?text=${text}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={trackClick}
                        className={cn(
                            t.btnContact,
                            'flex h-14 w-full px-6 text-base',
                        )}
                    >
                        <MessageCircle className="size-5" />
                        {content.label ?? 'Chat WhatsApp'}
                    </a>
                );
            }

            case 'PRODUCT':
            case 'PRODUCT_COLLECTION':
            case 'FEATURED_PRODUCTS':
            case 'AFFILIATE_PRODUCT': {
                const products = (content.products ?? []) as StorefrontProduct[];

                if (products.length === 0) return null;

                const asList = ctx.productLayout === 'list';

                return (
                    <div
                        className={cn(
                            asList
                                ? 'space-y-3'
                                : products.length === 1
                                  ? 'grid gap-3 @lg:max-w-sm'
                                  : 'grid grid-cols-2 gap-2.5 @2xl:gap-4 @3xl:grid-cols-3 @5xl:grid-cols-4',
                        )}
                    >
                        {products.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                                theme={t}
                                layout={asList ? 'list' : 'grid'}
                                onBuy={() => {
                                    trackClick();
                                    ctx.onBuy(product);
                                }}
                                onAddToCart={
                                    ctx.onAddToCart
                                        ? () => {
                                              trackClick();
                                              ctx.onAddToCart!(product);
                                          }
                                        : undefined
                                }
                                onOpen={() => {
                                    trackClick();

                                    if (product.slug && !ctx.isPreview) {
                                        router.visit(`/${ctx.storeUsername}/p/${product.slug}`);
                                        return;
                                    }

                                    ctx.onBuy(product);
                                }}
                            />
                        ))}
                    </div>
                );
            }

            case 'ARTICLE':
                return (
                    <article className={cn(t.card, 'p-6')}>
                        <h3 className="text-lg font-extrabold">{content.title ?? block.title}</h3>
                        {content.excerpt && (
                            <p className={cn('mt-2 text-sm leading-relaxed', t.muted)}>{content.excerpt}</p>
                        )}
                        {content.url && (
                            <a
                                href={content.url}
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                onClick={trackClick}
                                className="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-[var(--sf-primary)] hover:underline"
                            >
                                Baca selengkapnya
                                <ExternalLink className="size-3.5" />
                            </a>
                        )}
                    </article>
                );

            case 'CAROUSEL':
                return (
                    <CarouselBlock
                        slides={(content.slides ?? []) as any[]}
                        theme={t}
                        aspect={content.aspect}
                        autoplay={!!content.autoplay && !ctx.isPreview}
                    />
                );

            case 'MARQUEE':
                return (
                    <MarqueeBlock
                        items={(content.items ?? []).filter(Boolean)}
                        speed={content.speed}
                        reverse={!!content.reverse}
                        separator={content.separator || '✦'}
                    />
                );

            case 'STATS':
                return <StatsBlock stats={(content.stats ?? []) as any[]} theme={t} />;

            case 'LOGO_CLOUD':
                return (
                    <LogoCloudBlock
                        logos={(content.logos ?? []) as any[]}
                        grayscale={content.grayscale !== false}
                    />
                );

            case 'BEFORE_AFTER':
                return (
                    <BeforeAfterBlock
                        before={content.before}
                        after={content.after}
                        beforeLabel={content.before_label || 'Sebelum'}
                        afterLabel={content.after_label || 'Sesudah'}
                        theme={t}
                    />
                );

            case 'STEPS':
                return <StepsBlock steps={(content.steps ?? []) as any[]} theme={t} layout={content.layout} />;

            case 'EMBED': {
                // Providers block their public pages from being framed, and an
                // allowlist alone is not enough — the URL has to be converted
                // to the provider's player endpoint. That also keeps arbitrary
                // third-party pages out of the storefront.
                const target = toEmbedUrl(content.url ?? '');

                if (!target) {
                    return ctx.isPreview ? (
                        <p
                            className={cn(
                                'rounded-xl border border-dashed p-4 text-center text-xs',
                                t.line,
                                t.muted,
                            )}
                        >
                            Link ini belum bisa di-embed. Yang didukung: {EMBED_PROVIDERS}.
                        </p>
                    ) : null;
                }

                return (
                    <div
                        className={cn(
                            'overflow-hidden rounded-2xl shadow-sm',
                            target.aspect === 'audio' ? 'h-[152px]' : 'aspect-video',
                        )}
                    >
                        <iframe
                            src={target.url}
                            title={block.title ?? 'Embed'}
                            loading="lazy"
                            allowFullScreen
                            allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
                            className="size-full border-0"
                        />
                    </div>
                );
            }

            default:
                return null;
        }
    })();

    if (!body) return null;

    const showHeading = block.title && !['HEADING', 'DIVIDER', 'SPACER'].includes(block.type);

    const style = (block.style ?? {}) as BlockStyleTokens;

    // The builder preview shows the finished state: an editor watching blocks
    // fade in on every keystroke cannot judge the design.
    const revealRef = useReveal<HTMLElement>(!ctx.isPreview && (style.animation ?? 'none') !== 'none');

    return (
        <section
            ref={revealRef}
            id={`block-${block.id}`}
            className={cn(
                'scroll-mt-32',
                block.visible_mobile ? '' : 'hidden sm:block',
                block.visible_desktop ? '' : 'sm:hidden',
                blockStyleClasses(style),
            )}
            style={blockStyleVars(style) as CSSProperties}
        >
            {showHeading && (
                <div className="mb-5 flex items-end justify-between gap-4 sm:mb-6">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[.16em] text-[var(--sf-primary)]">
                            {PRODUCT_BLOCKS.has(block.type) ? (ctx.affiliateMode ? 'Etalase pilihan' : 'Pilihan untuk kamu') : 'Dari toko ini'}
                        </p>
                        <h2 className="mt-1 text-xl font-black tracking-[-.025em] sm:text-2xl">{block.title}</h2>
                        {PRODUCT_BLOCKS.has(block.type) && (
                            <p className={cn('mt-1 text-xs leading-5 sm:text-sm', t.muted)}>{ctx.affiliateMode ? `Klik produk untuk melihat harga terbaru di marketplace.` : `Kurasi terbaik dari ${ctx.storeName ?? `@${ctx.storeUsername}`}.`}</p>
                        )}
                    </div>
                    {PRODUCT_BLOCKS.has(block.type) && <span className="hidden rounded-full border border-[var(--sf-line)] px-3 py-1.5 text-[10px] font-bold sm:inline-flex">{ctx.affiliateMode ? 'Creator picks' : 'Official selection'}</span>}
                </div>
            )}
            {body}
        </section>
    );
}

/* -------------------------------------------------------------------------- */

function LeadForm({ block, ctx }: { block: StorefrontBlock; ctx: RendererContext }) {
    const content = block.content ?? {};
    const t = ctx.theme;

    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({
        block_id: block.id,
        name: '',
        email: '',
        phone: '',
        consent: false as boolean,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (ctx.isPreview) return;

        post(`/${ctx.storeUsername}/leads`, { preserveScroll: true, onSuccess: () => reset() });
    };

    const field = cn(
        'w-full rounded-xl border bg-transparent px-4 py-3 text-sm outline-none transition-colors',
        t.line,
        'focus:border-[var(--sf-primary)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--sf-primary)_28%,transparent)]',
    );

    return (
        <form onSubmit={submit} className={cn(t.card, 'overflow-hidden')}>
            <div
                className="px-6 py-5"
                style={{
                    background:
                        'linear-gradient(135deg, color-mix(in oklab, var(--sf-primary) 12%, transparent), color-mix(in oklab, var(--sf-accent) 12%, transparent))',
                }}
            >
                <p className="text-lg font-extrabold">{content.headline ?? 'Gabung dulu yuk'}</p>
                {content.subtext && <p className={cn('mt-1 text-sm', t.muted)}>{content.subtext}</p>}
            </div>

            <div className="space-y-3 p-6">
                {content.ask_name !== false && (
                    <div>
                        <input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Nama kamu"
                            aria-label="Nama"
                            className={field}
                        />
                        {errors.name && <p className="mt-1 text-xs text-rose-500">{errors.name}</p>}
                    </div>
                )}

                <div>
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="Email kamu"
                        aria-label="Email"
                        className={field}
                    />
                    {errors.email && <p className="mt-1 text-xs text-rose-500">{errors.email}</p>}
                </div>

                {content.ask_phone && (
                    <div>
                        <input
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="Nomor WhatsApp"
                            aria-label="Nomor WhatsApp"
                            className={field}
                        />
                        {errors.phone && <p className="mt-1 text-xs text-rose-500">{errors.phone}</p>}
                    </div>
                )}

                <label className={cn('flex items-start gap-2.5 text-xs leading-relaxed', t.muted)}>
                    <input
                        type="checkbox"
                        checked={data.consent}
                        onChange={(e) => setData('consent', e.target.checked)}
                        className="mt-0.5 size-4 shrink-0 accent-[var(--sf-primary)]"
                        required
                    />
                    {content.consent_text ?? 'Aku setuju dihubungi lewat email/WhatsApp soal produk ini.'}
                </label>
                {errors.consent && <p className="text-xs text-rose-500">{errors.consent}</p>}

                <button
                    type="submit"
                    disabled={processing || ctx.isPreview}
                    className={cn(t.btnPrimary, 'h-12 w-full px-5 text-sm')}
                >
                    {content.button_label ?? 'Kirim'}
                </button>

                {recentlySuccessful && (
                    <p className="text-center text-sm font-semibold text-emerald-500">Makasih! Datamu udah masuk.</p>
                )}
            </div>
        </form>
    );
}

function Countdown({
    target,
    label,
    theme,
}: {
    target?: string;
    label?: string;
    theme: StorefrontTheme;
}) {
    const [remaining, setRemaining] = useState(() => diff(target));

    useEffect(() => {
        const timer = setInterval(() => setRemaining(diff(target)), 1000);
        return () => clearInterval(timer);
    }, [target]);

    if (!target) return null;

    const over = remaining.total <= 0;

    return (
        <div
            className="rounded-2xl p-6 text-center shadow-lg"
            style={{
                background: 'linear-gradient(135deg, var(--sf-primary), var(--sf-accent))',
                color: 'var(--sf-on-primary)',
            }}
        >
            <p className="flex items-center justify-center gap-1.5 text-sm font-semibold opacity-90">
                <Clock className="size-4" />
                {label ?? 'Promo berakhir dalam'}
            </p>

            {over ? (
                <p className="mt-3 text-xl font-extrabold">Waktunya sudah habis</p>
            ) : (
                <div className="mt-4 flex justify-center gap-2 sm:gap-3">
                    {[
                        { value: remaining.days, unit: 'Hari' },
                        { value: remaining.hours, unit: 'Jam' },
                        { value: remaining.minutes, unit: 'Menit' },
                        { value: remaining.seconds, unit: 'Detik' },
                    ].map((part) => (
                        <div
                            key={part.unit}
                            className="min-w-16 rounded-xl bg-white/20 px-3 py-2.5 backdrop-blur-sm sm:min-w-20"
                        >
                            <p className="text-2xl font-black tabular-nums sm:text-3xl">
                                {String(part.value).padStart(2, '0')}
                            </p>
                            <p className="text-[10px] font-bold uppercase tracking-wider opacity-80">{part.unit}</p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function diff(target?: string) {
    if (!target) return { total: 0, days: 0, hours: 0, minutes: 0, seconds: 0 };

    const total = new Date(target).getTime() - Date.now();

    return {
        total,
        days: Math.max(0, Math.floor(total / 86400000)),
        hours: Math.max(0, Math.floor((total / 3600000) % 24)),
        minutes: Math.max(0, Math.floor((total / 60000) % 60)),
        seconds: Math.max(0, Math.floor((total / 1000) % 60)),
    };
}

function SocialIcon({ platform }: { platform: string }) {
    switch (platform.toLowerCase()) {
        case 'instagram':
            return <Camera className="size-5" />;
        case 'youtube':
            return <PlaySquare className="size-5" />;
        case 'tiktok':
            return <Music2 className="size-5" />;
        case 'whatsapp':
            return <MessageCircle className="size-5" />;
        default:
            return <Link2 className="size-5" />;
    }
}
