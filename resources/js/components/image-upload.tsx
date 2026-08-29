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

export interface ExistingProductImage {
    id: number;
    url: string;
    alt?: string | null;
}

/**
 * Multi-image picker for a product gallery. Existing server images and newly
 * selected files live in one ordered-looking grid, while removals are sent as
 * explicit ids so an update cannot accidentally delete another product's
 * media.
 */
export function ProductGalleryUpload({
    existing = [],
    files,
    removedIds,
    error,
    maxImages = 8,
    onFilesChange,
    onRemovedIdsChange,
}: {
    existing?: ExistingProductImage[];
    files: File[];
    removedIds: number[];
    error?: string;
    maxImages?: number;
    onFilesChange: (files: File[]) => void;
    onRemovedIdsChange: (ids: number[]) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [localError, setLocalError] = useState<string | null>(null);
    const [previews, setPreviews] = useState<string[]>([]);
    const visibleExisting = existing.filter((image) => !removedIds.includes(image.id));
    const remaining = Math.max(0, maxImages - visibleExisting.length - files.length);

    useEffect(() => {
        const urls = files.map((file) => URL.createObjectURL(file));
        setPreviews(urls);

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [files]);

    const addFiles = (list: FileList | null) => {
        if (!list?.length) return;

        const chosen = Array.from(list);
        const invalid = chosen.find((file) => !ACCEPTED.includes(file.type) || file.size > 4096 * 1024);

        if (invalid) {
            setLocalError('Gunakan JPG, PNG, WEBP, atau GIF dengan ukuran maksimal 4 MB per gambar.');
            return;
        }

        if (chosen.length > remaining) {
            setLocalError(`Galeri maksimal ${maxImages} gambar. Kamu masih bisa menambah ${remaining}.`);
            return;
        }

        setLocalError(null);
        onFilesChange([...files, ...chosen]);
        if (input.current) input.current.value = '';
    };

    const message = error ?? localError;

    return (
        <div className="mt-6 border-t border-line pt-5">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold">Galeri produk</p>
                    <p className="mt-1 text-xs leading-5 text-muted">Tambahkan beberapa sudut foto. Thumbnail tetap menjadi gambar utama di katalog.</p>
                </div>
                <span className="shrink-0 rounded-full bg-surface-2 px-2.5 py-1 text-[11px] font-bold text-muted">
                    {visibleExisting.length + files.length}/{maxImages}
                </span>
            </div>

            {(visibleExisting.length > 0 || files.length > 0) && (
                <div className="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {visibleExisting.map((image) => (
                        <div key={`saved-${image.id}`} className="group relative overflow-hidden rounded-xl border border-line bg-surface-2">
                            <img src={image.url} alt={image.alt ?? ''} className="aspect-square size-full object-cover" />
                            <button
                                type="button"
                                onClick={() => onRemovedIdsChange([...removedIds, image.id])}
                                className="absolute right-1.5 top-1.5 grid size-7 place-items-center rounded-lg bg-black/70 text-white opacity-90 transition hover:bg-[var(--danger)]"
                                aria-label="Hapus gambar tersimpan"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </div>
                    ))}

                    {files.map((file, index) => (
                        <div key={`${file.name}-${file.lastModified}-${index}`} className="group relative overflow-hidden rounded-xl border border-line bg-surface-2">
                            {previews[index] && <img src={previews[index]} alt="" className="aspect-square size-full object-cover" />}
                            <button
                                type="button"
                                onClick={() => onFilesChange(files.filter((_, fileIndex) => fileIndex !== index))}
                                className="absolute right-1.5 top-1.5 grid size-7 place-items-center rounded-lg bg-black/70 text-white transition hover:bg-[var(--danger)]"
                                aria-label={`Hapus ${file.name}`}
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            {remaining > 0 && (
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    className="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line bg-surface-2 px-4 py-4 text-sm font-semibold transition hover:border-[var(--primary)] hover:text-[var(--primary)]"
                >
                    <ImagePlus className="size-5" />
                    Tambah foto produk
                </button>
            )}

            <input
                ref={input}
                type="file"
                accept={ACCEPTED.join(',')}
                multiple
                className="sr-only"
                aria-label="Tambah foto galeri produk"
                onChange={(event) => addFiles(event.target.files)}
            />

            {message && <p className="mt-2 text-xs font-medium text-[var(--danger)]" role="alert">{message}</p>}
        </div>
    );
}
