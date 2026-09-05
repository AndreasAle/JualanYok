import { router, useForm } from '@inertiajs/react';
import { MessageSquare, Star } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Pagination, StatCard } from '@/components/shared';
import { Button, Card, EmptyState } from '@/components/ui';
import { cn, formatNumber, initials } from '@/lib/utils';
import type { Paginated } from '@/types';

interface ReviewRow {
    id: number;
    name: string;
    avatar_url: string | null;
    rating: number;
    body: string | null;
    variant_label: string | null;
    media: { url: string; kind: string }[];
    created_at: string;
    seller_reply: string | null;
    product: { name: string | null; thumbnail_url: string | null; url: string | null };
}

const TABS = [
    { key: 'semua', label: 'Semua' },
    { key: 'perlu-dibalas', label: 'Belum dibalas' },
    { key: 'rendah', label: '3 bintang ke bawah' },
];

/**
 * What buyers said, and the one thing a seller can do about it.
 *
 * Ordered by what needs an answer rather than by date. An unanswered one-star
 * review is the most expensive thing on this page — it is read by everyone
 * considering that product — and sorting by newest is how it stays unanswered
 * under yesterday's five-star ones.
 */
export default function CreatorReviews({
    reviews,
    filter,
    stats,
}: {
    reviews: Paginated<ReviewRow>;
    filter: string;
    stats: { total: number; unanswered: number; low: number; average: number };
}) {
    return (
        <DashboardLayout title="Ulasan" area="creator">
            <div className="mb-5">
                <h1 className="text-[1.375rem] font-semibold tracking-[-.02em]">Ulasan pembeli</h1>
                <p className="mt-1.5 text-sm text-muted">
                    Balasanmu tampil di halaman produk, di bawah ulasannya. Kamu bisa membalas, tapi tidak
                    bisa menghapus — itu yang bikin bintangnya dipercaya orang.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Rata-rata" value={stats.total > 0 ? stats.average.toFixed(1) : '—'} icon={<Star />} tone="brand" />
                <StatCard label="Total ulasan" value={formatNumber(stats.total)} />
                <StatCard label="Belum dibalas" value={formatNumber(stats.unanswered)} hint="paling perlu perhatian" />
                <StatCard label="3 bintang ke bawah" value={formatNumber(stats.low)} />
            </div>

            <div className="mt-5 flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none]">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        onClick={() => router.get('/dashboard/ulasan', tab.key === 'semua' ? {} : { saring: tab.key }, {
                            preserveScroll: true,
                            preserveState: false,
                        })}
                        className={cn(
                            '-mb-px shrink-0 border-b-2 px-3.5 py-2.5 text-[0.8125rem] transition-colors',
                            filter === tab.key
                                ? 'border-[var(--primary)] font-semibold text-[var(--primary)]'
                                : 'border-transparent text-muted hover:text-fg',
                        )}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {reviews.data.length === 0 ? (
                <Card className="mt-4">
                    <EmptyState
                        icon={<MessageSquare className="size-6" />}
                        title={filter === 'semua' ? 'Belum ada ulasan' : 'Tidak ada di saringan ini'}
                        description={
                            filter === 'semua'
                                ? 'Ulasan cuma bisa ditulis pembeli yang sudah menerima pesanannya.'
                                : 'Coba lihat tab lain.'
                        }
                    />
                </Card>
            ) : (
                <ul className="mt-4 space-y-3">
                    {reviews.data.map((review) => (
                        <li key={review.id}>
                            <ReviewCard review={review} />
                        </li>
                    ))}
                </ul>
            )}

            <Pagination meta={reviews} />
        </DashboardLayout>
    );
}

function ReviewCard({ review }: { review: ReviewRow }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ body: '' });

    const send = (event: React.FormEvent) => {
        event.preventDefault();
        post(`/dashboard/ulasan/${review.id}/balas`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Card className="p-4 sm:p-5">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                {review.avatar_url ? (
                    <img src={review.avatar_url} alt="" className="size-8 rounded-full object-cover" />
                ) : (
                    <span className="grid size-8 place-items-center rounded-full bg-[var(--nav)] text-[0.6875rem] font-semibold text-white">
                        {initials(review.name)}
                    </span>
                )}

                <div className="min-w-0">
                    <p className="text-[0.8125rem] font-medium">{review.name}</p>
                    <p className="text-xs text-muted">
                        {review.created_at}
                        {review.variant_label && <> · {review.variant_label}</>}
                    </p>
                </div>

                <span className="ml-auto inline-flex items-center gap-0.5" aria-label={`${review.rating} dari 5`}>
                    {[1, 2, 3, 4, 5].map((star) => (
                        <Star
                            key={star}
                            className={cn('size-3.5', star <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-black/15')}
                            aria-hidden="true"
                        />
                    ))}
                </span>
            </div>

            {review.product.name && (
                <a
                    href={review.product.url ?? '#'}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-3 flex items-center gap-2.5 rounded-[var(--radius-field)] bg-surface-2 p-2 transition-colors hover:bg-[var(--border)]"
                >
                    {review.product.thumbnail_url && (
                        <img src={review.product.thumbnail_url} alt="" className="size-8 rounded object-cover" />
                    )}
                    <span className="line-clamp-1 text-xs font-medium">{review.product.name}</span>
                </a>
            )}

            {review.body && <p className="mt-3 whitespace-pre-wrap break-words text-[0.8125rem] leading-6">{review.body}</p>}

            {review.media.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {review.media.map((item, index) =>
                        item.kind === 'video' ? (
                            <video key={index} src={item.url} controls preload="none" className="size-16 rounded bg-black object-cover" />
                        ) : (
                            <img key={index} src={item.url} alt="" loading="lazy" className="size-16 rounded object-cover" />
                        ),
                    )}
                </div>
            )}

            {review.seller_reply ? (
                <div className="mt-3 rounded-[var(--radius-field)] border-l-2 border-[var(--primary)] bg-surface-2 p-3">
                    <p className="text-[0.6875rem] font-semibold text-[var(--primary)]">Balasanmu</p>
                    <p className="mt-1 whitespace-pre-wrap text-[0.8125rem] leading-6">{review.seller_reply}</p>
                </div>
            ) : open ? (
                <form onSubmit={send} className="mt-3">
                    <textarea
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        rows={3}
                        maxLength={2000}
                        autoFocus
                        placeholder={
                            review.rating <= 3
                                ? 'Akui masalahnya, sebutkan apa yang kamu lakukan. Pembeli lain membaca ini.'
                                : 'Terima kasih singkat sudah cukup.'
                        }
                        className="w-full resize-none rounded-[var(--radius-field)] border border-line bg-surface px-3 py-2 text-[0.8125rem] outline-none"
                    />
                    {errors.body && <p className="mt-1 text-xs text-[var(--danger)]">{errors.body}</p>}

                    <div className="mt-2 flex gap-2">
                        <Button type="submit" size="sm" loading={processing} disabled={!data.body.trim()}>
                            Kirim balasan
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                    </div>
                </form>
            ) : (
                <Button size="sm" variant="outline" className="mt-3" onClick={() => setOpen(true)}>
                    <MessageSquare className="size-4" /> Balas
                </Button>
            )}
        </Card>
    );
}
