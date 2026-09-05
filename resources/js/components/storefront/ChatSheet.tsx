import { MessageCircle, Send, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { buildStorefrontTheme } from '@/lib/storefront-theme';

export interface ChatMessage {
    id: number;
    sender: 'buyer' | 'seller';
    body: string;
    context: { kind: string; label: string; url: string; image: string | null; price: number } | null;
    read: boolean;
    at: string;
    at_human: string;
}

type Theme = ReturnType<typeof buildStorefrontTheme>;

/** Only while the panel is open; a closed panel has nothing to refresh. */
const POLL_MS = 8000;

/**
 * The buyer's chat with the shop.
 *
 * Deliberately a panel rather than a page: a question is usually asked while
 * looking at the thing it is about, and navigating away to ask it loses both
 * the product and the buyer's place. The product being viewed rides along with
 * the first message, so the seller does not have to ask what "this" is.
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
    whatsapp,
    theme,
    onClose,
}: {
    storeUsername: string;
    storeName: string;
    storeAvatar?: string | null;
    productId?: number;
    productName?: string;
    whatsapp?: string | null;
    theme: Theme;
    onClose: () => void;
}) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [name, setName] = useState('');
    const [sending, setSending] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const endRef = useRef<HTMLDivElement | null>(null);

    const load = async () => {
        try {
            const response = await fetch(`/${storeUsername}/chat`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            setMessages(data.messages ?? []);
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

    const send = async (event: React.FormEvent) => {
        event.preventDefault();

        const body = draft.trim();
        if (!body || sending) return;

        setSending(true);
        setError(null);

        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            const response = await fetch(`/${storeUsername}/chat`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    body,
                    // Only attached to the first message of a fresh thread.
                    ...(messages.length === 0 && productId ? { product_id: productId } : {}),
                    ...(messages.length === 0 && name.trim() ? { name: name.trim() } : {}),
                }),
            });

            if (!response.ok) {
                setError(
                    response.status === 429
                        ? 'Kebanyakan pesan sekaligus. Tunggu sebentar ya.'
                        : 'Pesan belum terkirim. Coba lagi sebentar lagi.',
                );

                return;
            }

            const data = await response.json();
            setMessages(data.messages ?? []);
            setDraft('');
        } catch {
            setError('Koneksi terputus. Pesan belum terkirim.');
        } finally {
            setSending(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[80] flex items-end justify-end sm:p-4" role="dialog" aria-label={`Chat dengan ${storeName}`}>
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
                aria-label="Tutup chat"
                tabIndex={-1}
            />

            <div className="relative flex h-[85vh] w-full flex-col overflow-hidden rounded-t-2xl bg-[var(--sf-card)] shadow-2xl sm:h-[32rem] sm:max-w-sm sm:rounded-2xl">
                <header className={cn('flex items-center gap-2.5 border-b px-4 py-3', theme.line)}>
                    <span className="size-9 shrink-0 overflow-hidden rounded-full">
                        {storeAvatar ? (
                            <img src={storeAvatar} alt="" className="size-full object-cover" />
                        ) : (
                            <span
                                className="grid size-full place-items-center text-sm font-bold"
                                style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                            >
                                {storeName[0]?.toUpperCase()}
                            </span>
                        )}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold">{storeName}</p>
                        <p className={cn('truncate text-[0.6875rem]', theme.muted)}>Biasanya dibalas dalam beberapa jam</p>
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
                            {productName && (
                                <p className={cn('mt-3 text-[0.6875rem]', theme.muted)}>
                                    Pesan pertamamu otomatis menyertakan “{productName}”.
                                </p>
                            )}
                        </div>
                    ) : (
                        messages.map((message) => <Bubble key={message.id} message={message} theme={theme} />)
                    )}
                    <div ref={endRef} />
                </div>

                {error && (
                    <p className="border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-700">{error}</p>
                )}

                <form onSubmit={send} className={cn('border-t p-2.5', theme.line)}>
                    {loaded && messages.length === 0 && (
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Nama kamu (opsional)"
                            maxLength={80}
                            className={cn('mb-2 h-9 w-full rounded-lg border px-3 text-[0.8125rem] outline-none', theme.line)}
                        />
                    )}

                    <div className="flex items-end gap-2">
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
                            disabled={sending || !draft.trim()}
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

function Bubble({ message, theme }: { message: ChatMessage; theme: Theme }) {
    const mine = message.sender === 'buyer';

    return (
        <div className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
            <div className={cn('max-w-[80%] rounded-2xl px-3 py-2', mine ? 'rounded-br-sm' : 'rounded-bl-sm')}
                style={
                    mine
                        ? { background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }
                        : { background: 'color-mix(in oklab, var(--sf-fg) 7%, transparent)' }
                }
            >
                {message.context && (
                    <a
                        href={message.context.url}
                        className={cn(
                            'mb-1.5 flex items-center gap-2 rounded-lg p-1.5 text-[0.6875rem]',
                            mine ? 'bg-white/15' : 'bg-[var(--sf-card)]',
                        )}
                    >
                        {message.context.image && (
                            <img src={message.context.image} alt="" className="size-8 shrink-0 rounded object-cover" />
                        )}
                        <span className="line-clamp-2 min-w-0 flex-1">{message.context.label}</span>
                    </a>
                )}

                <p className="whitespace-pre-wrap break-words text-[0.8125rem] leading-5">{message.body}</p>
                <p className={cn('mt-0.5 text-right text-[0.625rem]', mine ? 'opacity-70' : theme.muted)}>
                    {message.at_human}
                </p>
            </div>
        </div>
    );
}
