import { FileText, ImagePlus, MessageCircle, Paperclip, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn, formatIDR } from '@/lib/utils';
import type { buildStorefrontTheme } from '@/lib/storefront-theme';

export interface ChatAttachment {
    id: number;
    kind: 'image' | 'video' | 'file' | string;
    name: string;
    size: number;
    url: string;
}

export interface ChatMessage {
    id: number;
    sender: 'buyer' | 'seller';
    is_auto: boolean;
    body: string;
    context: { kind: string; label: string; url: string; image: string | null; price: number; buyable?: boolean } | null;
    attachments: ChatAttachment[];
    read: boolean;
    at: string;
    at_human: string;
}

interface Presence {
    online: boolean;
    label: string;
}

type Theme = ReturnType<typeof buildStorefrontTheme>;

/** Only while the panel is open; a closed panel has nothing to refresh. */
const POLL_MS = 8000;

const ACCEPT = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'video/mp4', 'video/quicktime', 'video/webm',
    'application/pdf', 'application/zip', 'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
].join(',');

const MAX_FILES = 5;

/** Rounded the way a person reads a file size, not the way a disk reports it. */
function readableSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The buyer's chat with the shop.
 *
 * A panel rather than a page: a question is usually asked while looking at the
 * thing it is about, and navigating away to ask it loses both the product and
 * the buyer's place. The product being viewed is pinned above the box before a
 * word is typed, so the seller never has to ask what "this" is.
 *
 * Message bodies are rendered as text nodes. Nothing a stranger types is ever
 * interpolated into markup.
 */
export function ChatSheet({
    storeUsername,
    storeName,
    storeAvatar,
    productId,
    productName,
    productImage,
    productPrice,
    whatsapp,
    theme,
    onClose,
}: {
    storeUsername: string;
    storeName: string;
    storeAvatar?: string | null;
    productId?: number;
    productName?: string;
    productImage?: string | null;
    productPrice?: number;
    whatsapp?: string | null;
    theme: Theme;
    onClose: () => void;
}) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [seller, setSeller] = useState<Presence>({ online: false, label: '' });
    const [draft, setDraft] = useState('');
    const [name, setName] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [pinned, setPinned] = useState(Boolean(productId));
    const [sending, setSending] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const endRef = useRef<HTMLDivElement | null>(null);
    const picker = useRef<HTMLInputElement | null>(null);

    const load = async () => {
        try {
            const response = await fetch(`/${storeUsername}/chat`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            setMessages(data.messages ?? []);
            if (data.seller) setSeller(data.seller);
        } finally {
            setLoaded(true);
        }
    };

    useEffect(() => {
        load();
        const timer = window.setInterval(load, POLL_MS);

        return () => window.clearInterval(timer);
    }, [storeUsername]);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [messages.length]);

    const addFiles = (list: FileList | null) => {
        if (!list?.length) return;

        setError(null);
        setFiles((current) => [...current, ...Array.from(list)].slice(0, MAX_FILES));

        if (picker.current) picker.current.value = '';
    };

    const send = async (event: React.FormEvent) => {
        event.preventDefault();

        const body = draft.trim();
        if ((!body && files.length === 0) || sending) return;

        setSending(true);
        setError(null);

        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            // Multipart, because a message can carry files. Inertia is not used
            // here at all — this endpoint answers JSON, not a page.
            const form = new FormData();
            form.append('body', body);
            if (pinned && productId) form.append('product_id', String(productId));
            if (messages.length === 0 && name.trim()) form.append('name', name.trim());
            files.forEach((file) => form.append('files[]', file));

            const response = await fetch(`/${storeUsername}/chat`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: form,
            });

            if (!response.ok) {
                const reason = await response.json().catch(() => null);

                setError(
                    reason?.message
                        ?? (Object.values(reason?.errors ?? {})[0] as string[] | undefined)?.[0]
                        ?? (response.status === 429
                            ? 'Kebanyakan pesan sekaligus. Tunggu sebentar ya.'
                            : response.status === 419
                              ? 'Sesi kamu kedaluwarsa. Muat ulang halaman lalu kirim lagi.'
                              : 'Pesan belum terkirim. Coba lagi sebentar lagi.'),
                );

                return;
            }

            const data = await response.json();
            setMessages(data.messages ?? []);
            if (data.seller) setSeller(data.seller);
            setDraft('');
            setFiles([]);
            setPinned(false);
        } catch {
            setError('Koneksi terputus. Pesan belum terkirim.');
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[80] flex items-end justify-end sm:p-4" role="dialog" aria-label={`Chat dengan ${storeName}`}>
            <button type="button" className="absolute inset-0 bg-black/40" onClick={onClose} aria-label="Tutup chat" tabIndex={-1} />

            <div className="relative flex h-[85vh] w-full flex-col overflow-hidden rounded-t-2xl bg-[var(--sf-card)] shadow-2xl sm:h-[34rem] sm:max-w-sm sm:rounded-2xl">
                <header className={cn('flex items-center gap-2.5 border-b px-4 py-3', theme.line)}>
                    <span className="relative size-9 shrink-0">
                        {storeAvatar ? (
                            <img src={storeAvatar} alt="" className="size-full rounded-full object-cover" />
                        ) : (
                            <span
                                className="grid size-full place-items-center rounded-full text-sm font-bold"
                                style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                            >
                                {storeName[0]?.toUpperCase()}
                            </span>
                        )}
                        {/* Only lit while a seller actually has the inbox open. */}
                        {seller.online && (
                            <span className="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-[var(--sf-card)] bg-emerald-500" />
                        )}
                    </span>

                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold">{storeName}</p>
                        <p className={cn('truncate text-[0.6875rem]', seller.online ? 'font-medium text-emerald-600' : theme.muted)}>
                            {seller.label || 'Biasanya dibalas dalam beberapa jam'}
                        </p>
                    </div>

                    <button type="button" onClick={onClose} className={cn('grid size-8 place-items-center rounded-lg', theme.muted)} aria-label="Tutup">
                        <X className="size-4" />
                    </button>
                </header>

                <div className="flex-1 space-y-2.5 overflow-y-auto px-3 py-3">
                    {!loaded ? (
                        <p className={cn('py-8 text-center text-xs', theme.muted)}>Memuat percakapan…</p>
                    ) : messages.length === 0 ? (
                        <div className="py-6 text-center">
                            <span className="mx-auto grid size-11 place-items-center rounded-full bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] text-[var(--sf-primary)]">
                                <MessageCircle className="size-5" />
                            </span>
                            <p className="mt-3 text-sm font-medium">Tanya apa aja ke {storeName}</p>
                            <p className={cn('mx-auto mt-1 max-w-[16rem] text-xs leading-5', theme.muted)}>
                                Stok, ukuran, pengiriman — pesanmu langsung masuk ke dashboard penjual.
                            </p>
                        </div>
                    ) : (
                        messages.map((message) => <Bubble key={message.id} message={message} theme={theme} />)
                    )}
                    <div ref={endRef} />
                </div>

                {error && <p className="border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-700">{error}</p>}

                <form onSubmit={send} className={cn('border-t p-2.5', theme.line)}>
                    {/* The product, pinned before a word is typed. */}
                    {pinned && productId && (
                        <div className={cn('mb-2 flex items-center gap-2 rounded-lg border p-1.5', theme.line)}>
                            {productImage && <img src={productImage} alt="" className="size-9 shrink-0 rounded object-cover" />}
                            <span className="min-w-0 flex-1">
                                <span className="line-clamp-1 text-[0.6875rem] font-medium">{productName}</span>
                                {productPrice !== undefined && (
                                    <span className="block text-[0.6875rem] text-[var(--sf-primary)]">{formatIDR(productPrice)}</span>
                                )}
                            </span>
                            <button
                                type="button"
                                onClick={() => setPinned(false)}
                                className={cn('grid size-6 shrink-0 place-items-center rounded', theme.muted)}
                                aria-label="Lepas produk dari pesan"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    )}

                    {files.length > 0 && (
                        <div className="mb-2 flex flex-wrap gap-1.5">
                            {files.map((file, index) => (
                                <span key={index} className={cn('relative flex items-center gap-1.5 rounded-lg border py-1 pl-1.5 pr-6 text-[0.6875rem]', theme.line)}>
                                    {file.type.startsWith('image/') ? (
                                        <img src={URL.createObjectURL(file)} alt="" className="size-7 rounded object-cover" />
                                    ) : (
                                        <FileText className="size-4 opacity-60" />
                                    )}
                                    <span className="max-w-24 truncate">{file.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => setFiles(files.filter((_, i) => i !== index))}
                                        className="absolute right-1 top-1/2 -translate-y-1/2"
                                        aria-label={`Hapus ${file.name}`}
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}

                    {loaded && messages.length === 0 && (
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nama kamu (opsional)"
                            maxLength={80}
                            className={cn('mb-2 h-9 w-full rounded-lg border px-3 text-[0.8125rem] outline-none', theme.line)}
                        />
                    )}

                    <div className="flex items-end gap-1.5">
                        <button
                            type="button"
                            onClick={() => picker.current?.click()}
                            disabled={files.length >= MAX_FILES}
                            className={cn('grid size-9 shrink-0 place-items-center rounded-lg border disabled:opacity-40', theme.line)}
                            aria-label="Lampirkan file"
                        >
                            <Paperclip className="size-4" />
                        </button>

                        <input
                            ref={picker}
                            type="file"
                            multiple
                            accept={ACCEPT}
                            onChange={(e) => addFiles(e.target.files)}
                            className="hidden"
                        />

                        <textarea
                            value={draft}
                            onChange={(e) => setDraft(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    send(e);
                                }
                            }}
                            rows={1}
                            maxLength={2000}
                            placeholder="Tulis pesan…"
                            className={cn('max-h-24 min-h-9 flex-1 resize-none rounded-lg border px-3 py-2 text-[0.8125rem] outline-none', theme.line)}
                        />

                        <button
                            type="submit"
                            disabled={sending || (!draft.trim() && files.length === 0)}
                            className="grid size-9 shrink-0 place-items-center rounded-lg disabled:opacity-40"
                            style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                            aria-label="Kirim"
                        >
                            <Send className="size-4" />
                        </button>
                    </div>

                    {whatsapp && (
                        <a
                            href={`https://wa.me/${whatsapp}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn('mt-2 block text-center text-[0.6875rem] underline', theme.muted)}
                        >
                            Atau hubungi lewat WhatsApp
                        </a>
                    )}
                </form>
            </div>
        </div>
    );
}

/** Attachments, rendered by what they are rather than by what they claim. */
export function Attachments({ items, mine }: { items: ChatAttachment[]; mine: boolean }) {
    if (items.length === 0) return null;

    return (
        <div className="mb-1.5 space-y-1.5">
            {items.map((file) =>
                file.kind === 'image' ? (
                    <a key={file.id} href={file.url} target="_blank" rel="noopener noreferrer" className="block">
                        <img src={file.url} alt={file.name} loading="lazy" className="max-h-48 w-full rounded-lg object-cover" />
                    </a>
                ) : file.kind === 'video' ? (
                    <video key={file.id} src={file.url} controls preload="none" className="max-h-48 w-full rounded-lg bg-black" />
                ) : (
                    <a
                        key={file.id}
                        href={file.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={cn(
                            'flex items-center gap-2 rounded-lg p-2 text-[0.75rem]',
                            mine ? 'bg-white/15' : 'bg-black/5',
                        )}
                    >
                        <FileText className="size-4 shrink-0" />
                        <span className="min-w-0 flex-1">
                            <span className="line-clamp-1 font-medium">{file.name}</span>
                            <span className="text-[0.625rem] opacity-70">{readableSize(file.size)}</span>
                        </span>
                    </a>
                ),
            )}
        </div>
    );
}

function Bubble({ message, theme }: { message: ChatMessage; theme: Theme }) {
    const mine = message.sender === 'buyer';

    return (
        <div className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
            <div
                className={cn('max-w-[80%] rounded-2xl px-3 py-2', mine ? 'rounded-br-sm' : 'rounded-bl-sm')}
                style={
                    mine
                        ? { background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }
                        : { background: 'color-mix(in oklab, var(--sf-fg) 7%, transparent)' }
                }
            >
                {message.context && (
                    <a
                        href={message.context.url}
                        className={cn('mb-1.5 flex items-center gap-2 rounded-lg p-1.5 text-[0.6875rem]', mine ? 'bg-white/15' : 'bg-[var(--sf-card)]')}
                    >
                        {message.context.image && <img src={message.context.image} alt="" className="size-8 shrink-0 rounded object-cover" />}
                        <span className="min-w-0 flex-1">
                            <span className="line-clamp-2">{message.context.label}</span>
                            {message.context.price > 0 && (
                                <span className="mt-0.5 block font-semibold">{formatIDR(message.context.price)}</span>
                            )}
                        </span>
                    </a>
                )}

                <Attachments items={message.attachments ?? []} mine={mine} />

                {message.body && <p className="whitespace-pre-wrap break-words text-[0.8125rem] leading-5">{message.body}</p>}

                <p className={cn('mt-0.5 flex items-center justify-end gap-1.5 text-[0.625rem]', mine ? 'opacity-70' : theme.muted)}>
                    {/* Said plainly. A buyer must be able to tell an automatic
                        reply from the person they think they are talking to. */}
                    {message.is_auto && <span className="rounded bg-black/10 px-1">otomatis</span>}
                    {message.at_human}
                </p>
            </div>
        </div>
    );
}
