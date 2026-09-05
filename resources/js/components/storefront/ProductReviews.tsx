import { router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import { cn, formatNumber } from '@/lib/utils';
import type { buildStorefrontTheme } from '@/lib/storefront-theme';
import type { Paginated } from '@/types';

type Theme = ReturnType<typeof buildStorefrontTheme>;

export interface ReviewRow {
    id: number;
    name: string;
    avatar_url: string | null;
    rating: number;
    body: string | null;
    variant_label: string | null;
    media: { url: string; kind: string }[];
    created_at: string;
    seller_reply: string | null;
    seller_replied_at: string | null;
}

export interface ReviewSummary {
    average: number;
    total: number;
    breakdown: Record<string, number>;
    with_media: number;
    with_comment: number;
}

/** Five stars, drawn once and reused at three different sizes. */
export function Stars({ rating, className }: { rating: number; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-0.5', className)} aria-label={`${rating} dari 5 bintang`}>
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    className={cn('size-3.5', star <= rating ? 'fill-amber-400 text-amber-400' : 'text-black/15')}
                    aria-hidden="true"
                />
            ))}
        </span>
    );
}

/**
 * The reviews section.
 *
 * Nobody reads reviews in order. They go straight to the one-star ones, or to
 * the ones with photos, because that is where the truth about sizing and
 * colour actually lives — so the filters are the feature, not decoration, and
 * each says how many it holds so a buyer knows before clicking whether it is
 * worth it.
 *
 * Every piece of text here was typed by a stranger and is rendered as a text
 * node, never as markup.
 */
export function ProductReviews({
    summary,
    reviews,
    filter,
    storeName,
    theme,
}: {
    summary: ReviewSummary;
    reviews: Paginated<ReviewRow>;
    filter: string;
    storeName: string;
    theme: Theme;
}) {
    const [lightbox, setLightbox] = useState<{ url: string; kind: string } | null>(null);

    const go = (next: string) =>
        router.get(window.location.pathname, next === 'semua' ? {} : { ulasan: next }, {
            preserveScroll: true,
            preserveState: false,
        });

    const chips: { key: string; label: string; count: number }[] = [
        { key: 'semua', label: 'Semua', count: summary.total },
        ...[5, 4, 3, 2, 1].map((star) => ({
            key: String(star),
            label: `${star} Bintang`,
            count: summary.breakdown[String(star)] ?? 0,
        })),
        { key: 'komentar', label: 'Dengan Komentar', count: summary.with_comment },
        { key: 'media', label: 'Dengan Foto/Video', count: summary.with_media },
    ];

    return (
        <section className={theme.card}>
            <h2 className={cn('border-b px-4 py-3 text-[0.9375rem] font-semibold sm:px-5', theme.line)}>
                Penilaian Produk
            </h2>

            <div className="p-4 sm:p-5">
                {summary.total === 0 ? (
                    <div className="py-8 text-center">
                        <Stars rating={0} className="justify-center" />
                        <p className="mt-3 text-sm font-medium">Belum ada ulasan</p>
                        <p className={cn('mx-auto mt-1 max-w-sm text-xs leading-5', theme.muted)}>
                            Ulasan cuma bisa ditulis oleh yang sudah beli produk ini, jadi bintangnya bisa dipercaya.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className={cn('flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:gap-8', theme.line)}>
                            <div className="shrink-0 text-center sm:text-left">
                                <p className="text-[var(--sf-primary)]">
                                    <span className="text-3xl font-bold">{summary.average.toFixed(1)}</span>
                                    <span className={cn('text-sm', theme.muted)}> dari 5</span>
                                </p>
                                <Stars rating={Math.round(summary.average)} className="mt-1 justify-center sm:justify-start" />
                                <p className={cn('mt-1 text-xs', theme.muted)}>{formatNumber(summary.total)} penilaian</p>
                            </div>

                            {/* The distribution, because an average of 4.1 made
                                of fives and ones is a different product from
                                one made entirely of fours. */}
                            <div className="min-w-0 flex-1 space-y-1">
                                {[5, 4, 3, 2, 1].map((star) => {
                                    const count = summary.breakdown[String(star)] ?? 0;
                                    const share = summary.total > 0 ? (count / summary.total) * 100 : 0;

                                    return (
                                        <div key={star} className="flex items-center gap-2 text-xs">
                                            <span className={cn('w-3 text-right', theme.muted)}>{star}</span>
                                            <Star className="size-3 shrink-0 fill-amber-400 text-amber-400" aria-hidden="true" />
                                            <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-[color-mix(in_oklab,var(--sf-fg)_8%,transparent)]">
                                                <span className="block h-full rounded-full bg-amber-400" style={{ width: `${share}%` }} />
                                            </span>
                                            <span className={cn('w-8 text-right tabular-nums', theme.muted)}>{count}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-1.5">
                            {chips.map((chip) => (
                                <button
                                    key={chip.key}
                                    type="button"
                                    disabled={chip.count === 0 && chip.key !== 'semua'}
                                    onClick={() => go(chip.key)}
                                    className={cn(
                                        'rounded border px-2.5 py-1 text-xs transition disabled:opacity-40',
                                        filter === chip.key
                                            ? 'border-[var(--sf-primary)] bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] font-semibold text-[var(--sf-primary)]'
                                            : 'border-[var(--sf-line)] hover:border-[var(--sf-primary)]',
                                    )}
                                >
                                    {chip.label} ({formatNumber(chip.count)})
                                </button>
                            ))}
                        </div>

                        <ul className={cn('mt-2 divide-y', theme.line)}>
                            {reviews.data.map((review) => (
                                <li key={review.id} className="py-4">
                                    <div className="flex gap-3">
                                        {review.avatar_url ? (
                                            <img src={review.avatar_url} alt="" className="size-8 shrink-0 rounded-full object-cover" />
                                        ) : (
                                            <span className="grid size-8 shrink-0 place-items-center rounded-full bg-[color-mix(in_oklab,var(--sf-fg)_8%,transparent)] text-[0.6875rem] font-semibold">
                                                {review.name[0]?.toUpperCase()}
                                            </span>
                                        )}

                                        <div className="min-w-0 flex-1">
                                            <p className="text-[0.8125rem] font-medium">{review.name}</p>
                                            <Stars rating={review.rating} className="mt-0.5" />

                                            <p className={cn('mt-1 text-[0.6875rem]', theme.muted)}>
                                                {review.created_at}
                                                {review.variant_label && <> · Variasi: {review.variant_label}</>}
                                            </p>

                                            {review.body && (
                                                <p className="mt-2 whitespace-pre-wrap break-words text-[0.8125rem] leading-6">
                                                    {review.body}
                                                </p>
                                            )}

                                            {review.media.length > 0 && (
                                                <div className="mt-2 flex flex-wrap gap-1.5">
                                                    {review.media.map((item, index) => (
                                                        <button
                                                            key={index}
                                                            type="button"
                                                            onClick={() => setLightbox(item)}
                                                            className="relative size-16 overflow-hidden rounded border border-[var(--sf-line)]"
                                                            aria-label={item.kind === 'video' ? 'Putar video ulasan' : 'Lihat foto ulasan'}
                                                        >
                                                            {item.kind === 'video' ? (
                                                                <>
                                                                    <video src={item.url} className="size-full bg-black object-cover" muted preload="none" />
                                                                    <span className="absolute inset-0 grid place-items-center bg-black/30 text-[0.625rem] font-semibold text-white">
                                                                        VIDEO
                                                                    </span>
                                                                </>
                                                            ) : (
                                                                <img src={item.url} alt="" loading="lazy" className="size-full object-cover" />
                                                            )}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            {review.seller_reply && (
                                                <div className={cn('mt-2.5 rounded-lg p-2.5 text-[0.8125rem] leading-6', 'bg-[color-mix(in_oklab,var(--sf-fg)_5%,transparent)]')}>
                                                    <p className="text-[0.6875rem] font-semibold text-[var(--sf-primary)]">
                                                        Balasan {storeName}
                                                        {review.seller_replied_at && (
                                                            <span className={cn('ml-1.5 font-normal', theme.muted)}>{review.seller_replied_at}</span>
                                                        )}
                                                    </p>
                                                    <p className="mt-1 whitespace-pre-wrap break-words">{review.seller_reply}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        {reviews.last_page > 1 && (
                            <div className="mt-3 flex flex-wrap justify-center gap-1.5">
                                {reviews.links
                                    .filter((link) => !['&laquo; Previous', 'Next &raquo;'].includes(link.label))
                                    .map((link, index) => (
                                        <button
                                            key={index}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                            className={cn(
                                                'min-w-8 rounded border px-2 py-1 text-xs disabled:opacity-40',
                                                link.active
                                                    ? 'border-[var(--sf-primary)] font-semibold text-[var(--sf-primary)]'
                                                    : 'border-[var(--sf-line)]',
                                            )}
                                        >
                                            {link.label}
                                        </button>
                                    ))}
                            </div>
                        )}
                    </>
                )}
            </div>

            {lightbox && (
                <div
                    className="fixed inset-0 z-[90] grid place-items-center bg-black/80 p-4"
                    onClick={() => setLightbox(null)}
                    role="dialog"
                    aria-label="Media ulasan"
                >
                    {lightbox.kind === 'video' ? (
                        <video src={lightbox.url} controls autoPlay className="max-h-full max-w-full rounded-lg" />
                    ) : (
                        <img src={lightbox.url} alt="" className="max-h-full max-w-full rounded-lg" />
                    )}
                </div>
            )}
        </section>
    );
}
