import { router } from '@inertiajs/react';
import { AlertTriangle, Minus, Plus, ShoppingBag, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatIDR } from '@/lib/utils';
import type { CartLine, CartPayload } from '@/types';

/**
 * The basket drawer.
 *
 * Every mutation goes to the server and the refreshed cart comes back with the
 * page props — there is no client-side copy of the basket that could drift from
 * what will actually be charged.
 */
export function CartSheet({
    cart,
    storeUsername,
    theme,
    onCheckout,
    onClose,
}: {
    cart: CartPayload | null;
    storeUsername: string;
    theme: StorefrontTheme;
    onCheckout: () => void;
    onClose: () => void;
}) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const lines = cart?.items ?? [];
    const buyable = lines.filter((line) => line.issue === null);

    const setQuantity = (line: CartLine, quantity: number) => {
        setBusyId(line.id);
        router.put(
            `/${storeUsername}/keranjang/${line.id}`,
            { quantity },
            { preserveScroll: true, preserveState: true, onFinish: () => setBusyId(null) },
        );
    };

    const remove = (line: CartLine) => {
        setBusyId(line.id);
        router.delete(`/${storeUsername}/keranjang/${line.id}`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusyId(null),
        });
    };

    return (
        <div
            className="fixed inset-0 z-[80] flex justify-end bg-black/60 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cart-title"
            onClick={(event) => event.target === event.currentTarget && onClose()}
            style={theme.vars}
        >
            <aside
                className={cn(
                    'flex h-full w-full max-w-md animate-rise flex-col bg-[var(--sf-card)] shadow-2xl',
                    theme.dark ? 'text-slate-100' : 'text-slate-900',
                )}
            >
                <header className="flex items-center justify-between gap-3 border-b border-[var(--sf-line)] p-5">
                    <h2 id="cart-title" className="flex items-center gap-2 text-lg font-extrabold">
                        <ShoppingBag className="size-5" />
                        Keranjang
                        {lines.length > 0 && (
                            <span className={cn('text-sm font-semibold', theme.muted)}>({cart?.item_count})</span>
                        )}
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup keranjang"
                        className={cn('grid size-9 place-items-center rounded-lg hover:bg-black/5', theme.muted)}
                    >
                        <X className="size-5" />
                    </button>
                </header>

                {lines.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center px-8 text-center">
                        <ShoppingBag className="size-10 opacity-25" />
                        <p className="mt-4 font-extrabold">Keranjangmu masih kosong</p>
                        <p className={cn('mt-1.5 text-sm', theme.muted)}>
                            Tambah produk dulu, nanti bisa dibayar sekaligus dalam satu transaksi.
                        </p>
                        <button
                            type="button"
                            onClick={onClose}
                            className={cn(theme.btnPrimary, 'mt-6 h-11 px-6 text-sm')}
                        >
                            Lihat produk
                        </button>
                    </div>
                ) : (
                    <>
                        <div className="flex-1 space-y-3 overflow-y-auto p-5">
                            {cart?.has_issue && (
                                <p className="flex items-start gap-2 rounded-xl bg-amber-100 px-4 py-3 text-xs leading-relaxed text-amber-900">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    Ada item yang tidak bisa dilanjutkan. Item itu tidak dihitung ke total dan tidak akan
                                    ikut dibayar.
                                </p>
                            )}

                            {lines.map((line) => (
                                <article
                                    key={line.id}
                                    className={cn(
                                        'flex gap-3 rounded-xl border border-[var(--sf-line)] p-3',
                                        line.issue && 'opacity-70',
                                    )}
                                >
                                    <a
                                        href={`/${storeUsername}/p/${line.slug}`}
                                        className="size-16 shrink-0 overflow-hidden rounded-lg bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)]"
                                    >
                                        {line.thumbnail_url ? (
                                            <img src={line.thumbnail_url} alt="" className="size-full object-cover" />
                                        ) : (
                                            <span className="grid size-full place-items-center">
                                                <ShoppingBag className="size-5 opacity-30" />
                                            </span>
                                        )}
                                    </a>

                                    <div className="min-w-0 flex-1">
                                        <p className="line-clamp-2 text-sm font-bold leading-snug">{line.name}</p>
                                        {line.variant_name && (
                                            <p className={cn('mt-0.5 text-xs', theme.muted)}>{line.variant_name}</p>
                                        )}

                                        {line.issue ? (
                                            <p className="mt-1 text-xs font-bold text-rose-500">{line.issue}</p>
                                        ) : (
                                            <p className="mt-1 text-sm font-extrabold tabular-nums text-[var(--sf-primary)]">
                                                {formatIDR(line.line_total)}
                                            </p>
                                        )}

                                        <div className="mt-2 flex items-center justify-between gap-2">
                                            <div className="flex items-center gap-1 rounded-lg border border-[var(--sf-line)] p-0.5">
                                                <button
                                                    type="button"
                                                    aria-label={`Kurangi ${line.name}`}
                                                    disabled={busyId === line.id || line.quantity <= line.min_quantity}
                                                    onClick={() => setQuantity(line, line.quantity - 1)}
                                                    className="grid size-7 place-items-center rounded hover:bg-black/5 disabled:opacity-35"
                                                >
                                                    <Minus className="size-3.5" />
                                                </button>
                                                <span className="w-8 text-center text-sm font-bold tabular-nums">
                                                    {line.quantity}
                                                </span>
                                                <button
                                                    type="button"
                                                    aria-label={`Tambah ${line.name}`}
                                                    disabled={busyId === line.id || line.quantity >= line.max_quantity}
                                                    onClick={() => setQuantity(line, line.quantity + 1)}
                                                    className="grid size-7 place-items-center rounded hover:bg-black/5 disabled:opacity-35"
                                                >
                                                    <Plus className="size-3.5" />
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() => remove(line)}
                                                disabled={busyId === line.id}
                                                aria-label={`Hapus ${line.name}`}
                                                className={cn(
                                                    'grid size-8 place-items-center rounded-lg hover:bg-rose-500/10 hover:text-rose-500',
                                                    theme.muted,
                                                )}
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>

                        <footer className="space-y-3 border-t border-[var(--sf-line)] p-5">
                            <div className="flex items-center justify-between">
                                <span className={cn('text-sm', theme.muted)}>Subtotal</span>
                                <span className="text-xl font-black tabular-nums text-[var(--sf-primary)]">
                                    {formatIDR(cart?.subtotal ?? 0)}
                                </span>
                            </div>

                            <button
                                type="button"
                                onClick={onCheckout}
                                disabled={buyable.length === 0}
                                className={cn(theme.btnPrimary, 'h-12 w-full px-5 text-base shadow-md')}
                            >
                                Lanjut ke pembayaran
                            </button>

                            <p className={cn('text-center text-xs', theme.muted)}>
                                Kupon dan ongkos kirim dihitung di langkah berikutnya.
                            </p>
                        </footer>
                    </>
                )}
            </aside>
        </div>
    );
}
