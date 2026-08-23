import { router } from '@inertiajs/react';
import {
    AlertTriangle, CheckCircle2, FileText, Link2, Pencil, RefreshCw, Trash2, Upload, X,
} from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';
import { ConfirmButton } from '@/components/shared';
import { Alert, Button, Field, Input, Switch } from '@/components/ui';
import { cn } from '@/lib/utils';

export interface ProductFileItem {
    id: number;
    name: string;
    size: number;
    version: string;
    download_limit: number | null;
    access_days: number | null;
    watermark_pdf: boolean;
    mime_type: string | null;
    external_url: string | null;
    purchase_count: number;
}

export interface UploadLimits {
    mimes: string[];
    max_kb: number;
}

function formatBytes(bytes: number): string {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** power;

    return `${value >= 10 || power === 0 ? Math.round(value) : value.toFixed(1)} ${units[power]}`;
}

/**
 * Manages the files a buyer receives after paying.
 *
 * Uploads go through Inertia so the refreshed file list comes back with the
 * page props — there is no separate client-side copy of the list to drift.
 */
export function ProductFiles({
    productId,
    files,
    limits,
    isDeliverable,
    error,
}: {
    productId: number;
    files: ProductFileItem[];
    limits: UploadLimits;
    isDeliverable: boolean;
    error?: string;
}) {
    const [progress, setProgress] = useState<number | null>(null);
    const [dragging, setDragging] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [linkMode, setLinkMode] = useState(false);
    const [externalUrl, setExternalUrl] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const replaceRef = useRef<HTMLInputElement>(null);
    const [replacingId, setReplacingId] = useState<number | null>(null);

    const maxMb = Math.round(limits.max_kb / 1024);
    const accept = limits.mimes.map((m) => `.${m}`).join(',');

    const upload = (file: File) => {
        router.post(
            `/dashboard/produk/${productId}/files`,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onProgress: (event) => setProgress(event?.percentage ?? 0),
                onFinish: () => setProgress(null),
            },
        );
    };

    const replace = (fileId: number, file: File) => {
        router.post(
            `/dashboard/produk/${productId}/files/${fileId}/replace`,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onProgress: (event) => setProgress(event?.percentage ?? 0),
                onFinish: () => {
                    setProgress(null);
                    setReplacingId(null);
                },
            },
        );
    };

    const addLink = () => {
        router.post(
            `/dashboard/produk/${productId}/files`,
            { external_url: externalUrl },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setExternalUrl('');
                    setLinkMode(false);
                },
            },
        );
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        const file = event.dataTransfer.files?.[0];
        if (file) upload(file);
    };

    return (
        <div className="space-y-4">
            {!isDeliverable && (
                <Alert tone="warning" title="Produk ini belum bisa dijual">
                    <span className="text-sm">
                        Produk digital butuh minimal satu file. Selama masih kosong, produk disembunyikan dari toko dan
                        checkout ditolak — supaya tidak ada pembeli yang membayar lalu tidak menerima apa pun.
                    </span>
                </Alert>
            )}

            {error && <Alert tone="danger" title="Tidak bisa dihapus"><span className="text-sm">{error}</span></Alert>}

            {/* Upload zone */}
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'rounded-[var(--radius-field)] border-2 border-dashed p-6 text-center transition',
                    dragging ? 'border-[var(--primary)] bg-surface-2' : 'border-line',
                )}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    className="hidden"
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (file) upload(file);
                        event.target.value = '';
                    }}
                />

                {progress !== null ? (
                    <div className="space-y-2">
                        <p className="text-sm font-semibold">Mengunggah… {progress}%</p>
                        <div className="h-2 overflow-hidden rounded-full bg-surface-2">
                            <div
                                className="h-full rounded-full bg-[var(--primary)] transition-[width]"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="text-xs text-muted">File besar butuh waktu. Jangan tutup halaman ini.</p>
                    </div>
                ) : (
                    <>
                        <Upload className="mx-auto size-7 text-muted" />
                        <p className="mt-2 text-sm font-semibold">Tarik file ke sini, atau</p>
                        <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
                            <Button type="button" variant="outline" onClick={() => inputRef.current?.click()}>
                                Pilih file
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setLinkMode((v) => !v)}>
                                <Link2 className="size-4" />
                                Pakai tautan eksternal
                            </Button>
                        </div>
                        <p className="mt-3 text-xs text-muted">
                            {limits.mimes.join(', ')} · maksimal {maxMb} MB
                        </p>
                    </>
                )}
            </div>

            {linkMode && (
                <div className="rounded-[var(--radius-field)] border border-line p-4">
                    <Field
                        label="Tautan file"
                        hint="Untuk file yang sudah kamu hosting sendiri. Pembeli diarahkan ke tautan ini."
                        htmlFor="external_url"
                    >
                        <div className="flex gap-2">
                            <Input
                                id="external_url"
                                value={externalUrl}
                                onChange={(event) => setExternalUrl(event.target.value)}
                                placeholder="https://drive.google.com/..."
                            />
                            <Button type="button" onClick={addLink} disabled={!externalUrl}>
                                Tambah
                            </Button>
                        </div>
                    </Field>
                </div>
            )}

            {/* File list */}
            {files.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted">Belum ada file.</p>
            ) : (
                <ul className="space-y-2">
                    {files.map((file) => (
                        <li key={file.id} className="rounded-[var(--radius-field)] border border-line">
                            <div className="flex items-start gap-3 p-3">
                                <div className="grid size-10 shrink-0 place-items-center rounded-[var(--radius-field)] bg-surface-2">
                                    {file.external_url ? (
                                        <Link2 className="size-5 text-muted" />
                                    ) : (
                                        <FileText className="size-5 text-muted" />
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">{file.name}</p>
                                    <p className="mt-0.5 text-xs text-muted">
                                        v{file.version}
                                        {' · '}
                                        {file.external_url ? 'Tautan eksternal' : formatBytes(file.size)}
                                        {file.download_limit !== null && ` · maks ${file.download_limit}× unduh`}
                                        {file.access_days !== null && ` · berlaku ${file.access_days} hari`}
                                    </p>
                                    {file.purchase_count > 0 && (
                                        <p className="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                            <CheckCircle2 className="size-3.5" />
                                            Sudah dikirim ke {file.purchase_count} pembeli
                                        </p>
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setEditingId(editingId === file.id ? null : file.id)}
                                        aria-label="Ubah detail file"
                                    >
                                        {editingId === file.id ? <X className="size-4" /> : <Pencil className="size-4" />}
                                    </Button>

                                    {!file.external_url && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => {
                                                setReplacingId(file.id);
                                                replaceRef.current?.click();
                                            }}
                                            aria-label="Ganti file"
                                        >
                                            <RefreshCw className="size-4" />
                                        </Button>
                                    )}

                                    <ConfirmButton
                                        title="Hapus file ini?"
                                        message={
                                            file.purchase_count > 0
                                                ? 'File ini sudah dibeli. Penghapusan akan ditolak — gunakan "Ganti file" untuk versi baru.'
                                                : 'File akan dihapus permanen dari penyimpanan.'
                                        }
                                        confirmLabel="Ya, hapus"
                                        onConfirm={() =>
                                            router.delete(`/dashboard/produk/${productId}/files/${file.id}`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <Button type="button" variant="ghost" aria-label="Hapus file">
                                            <Trash2 className="size-4 text-[var(--danger)]" />
                                        </Button>
                                    </ConfirmButton>
                                </div>
                            </div>

                            {editingId === file.id && (
                                <FileDetails
                                    productId={productId}
                                    file={file}
                                    onDone={() => setEditingId(null)}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {/* Single hidden input reused by every "replace" button. */}
            <input
                ref={replaceRef}
                type="file"
                accept={accept}
                className="hidden"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file && replacingId) replace(replacingId, file);
                    event.target.value = '';
                }}
            />

            <p className="flex items-start gap-2 text-xs text-muted">
                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                File disimpan di penyimpanan privat. Pembeli hanya menerima tautan bertanda tangan yang berlaku singkat,
                jadi alamat aslinya tidak pernah terekspos.
            </p>
        </div>
    );
}

/** Inline metadata editor for one file. */
function FileDetails({
    productId,
    file,
    onDone,
}: {
    productId: number;
    file: ProductFileItem;
    onDone: () => void;
}) {
    const [name, setName] = useState(file.name);
    const [version, setVersion] = useState(file.version);
    const [downloadLimit, setDownloadLimit] = useState(file.download_limit?.toString() ?? '');
    const [accessDays, setAccessDays] = useState(file.access_days?.toString() ?? '');
    const [watermark, setWatermark] = useState(file.watermark_pdf);
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            `/dashboard/produk/${productId}/files/${file.id}`,
            {
                name,
                version,
                download_limit: downloadLimit === '' ? null : Number(downloadLimit),
                access_days: accessDays === '' ? null : Number(accessDays),
                watermark_pdf: watermark,
            },
            {
                preserveScroll: true,
                onSuccess: onDone,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-4 border-t border-line bg-surface-2 p-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Nama file yang dilihat pembeli" htmlFor={`name-${file.id}`}>
                    <Input id={`name-${file.id}`} value={name} onChange={(e) => setName(e.target.value)} />
                </Field>
                <Field label="Versi" htmlFor={`version-${file.id}`}>
                    <Input id={`version-${file.id}`} value={version} onChange={(e) => setVersion(e.target.value)} />
                </Field>
                <Field
                    label="Batas unduh"
                    hint="Kosongkan untuk tanpa batas."
                    htmlFor={`limit-${file.id}`}
                >
                    <Input
                        id={`limit-${file.id}`}
                        type="number"
                        min={1}
                        value={downloadLimit}
                        onChange={(e) => setDownloadLimit(e.target.value)}
                        placeholder="Tanpa batas"
                    />
                </Field>
                <Field
                    label="Masa akses (hari)"
                    hint="Kosongkan untuk akses selamanya."
                    htmlFor={`days-${file.id}`}
                >
                    <Input
                        id={`days-${file.id}`}
                        type="number"
                        min={1}
                        value={accessDays}
                        onChange={(e) => setAccessDays(e.target.value)}
                        placeholder="Selamanya"
                    />
                </Field>
            </div>

            <Switch
                checked={watermark}
                onChange={setWatermark}
                label="Tandai PDF dengan identitas pembeli"
                description="Belum aktif — watermarking dijadwalkan menyusul."
            />

            <div className="flex gap-2">
                <Button type="button" onClick={save} loading={saving}>
                    Simpan detail
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Batal
                </Button>
            </div>
        </div>
    );
}
