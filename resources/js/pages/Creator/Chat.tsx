import { router } from '@inertiajs/react';
import { MessageCircle, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card } from '@/components/ui';
import { cn, initials } from '@/lib/utils';

interface Message {
    id: number;
    sender: 'buyer' | 'seller';
    body: string;
    context: { kind: string; label: string; url: string; image: string | null; price: number } | null;
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

/** Slow enough to be free, fast enough that a live conversation feels live. */
const POLL_MS = 7000;

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
}: {
    conversations: Row[];
    active: { id: number; name: string; is_guest: boolean; messages: Message[] } | null;
}) {
    const [messages, setMessages] = useState<Message[]>(active?.messages ?? []);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const endRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        setMessages(active?.messages ?? []);
        setDraft('');
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
        if (!body || !active || sending) return;

        setSending(true);
        setError(null);

        try {
            const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

            const response = await fetch(`/dashboard/chat/${active.id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                setError('Balasan belum terkirim. Coba lagi sebentar lagi.');

                return;
            }

            const data = await response.json();
            setMessages(data.messages ?? []);
            setDraft('');
        } catch {
            setError('Koneksi terputus. Balasan belum terkirim.');
        } finally {
            setSending(false);
        }
    };

    return (
        <DashboardLayout title="Chat" area="creator">
            <div className="mb-5">
                <h1 className="text-[1.375rem] font-semibold tracking-[-.02em]">Chat pembeli</h1>
                <p className="mt-1.5 text-sm text-muted">
                    Pertanyaan yang masuk dari halaman produk dan checkout tokomu. Balas cepat, konversi naik.
                </p>
            </div>

            <Card className="grid overflow-hidden lg:grid-cols-[19rem_minmax(0,1fr)] lg:divide-x lg:divide-[var(--border)]">
                {/* Thread list */}
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

                {/* Thread */}
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
                            <p className="truncate text-[0.9375rem] font-semibold">{active.name}</p>
                            {active.is_guest && (
                                <span className="rounded bg-surface-2 px-1.5 py-0.5 text-[0.625rem] text-muted">
                                    belum punya akun
                                </span>
                            )}
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
                                                <span className="line-clamp-2 min-w-0 flex-1">{message.context.label}</span>
                                            </a>
                                        )}

                                        <p className="whitespace-pre-wrap break-words text-[0.8125rem] leading-5">{message.body}</p>
                                        <p className={cn('mt-0.5 text-right text-[0.625rem]', message.sender === 'seller' ? 'opacity-70' : 'text-muted')}>
                                            {message.at_human}
                                        </p>
                                    </div>
                                </div>
                            ))}
                            <div ref={endRef} />
                        </div>

                        {error && <p className="border-t border-line px-4 py-2 text-xs text-[var(--danger)]">{error}</p>}

                        <form onSubmit={send} className="flex items-end gap-2 border-t border-line p-3">
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
                                disabled={sending || !draft.trim()}
                                className="grid size-9 shrink-0 place-items-center rounded-[var(--radius-field)] bg-[var(--primary)] text-white disabled:opacity-40"
                                aria-label="Kirim balasan"
                            >
                                <Send className="size-4" />
                            </button>
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
