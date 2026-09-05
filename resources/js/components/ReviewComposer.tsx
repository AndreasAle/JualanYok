import { useForm } from '@inertiajs/react';
import { ImagePlus, Star, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Card } from '@/components/ui';
import { cn } from '@/lib/utils';

const MAX_MEDIA = 5;

interface Reviewable {
    id: number;
    name: string;
    variant_name: string | null;
    thumbnail_url: string | null;
}

const WORDS: Record<number, string> = {
    1: 'Kecewa',
    2: 'Kurang',
    3: 'Lumayan',
    4: 'Bagus',
    5: 'Sangat bagus',
};

/**
 * Writing a review of something you bought.
 *
 * Stars first, and nothing else is asked for. A required essay is how review
 * sections end up empty — the rating is the part that carries information, and
 * the photo is the part other buyers actually trust, so both are easy and the
 * writing is optional.
 */
export function ReviewComposer({ orderNumber, item }: { orderNumber: string; item: Reviewable }) {
    const [hovered, setHovered] = useState(0);
    const [previews, setPreviews] = useState<{ url: string; kind: string }[]>([]);

    const { data, setData, post, processing, errors, reset } = useForm<{
        order_item_id: number;
        rating: number;
        body: string;
        is_anonymous: boolean;
        media: File[];
    }>({
        order_item_id: item.id,
        rating: 0,
        body: '',
        is_anonymous: false,
        media: [],
    });

    const addFiles = (files: FileList | null) => {
        if (!files) return;

        const room = MAX_MEDIA - data.media.length;
        const picked = Array.from(files).slice(0, Math.max(0, room));

        setData('media', [...data.media, ...picked]);
        setPreviews((current) => [
            ...current,
            ...picked.map((file) => ({
                url: URL.createObjectURL(file),
                kind: file.type.startsWith('video/') ? 'video' : 'image',
            })),
        ]);
    };

    const drop = (index: number) => {
        // The object URL is released, or every discarded pick leaks until the
        // tab is closed.
        URL.revokeObjectURL(previews[index].url);
        setPreviews((current) => current.filter((_, i) => i !== index));
        setData('media', data.media.filter((_, i) => i !== index));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(`/member/pembelian/${orderNumber}/ulasan`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                setPreviews([]);
                reset();
            },
        });
    };

    const serverErrors = errors as Record<string, string | undefined>;

    return (
        <Card className="p-4 sm:p-5">
            <div className="flex gap-3">
                {item.thumbnail_url && (
                    <img src={item.thumbnail_url} alt="" className="size-12 shrink-0 rounded object-cover" />
                )}
                <div className="min-w-0">
                    <p className="line-clamp-2 text-[0.8125rem] font-medium">{item.name}</p>
                    {item.variant_name && <p className="mt-0.5 text-xs text-muted">Variasi: {item.variant_name}</p>}
                </div>
            </div>

            <form onSubmit={submit} className="mt-4">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1" onMouseLeave={() => setHovered(0)}>
                        {[1, 2, 3, 4, 5].map((star) => (
                            <button
                                key={star}
                                type="button"
                                onMouseEnter={() => setHovered(star)}
                                onClick={() => setData('rating', star)}
                                aria-label={`${star} bintang`}
                                aria-pressed={data.rating === star}
                            >
                                <Star
                                    className={cn(
                                        'size-7 transition',
                                        star <= (hovered || data.rating)
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-black/15 dark:text-white/20',
                                    )}
                                />
                            </button>
                        ))}
                    </div>

                    {(hovered || data.rating) > 0 && (
                        <span className="text-[0.8125rem] font-medium text-amber-600">
                            {WORDS[hovered || data.rating]}
                        </span>
                    )}
                </div>

                {serverErrors.rating && (
                    <p className="mt-1.5 text-xs font-medium text-[var(--danger)]">{serverErrors.rating}</p>
                )}

                <textarea
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    rows={3}
                    maxLength={2000}
                    placeholder="Gimana barangnya? Bahan, ukuran, pengiriman — apa aja yang bantu pembeli lain."
                    className="mt-3 w-full resize-none rounded-[var(--radius-field)] border border-line bg-surface px-3 py-2 text-[0.8125rem] outline-none"
                />

                <div className="mt-2 flex flex-wrap items-center gap-2">
                    {previews.map((preview, index) => (
                        <span key={index} className="relative size-16 overflow-hidden rounded border border-line">
                            {preview.kind === 'video' ? (
                                <video src={preview.url} className="size-full object-cover" muted />
                            ) : (
                                <img src={preview.url} alt="" className="size-full object-cover" />
                            )}
                            <button
                                type="button"
                                onClick={() => drop(index)}
                                className="absolute right-0.5 top-0.5 grid size-5 place-items-center rounded-full bg-black/60 text-white"
                                aria-label="Hapus media"
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}

                    {data.media.length < MAX_MEDIA && (
                        <label className="grid size-16 cursor-pointer place-items-center rounded border border-dashed border-line text-muted transition hover:border-[var(--primary)] hover:text-[var(--primary)]">
                            <ImagePlus className="size-5" />
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                                onChange={(e) => addFiles(e.target.files)}
                                className="hidden"
                            />
                        </label>
                    )}

                    <span className="text-xs text-muted">
                        Foto atau video, maks {MAX_MEDIA} file · 20 MB per file
                    </span>
                </div>

                {serverErrors['media.0'] && (
                    <p className="mt-1.5 text-xs font-medium text-[var(--danger)]">{serverErrors['media.0']}</p>
                )}

                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-[0.8125rem] text-muted">
                        <input
                            type="checkbox"
                            checked={data.is_anonymous}
                            onChange={(e) => setData('is_anonymous', e.target.checked)}
                            className="size-4 accent-[var(--primary)]"
                        />
                        Sembunyikan namaku
                    </label>

                    <Button type="submit" size="sm" loading={processing} disabled={data.rating === 0}>
                        Kirim ulasan
                    </Button>
                </div>
            </form>
        </Card>
    );
}
