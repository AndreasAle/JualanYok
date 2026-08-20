import { GripVertical, ImagePlus, Link2, Loader2, Trash2, Upload } from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';
import { Button, Input } from '@/components/ui';
import { cn } from '@/lib/utils';

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_KB = 4096;

/** Uploads one file to the creator's media endpoint and returns its URL. */
async function upload(file: File): Promise<{ url: string; path: string }> {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch('/dashboard/media', {
        method: 'POST',
        body,
        headers: {
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        throw new Error(payload?.message ?? 'Upload gagal. Coba lagi ya.');
    }

    return response.json();
}

function validate(file: File): string | null {
    if (!ACCEPTED.includes(file.type)) return 'Formatnya harus JPG, PNG, WEBP, atau GIF.';
    if (file.size > MAX_KB * 1024) return `Ukuran maksimal ${Math.round(MAX_KB / 1024)} MB.`;

    return null;
}

/* ========================================================================== */

/**
 * Single image field for a block.
 *
 * Block content stores a URL string, so the file is uploaded straight away and
 * only the resulting URL is written back. Pasting a URL still works for images
 * already hosted elsewhere.
 */
export function MediaPicker({
    label,
    value,
    onChange,
    hint,
    aspect = 'wide',
}: {
    label: string;
    value: string;
    onChange: (url: string) => void;
    hint?: string;
    aspect?: 'square' | 'wide';
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const [showUrl, setShowUrl] = useState(false);

    const handle = async (file: File | undefined | null) => {
        if (!file) return;

        const problem = validate(file);

        if (problem) {
            setError(problem);
            return;
        }

        setError(null);
        setBusy(true);

        try {
            const { url } = await upload(file);
            onChange(url);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Upload gagal.');
        } finally {
            setBusy(false);
            if (input.current) input.current.value = '';
        }
    };

    return (
        <div>
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">{label}</p>
                <button
                    type="button"
                    onClick={() => setShowUrl((v) => !v)}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-muted hover:text-fg"
                >
                    <Link2 className="size-3.5" />
                    {showUrl ? 'Tutup URL' : 'Pakai URL'}
                </button>
            </div>

            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    handle(e.dataTransfer.files?.[0]);
                }}
                className={cn(
                    'relative overflow-hidden rounded-[var(--radius-field)] border-2 border-dashed transition-colors',
                    aspect === 'square' ? 'aspect-square max-w-44' : 'aspect-video',
                    dragging
                        ? 'border-[var(--primary)] bg-brand-50 dark:bg-brand-900/20'
                        : error
                          ? 'border-[var(--danger)]'
                          : 'border-line bg-surface-2',
                )}
            >
                {busy ? (
                    <span className="flex size-full flex-col items-center justify-center gap-2 text-sm text-muted">
                        <Loader2 className="size-6 animate-spin" />
                        Mengunggah…
                    </span>
                ) : value ? (
                    <>
                        <img src={value} alt="" className="size-full object-cover" />

                        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity hover:opacity-100">
                            <Button type="button" variant="secondary" size="sm" onClick={() => input.current?.click()}>
                                <Upload className="size-4" />
                                Ganti
                            </Button>
                            <Button type="button" variant="danger" size="sm" onClick={() => onChange('')}>
                                <Trash2 className="size-4" />
                                Hapus
                            </Button>
                        </div>
                    </>
                ) : (
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        className="flex size-full flex-col items-center justify-center gap-1.5 p-4 text-center"
                    >
                        <ImagePlus className="size-6 text-muted" />
                        <span className="text-sm font-semibold">Upload gambar</span>
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
                onChange={(e) => handle(e.target.files?.[0])}
            />

            {showUrl && (
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="https://..."
                    className="mt-2"
                    aria-label={`${label} lewat URL`}
                />
            )}

            {error ? (
                <p className="mt-1.5 text-xs font-medium text-[var(--danger)]" role="alert">
                    {error}
                </p>
            ) : (
                hint && <p className="mt-1.5 text-xs text-muted">{hint}</p>
            )}
        </div>
    );
}

/* ========================================================================== */

export interface GalleryImage {
    url: string;
    alt?: string;
}

/**
 * Multi-image field for the gallery block: upload several at once, reorder,
 * and remove.
 */
export function GalleryPicker({
    images,
    onChange,
}: {
    images: GalleryImage[];
    onChange: (images: GalleryImage[]) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    const addFiles = async (files: FileList | null) => {
        if (!files || files.length === 0) return;

        const chosen = Array.from(files);
        const problem = chosen.map(validate).find(Boolean);

        if (problem) {
            setError(problem);
            return;
        }

        setError(null);
        setBusy(true);

        try {
            // Uploaded in parallel; a single failure surfaces without losing
            // the files that did succeed.
            const results = await Promise.allSettled(chosen.map(upload));

            const added = results
                .filter((r): r is PromiseFulfilledResult<{ url: string; path: string }> => r.status === 'fulfilled')
                .map((r) => ({ url: r.value.url, alt: '' }));

            if (added.length > 0) onChange([...images, ...added]);

            const failed = results.filter((r) => r.status === 'rejected').length;
            if (failed > 0) setError(`${failed} gambar gagal diunggah.`);
        } finally {
            setBusy(false);
            if (input.current) input.current.value = '';
        }
    };

    const move = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= images.length) return;

        const next = [...images];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <div>
            <p className="mb-1.5 text-sm font-semibold">Gambar galeri</p>

            {images.length > 0 && (
                <ul className="mb-3 grid grid-cols-3 gap-2">
                    {images.map((image, index) => (
                        <li key={index} className="group relative overflow-hidden rounded-[var(--radius-field)]">
                            <img src={image.url} alt={image.alt ?? ''} className="aspect-square w-full object-cover" />

                            <div className="absolute inset-0 flex items-center justify-center gap-1 bg-black/55 opacity-0 transition-opacity group-hover:opacity-100">
                                <button
                                    type="button"
                                    onClick={() => move(index, -1)}
                                    disabled={index === 0}
                                    aria-label="Geser kiri"
                                    className="grid size-7 place-items-center rounded-md bg-white/90 text-slate-900 disabled:opacity-40"
                                >
                                    <GripVertical className="size-3.5 rotate-90" />
                                </button>

                                <button
                                    type="button"
                                    onClick={() => onChange(images.filter((_, i) => i !== index))}
                                    aria-label="Hapus gambar"
                                    className="grid size-7 place-items-center rounded-md bg-[var(--danger)] text-white"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    addFiles(e.dataTransfer.files);
                }}
                className={cn(
                    'rounded-[var(--radius-field)] border-2 border-dashed p-5 text-center transition-colors',
                    dragging
                        ? 'border-[var(--primary)] bg-brand-50 dark:bg-brand-900/20'
                        : error
                          ? 'border-[var(--danger)]'
                          : 'border-line bg-surface-2',
                )}
            >
                {busy ? (
                    <span className="flex items-center justify-center gap-2 text-sm text-muted">
                        <Loader2 className="size-5 animate-spin" />
                        Mengunggah…
                    </span>
                ) : (
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        className="flex w-full flex-col items-center gap-1.5"
                    >
                        <ImagePlus className="size-6 text-muted" />
                        <span className="text-sm font-semibold">Tambah gambar</span>
                        <span className="text-xs text-muted">Bisa pilih beberapa sekaligus</span>
                    </button>
                )}
            </div>

            <input
                ref={input}
                type="file"
                accept={ACCEPTED.join(',')}
                multiple
                className="sr-only"
                aria-label="Gambar galeri"
                onChange={(e) => addFiles(e.target.files)}
            />

            {error && (
                <p className="mt-1.5 text-xs font-medium text-[var(--danger)]" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
