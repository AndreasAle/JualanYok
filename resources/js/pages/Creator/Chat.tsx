import { router, useForm } from '@inertiajs/react';
import { Bot, FileText, MessageCircle, Package, Paperclip, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Button, Card, Switch, Textarea } from '@/components/ui';
import { cn, formatIDR, initials } from '@/lib/utils';

interface Attachment {
    id: number;
    kind: string;
    name: string;
    size: number;
    url: string;
}

interface Message {
    id: number;
    sender: 'buyer' | 'seller';
    is_auto: boolean;
    body: string;
    context: { kind: string; label: string; url: string; image: string | null; price: number } | null;
    attachments: Attachment[];
    read: boolean;
    at: string;
    at_human: string;
}

interface Row {
    id: number;
    name: string;
    avatar_url: string | null;
    is_guest: boolean;
    preview: string | null;
    from_buyer: boolean;
    unread: number;
    at_human: string | null;
}

interface ProductOption {
    id: number;
    name: string;
    price: number;
    thumbnail_url: string | null;
}

interface Presence {
    online: boolean;
    label: string;
}

/** Slow enough to be free, fast enough that a live conversation feels live. */
const POLL_MS = 7000;

const ACCEPT = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    'video/mp4', 'video/quicktime', 'video/webm',
    'application/pdf', 'application/zip', 'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
].join(',');

function readableSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * The seller's inbox.
 *
 * Two panes rather than a list that navigates away: answering ten questions is
 * one sitting, and a full page load between each one turns it into ten.
 *
 * Message bodies are rendered as text nodes — the whole content of this screen
 * was typed by strangers.
 */
export default function CreatorChat({
    conversations,
    active,
    products = [],
    autoReply,
}: {
    conversations: Row[];
    active: { id: number; name: string; is_guest: boolean; messages: Message[]; buyer: Presence } | null;
    products?: ProductOption[];
    autoReply: { enabled: boolean; message: string | null };
}) {
    const [messages, setMessages] = useState<Message[]>(active?.messages ?? []);
    const [buyer, setBuyer] = useState<Presence>(active?.buyer ?? { online: false, label: '' });
    const [draft, setDraft] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [product, setProduct] = useState<ProductOption | null>(null);
    const [picking, setPicking] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const endRef = useRef<HTMLDivElement | null>(null);
    const picker = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        setMessages(active?.messages ?? []);
        setBuyer(active?.buyer ?? { online: false, label: '' });
        setDraft('');
        setFiles([]);
        setProduct(null);
        setError(null);
    }, [active?.id]);

    useEffect(() => {
        if (!active) return;

        const poll = async () => {
            const response = await fetch(`/dashboard/chat/${active.id}/pesan`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            setMessages(data.messages ?? []);
            if (data.buyer) setBuyer(data.buyer);
        };

        const timer = window.setInterval(poll, POLL_MS);

        return () => window.clearInterval(timer);
    }, [active?.id]);

    useEffect(() => {
        endRef.current?.scrollIntoView({ block: 'end' });
    }, [messages.length]);

    const send = async (event: React.FormEvent) => {
        event.preventDefault();

        const body = draft.trim();
        if (!active || sending) return;
        if (!body && files.length === 0 && !product) return;

        setSending(true);
        setError(null);

        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            const form = new FormData();
            form.append('body', body);
            if (product) form.append('product_id', String(product.id));
            files.forEach((file) => form.append('files[]', file));

            const response = await fetch(`/dashboard/chat/${active.id}`, {
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
                        ?? 'Balasan belum terkirim. Coba lagi sebentar lagi.',
                );

                return;
            }

            const data = await response.json();
            setMessages(data.messages ?? []);
            setDraft('');
            setFiles([]);
            setProduct(null);
        } catch {
            setError('Koneksi terputus. Balasan belum terkirim.');
        } finally {
            setSending(false);
        }
    };

    return (
        <DashboardLayout title="Chat" area="creator">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-[1.375rem] font-semibold tracking-[-.02em]">Chat pembeli</h1>
                    <p className="mt-1.5 text-sm text-muted">
                        Pertanyaan dari halaman produk dan checkout tokomu. Balas cepat, konversi naik.
                    </p>
                </div>
            </div>

            <AutoReplyPanel autoReply={autoReply} />

            <Card className="mt-4 grid overflow-hidden lg:grid-cols-[19rem_minmax(0,1fr)] lg:divide-x lg:divide-[var(--border)]">
                <ul className={cn('max-h-[32rem] overflow-y-auto', active && 'hidden lg:block')}>
                    {conversations.length === 0 ? (
                        <li className="px-4 py-10 text-center">
                            <span className="mx-auto grid size-10 place-items-center rounded-full bg-surface-2 text-muted">
                                <MessageCircle className="size-5" />
                            </span>
                            <p className="mt-3 text-sm font-medium">Belum ada chat</p>
                            <p className="mt-1 text-xs leading-5 text-muted">
                                Tombol chat sudah tampil di halaman produkmu. Pesan pertama akan muncul di sini.
                            </p>
                        </li>
                    ) : (
                        conversations.map((row) => (
                            <li key={row.id}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.get('/dashboard/chat', { percakapan: row.id }, {
                                            preserveState: false,
                                            preserveScroll: true,
                                        })
                                    }
                                    className={cn(
                                        'flex w-full gap-2.5 border-b border-line px-3 py-3 text-left transition-colors hover:bg-surface-2',
                                        active?.id === row.id && 'bg-surface-2',
                                    )}
                                >
                                    {row.avatar_url ? (
                                        <img src={row.avatar_url} alt="" className="size-9 shrink-0 rounded-full object-cover" />
                                    ) : (
                                        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-[var(--nav)] text-[0.6875rem] font-semibold text-white">
                                            {initials(row.name)}
                                        </span>
                                    )}

                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-1.5">
                                            <span className="truncate text-[0.8125rem] font-medium">{row.name}</span>
                                            {row.is_guest && (
                                                <span className="shrink-0 rounded bg-surface-2 px-1 py-0.5 text-[0.625rem] text-muted">tamu</span>
                                            )}
                                            <span className="ml-auto shrink-0 text-[0.625rem] text-muted">{row.at_human}</span>
                                        </span>
                                        <span className="mt-0.5 flex items-center gap-2">
                                            <span className={cn('line-clamp-1 flex-1 text-xs', row.unread > 0 ? 'font-medium text-fg' : 'text-muted')}>
                                                {row.from_buyer ? '' : 'Kamu: '}
                                                {row.preview}
                                            </span>
                                            {row.unread > 0 && (
                                                <span className="grid min-w-4 shrink-0 place-items-center rounded-full bg-[var(--primary)] px-1 text-[0.625rem] font-semibold text-white">
                                                    {row.unread}
                                                </span>
                                            )}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))
                    )}
                </ul>

                {active ? (
                    <div className="flex h-[32rem] flex-col">
                        <div className="flex items-center gap-2 border-b border-line px-4 py-3">
                            <button
                                type="button"
                                onClick={() => router.get('/dashboard/chat')}
                                className="text-[0.8125rem] font-medium text-[var(--primary)] lg:hidden"
                            >
                                ← Semua
                            </button>
                            <div className="min-w-0">
                                <p className="truncate text-[0.9375rem] font-semibold">{active.name}</p>
                                <p className={cn('text-[0.6875rem]', buyer.online ? 'font-medium text-emerald-600' : 'text-muted')}>
                                    {buyer.online ? 'Sedang online' : buyer.label || (active.is_guest ? 'Belum punya akun' : '')}
                                </p>
                            </div>
                        </div>

                        <div className="flex-1 space-y-2.5 overflow-y-auto px-4 py-4">
                            {messages.map((message) => (
                                <div key={message.id} className={cn('flex', message.sender === 'seller' ? 'justify-end' : 'justify-start')}>
                                    <div
                                        className={cn(
                                            'max-w-[75%] rounded-2xl px-3 py-2',
                                            message.sender === 'seller'
                                                ? 'rounded-br-sm bg-[var(--primary)] text-white'
                                                : 'rounded-bl-sm bg-surface-2',
                                        )}
                                    >
                                        {message.context && (
                                            <a
                                                href={message.context.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={cn(
                                                    'mb-1.5 flex items-center gap-2 rounded-lg p-1.5 text-[0.6875rem]',
                                                    message.sender === 'seller' ? 'bg-white/15' : 'bg-surface',
                                                )}
                                            >
                                                {message.context.image && (
                                                    <img src={message.context.image} alt="" className="size-8 shrink-0 rounded object-cover" />
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    <span className="line-clamp-2">{message.context.label}</span>
                                                    {message.context.price > 0 && (
                                                        <span className="mt-0.5 block font-semibold">{formatIDR(message.context.price)}</span>
                                                    )}
                                                </span>
                                            </a>
                                        )}

                                        {(message.attachments ?? []).map((file) =>
                                            file.kind === 'image' ? (
                                                <a key={file.id} href={file.url} target="_blank" rel="noopener noreferrer" className="mb-1.5 block">
                                                    <img src={file.url} alt={file.name} loading="lazy" className="max-h-48 rounded-lg object-cover" />
                                                </a>
                                            ) : file.kind === 'video' ? (
                                                <video key={file.id} src={file.url} controls preload="none" className="mb-1.5 max-h-48 rounded-lg bg-black" />
                                            ) : (
                                                <a
                                                    key={file.id}
                                                    href={file.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className={cn(
                                                        'mb-1.5 flex items-center gap-2 rounded-lg p-2 text-[0.75rem]',
                                                        message.sender === 'seller' ? 'bg-white/15' : 'bg-surface',
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

                                        {message.body && (
                                            <p className="whitespace-pre-wrap break-words text-[0.8125rem] leading-5">{message.body}</p>
                                        )}

                                        <p className={cn('mt-0.5 flex items-center justify-end gap-1.5 text-[0.625rem]', message.sender === 'seller' ? 'opacity-70' : 'text-muted')}>
                                            {message.is_auto && <span className="rounded bg-black/10 px-1">otomatis</span>}
                                            {message.at_human}
                                        </p>
                                    </div>
                                </div>
                            ))}
                            <div ref={endRef} />
                        </div>

                        {error && <p className="border-t border-line px-4 py-2 text-xs text-[var(--danger)]">{error}</p>}

                        <form onSubmit={send} className="border-t border-line p-3">
                            {/* What is being recommended, before it is sent. */}
                            {product && (
                                <div className="mb-2 flex items-center gap-2 rounded-[var(--radius-field)] border border-line p-1.5">
                                    {product.thumbnail_url && (
                                        <img src={product.thumbnail_url} alt="" className="size-9 shrink-0 rounded object-cover" />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="line-clamp-1 text-[0.6875rem] font-medium">{product.name}</span>
                                        <span className="block text-[0.6875rem] text-[var(--primary)]">{formatIDR(product.price)}</span>
                                    </span>
                                    <button type="button" onClick={() => setProduct(null)} className="grid size-6 place-items-center rounded text-muted" aria-label="Lepas produk">
                                        <X className="size-3.5" />
                                    </button>
                                </div>
                            )}

                            {files.length > 0 && (
                                <div className="mb-2 flex flex-wrap gap-1.5">
                                    {files.map((file, index) => (
                                        <span key={index} className="relative flex items-center gap-1.5 rounded-lg border border-line py-1 pl-1.5 pr-6 text-[0.6875rem]">
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

                            {picking && (
                                <div className="mb-2 max-h-48 overflow-y-auto rounded-[var(--radius-field)] border border-line">
                                    {products.length === 0 ? (
                                        <p className="px-3 py-4 text-center text-xs text-muted">Belum ada produk aktif untuk dikirim.</p>
                                    ) : (
                                        products.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => {
                                                    setProduct(item);
                                                    setPicking(false);
                                                }}
                                                className="flex w-full items-center gap-2 border-b border-line px-2 py-2 text-left last:border-0 hover:bg-surface-2"
                                            >
                                                {item.thumbnail_url && (
                                                    <img src={item.thumbnail_url} alt="" className="size-8 shrink-0 rounded object-cover" />
                                                )}
                                                <span className="min-w-0 flex-1">
                                                    <span className="line-clamp-1 text-xs font-medium">{item.name}</span>
                                                    <span className="text-[0.6875rem] text-muted">{formatIDR(item.price)}</span>
                                                </span>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}

                            <div className="flex items-end gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => picker.current?.click()}
                                    className="grid size-9 shrink-0 place-items-center rounded-[var(--radius-field)] border border-line"
                                    aria-label="Lampirkan file"
                                >
                                    <Paperclip className="size-4" />
                                </button>

                                {/* "Langsung checkout aja kak" only works if the
                                    thing to check out is one tap away. */}
                                <button
                                    type="button"
                                    onClick={() => setPicking((open) => !open)}
                                    className={cn(
                                        'grid size-9 shrink-0 place-items-center rounded-[var(--radius-field)] border',
                                        picking ? 'border-[var(--primary)] text-[var(--primary)]' : 'border-line',
                                    )}
                                    aria-label="Kirim produk"
                                    title="Kirim produk"
                                >
                                    <Package className="size-4" />
                                </button>

                                <input
                                    ref={picker}
                                    type="file"
                                    multiple
                                    accept={ACCEPT}
                                    onChange={(e) => {
                                        if (e.target.files) setFiles((current) => [...current, ...Array.from(e.target.files!)].slice(0, 5));
                                        e.target.value = '';
                                    }}
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
                                    placeholder="Tulis balasan… (Enter untuk kirim)"
                                    className="max-h-28 min-h-9 flex-1 resize-none rounded-[var(--radius-field)] border border-line bg-surface px-3 py-2 text-[0.8125rem] outline-none"
                                />

                                <button
                                    type="submit"
                                    disabled={sending || (!draft.trim() && files.length === 0 && !product)}
                                    className="grid size-9 shrink-0 place-items-center rounded-[var(--radius-field)] bg-[var(--primary)] text-white disabled:opacity-40"
                                    aria-label="Kirim balasan"
                                >
                                    <Send className="size-4" />
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <div className="hidden h-[32rem] flex-col items-center justify-center text-center lg:flex">
                        <span className="grid size-11 place-items-center rounded-full bg-surface-2 text-muted">
                            <MessageCircle className="size-5" />
                        </span>
                        <p className="mt-3 text-sm font-medium">Pilih satu percakapan</p>
                        <p className="mt-1 max-w-xs text-xs leading-5 text-muted">
                            Balasan terkirim langsung ke pembeli, termasuk yang belum punya akun.
                        </p>
                    </div>
                )}
            </Card>
        </DashboardLayout>
    );
}

/**
 * The line the shop says while nobody is at the desk.
 *
 * Kept to one short message on purpose: it is read by someone who has just
 * asked a real question, and a paragraph of marketing in that moment reads as
 * being fobbed off.
 */
function AutoReplyPanel({ autoReply }: { autoReply: { enabled: boolean; message: string | null } }) {
    const [open, setOpen] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        enabled: autoReply.enabled,
        message: autoReply.message ?? 'Halo! Terima kasih sudah menghubungi. Pesanmu sudah masuk dan akan dibalas maksimal dalam 1x24 jam.',
    });

    return (
        <Card className="p-4 sm:p-5">
            <div className="flex flex-wrap items-center gap-3">
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-2 text-muted">
                    <Bot className="size-4" />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="text-[0.9375rem] font-semibold">Balasan otomatis</p>
                    <p className="mt-0.5 text-xs leading-5 text-muted">
                        {autoReply.enabled
                            ? 'Aktif — dikirim sekali, hanya sebelum kamu membalas.'
                            : 'Pembeli yang bertanya tengah malam tahu kapan kamu jawab.'}
                    </p>
                </div>

                <Button size="sm" variant="outline" onClick={() => setOpen((value) => !value)}>
                    {open ? 'Tutup' : 'Atur'}
                </Button>
            </div>

            {open && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        put('/dashboard/chat/balasan-otomatis', { preserveScroll: true, onSuccess: () => setOpen(false) });
                    }}
                    className="mt-4 border-t border-line pt-4"
                >
                    <Switch
                        checked={data.enabled}
                        onChange={(value) => setData('enabled', value)}
                        label="Kirim balasan otomatis"
                        description="Hanya untuk pesan pertama di tiap percakapan."
                    />

                    <Textarea
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        rows={3}
                        maxLength={500}
                        className="mt-3"
                        placeholder="Halo! Pesanmu sudah masuk, dibalas maksimal 1x24 jam."
                    />
                    {errors.message && <p className="mt-1 text-xs text-[var(--danger)]">{errors.message}</p>}

                    <p className="mt-2 text-xs text-muted">
                        Pembeli melihat label “otomatis” pada pesan ini — mereka harus bisa membedakannya dari kamu.
                    </p>

                    <Button type="submit" size="sm" className="mt-3" loading={processing}>
                        Simpan
                    </Button>
                </form>
            )}
        </Card>
    );
}
