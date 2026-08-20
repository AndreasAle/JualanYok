import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState, type DragEvent } from 'react';
import { Button } from '@/components/ui';
import { cn } from '@/lib/utils';

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/**
 * Picks one image, with a live preview.
 *
 * The preview uses an object URL so the creator sees their choice immediately
 * instead of waiting for a round trip; the URL is revoked on cleanup so long
 * editing sessions do not leak memory.
 */
export function ImageUpload({
    label,
    hint,
    currentUrl,
    aspect = 'square',
    maxKb = 4096,
    error,
    onSelect,
    onRemove,
}: {
    label: string;
    hint?: string;
    /** Image already saved on the server, if any. */
    currentUrl?: string | null;
    aspect?: 'square' | 'wide' | 'cover';
    maxKb?: number;
    error?: string;
    onSelect: (file: File | null) => void;
    /** Omit to hide the remove action. */
    onRemove?: () => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const [removed, setRemoved] = useState(false);

    useEffect(() => {
        return () => {
            if (preview) URL.revokeObjectURL(preview);
        };
    }, [preview]);

    const accept = (file: File | undefined | null) => {
        if (!file) return;

        if (!ACCEPTED.includes(file.type)) {
            setLocalError('Formatnya harus JPG, PNG, WEBP, atau GIF.');
            return;
        }

        if (file.size > maxKb * 1024) {
            setLocalError(`Ukuran maksimal ${Math.round(maxKb / 1024)} MB.`);
            return;
        }

        setLocalError(null);
        setRemoved(false);

        if (preview) URL.revokeObjectURL(preview);
        setPreview(URL.createObjectURL(file));

        onSelect(file);
    };

    const drop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setDragging(false);
        accept(e.dataTransfer.files?.[0]);
    };

    const clear = () => {
        if (preview) URL.revokeObjectURL(preview);

        setPreview(null);
        setLocalError(null);
        setRemoved(true);

        if (input.current) input.current.value = '';

        onSelect(null);
        onRemove?.();
    };

    const shown = preview ?? (removed ? null : currentUrl);

    const ratio =
        aspect === 'wide' ? 'aspect-[16/5]' : aspect === 'cover' ? 'aspect-[3/1]' : 'aspect-square max-w-40';

    const message = error ?? localError;

    return (
        <div>
            <p className="mb-1.5 text-sm font-semibold">{label}</p>

            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
                className={cn(
                    'relative overflow-hidden rounded-[var(--radius-card)] border-2 border-dashed transition-colors',
                    ratio,
                    dragging
                        ? 'border-[var(--primary)] bg-brand-50 dark:bg-brand-900/20'
                        : message
                          ? 'border-[var(--danger)]'
                          : 'border-line bg-surface-2',
                )}
            >
                {shown ? (
                    <>
                        <img src={shown} alt="" className="size-full object-cover" />

                        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity hover:opacity-100">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => input.current?.click()}
                            >
                                <Upload className="size-4" />
                                Ganti
                            </Button>

                            {onRemove && (
                                <Button type="button" variant="danger" size="sm" onClick={clear}>
                                    <Trash2 className="size-4" />
                                    Hapus
                                </Button>
                            )}
                        </div>
                    </>
                ) : (
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        className="flex size-full flex-col items-center justify-center gap-2 p-4 text-center"
                    >
                        <ImagePlus className="size-7 text-muted" />
                        <span className="text-sm font-semibold">Pilih gambar</span>
                        <span className="text-xs text-muted">atau tarik file ke sini</span>
                    </button>
                )}
            </div>

            <input
                ref={input}
                type="file"
                accept={ACCEPTED.join(',')}
                className="sr-only"
                aria-label={label}
                onChange={(e) => accept(e.target.files?.[0])}
            />

            {message ? (
                <p className="mt-1.5 text-xs font-medium text-[var(--danger)]" role="alert">
                    {message}
                </p>
            ) : (
                <p className="mt-1.5 text-xs text-muted">
                    {hint ?? `JPG, PNG, WEBP, atau GIF. Maksimal ${Math.round(maxKb / 1024)} MB.`}
                </p>
            )}
        </div>
    );
}
