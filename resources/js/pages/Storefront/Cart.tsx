import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, ImageIcon, Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CheckoutSheet } from '@/components/storefront/CheckoutSheet';
import { buildStorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatIDR } from '@/lib/utils';
import type { CartPayload } from '@/types';

interface StoreHeader {
    username: string;
    name: string;
    avatar_url: string | null;
    public_url: string;
    theme: Record<string, unknown>;
}

/**
 * The basket, as a page.
 *
 * The difference that matters is the checkbox. A basket is a shortlist as much
 * as an order — people park things in it — so buying three of the five items in
 * there has to be one action, not "delete two, buy, add them back". Selection
 * lives here and travels to checkout as a list of row ids; the server still
 * rebuilds what is actually charged from its own copy of the cart.
 */
export default function StorefrontCartPage({ store, cart }: { store: StoreHeader; cart: CartPayload }) {
    const theme = buildStorefrontTheme(store.theme as never);

    const buyable = cart.items.filter((item) => item.issue === null);
    const [selected, setSelected] = useState<number[]>(() => buyable.map((item) => item.id));
    const [checkout, setCheckout] = useState(false);

    // A line that sells out or is removed must not stay silently selected and
    // then vanish from the total.
    useEffect(() => {
        setSelected((current) => current.filter((id) => buyable.some((item) => item.id === id)));
    }, [cart]);

    const allSelected = buyable.length > 0 && selected.length === buyable.length;
    const chosen = buyable.filter((item) => selected.includes(item.id));
    const total = chosen.reduce((sum, item) => sum + item.line_total, 0);

    const toggle = (id: number) =>
        setSelected((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));

    const setQuantity = (id: number, quantity: number) =>
        router.put(`/${store.username}/keranjang/${id}`, { quantity }, { preserveScroll: true, preserveState: false });

    const remove = (id: number) =>
        router.delete(`/${store.username}/keranjang/${id}`, { preserveScroll: true, preserveState: false });

    const removeSelected = () => selected.forEach(remove);

    return (
        <div className="min-h-screen pb-28" style={theme.pageStyle}>
            <Head title={`Keranjang — ${store.name}`} />

            <div className={cn('border-b bg-[var(--sf-card)]', theme.line)}>
                <div className="mx-auto flex max-w-4xl items-center gap-2 px-4 py-3 text-[0.8125rem] sm:px-6">
                    <Link href={`/${store.username}`} className="inline-flex items-center gap-1.5 font-semibold hover:text-[var(--sf-primary)]">
                        <ArrowLeft className="size-4" />
                        {store.name}
                    </Link>
                    <span className={theme.muted}>/</span>
                    <span className={theme.muted}>Keranjang</span>
                </div>
            </div>

            <main className="mx-auto max-w-4xl space-y-3 px-3 py-4 sm:px-6">
                {cart.items.length === 0 ? (
                    <div className={cn(theme.card, 'flex flex-col items-center px-6 py-16 text-center')}>
                        <span className="grid size-12 place-items-center rounded-full bg-[color-mix(in_oklab,var(--sf-primary)_10%,transparent)] text-[var(--sf-primary)]">
                            <ShoppingBag className="size-6" />
                        </span>
                        <p className="mt-4 font-semibold">Keranjang kamu masih kosong</p>
                        <p className={cn('mt-1 text-sm', theme.muted)}>Yuk lihat-lihat dulu produk di toko ini.</p>
                        <Link
                            href={`/${store.username}`}
                            className={cn(theme.btnPrimary, 'mt-5 h-11 rounded px-6 text-sm')}
                        >
                            Lihat produk
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Store header row, the way a multi-shop basket is grouped. */}
                        <div className={cn(theme.card, 'flex items-center gap-3 px-4 py-3')}>
                            <input
                                type="checkbox"
                                checked={allSelected}
                                onChange={() => setSelected(allSelected ? [] : buyable.map((item) => item.id))}
                                className="size-4 accent-[var(--sf-primary)]"
                                aria-label="Pilih semua produk toko ini"
                            />
                            <span className="size-7 shrink-0 overflow-hidden rounded-full">
                                {store.avatar_url ? (
                                    <img src={store.avatar_url} alt="" className="size-full object-cover" />
                                ) : (
                                    <span
                                        className="grid size-full place-items-center text-xs font-bold"
                                        style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                                    >
                                        {store.name[0]?.toUpperCase()}
                                    </span>
                                )}
                            </span>
                            <p className="text-sm font-semibold">{store.name}</p>
                        </div>

                        <ul className={cn(theme.card, 'divide-y divide-[var(--sf-line)]')}>
                            {cart.items.map((item) => {
                                const blocked = item.issue !== null;

                                return (
                                    <li key={item.id} className={cn('flex gap-3 p-3 sm:p-4', blocked && 'opacity-70')}>
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(item.id)}
                                            disabled={blocked}
                                            onChange={() => toggle(item.id)}
                                            className="mt-8 size-4 shrink-0 accent-[var(--sf-primary)] disabled:opacity-40"
                                            aria-label={`Pilih ${item.name}`}
                                        />

                                        <Link href={`/${store.username}/p/${item.slug}`} className="size-20 shrink-0 overflow-hidden rounded bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)]">
                                            {item.thumbnail_url ? (
                                                <img src={item.thumbnail_url} alt="" className="size-full object-cover" />
                                            ) : (
                                                <span className="grid size-full place-items-center">
                                                    <ImageIcon className="size-5 opacity-40" />
                                                </span>
                                            )}
                                        </Link>

                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <Link href={`/${store.username}/p/${item.slug}`} className="line-clamp-2 text-[0.8125rem] font-medium leading-5">
                                                {item.name}
                                            </Link>

                                            <p className={cn('mt-0.5 text-xs', theme.muted)}>
                                                {item.variant_name ? `Variasi: ${item.variant_name}` : item.type_label}
                                            </p>

                                            {blocked && (
                                                <p className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-rose-500">
                                                    <AlertTriangle className="size-3.5" /> {item.issue}
                                                </p>
                                            )}

                                            <div className="mt-auto flex flex-wrap items-end justify-between gap-2 pt-2">
                                                <p className="text-[0.9375rem] font-semibold text-[var(--sf-primary)]">
                                                    {formatIDR(item.unit_price)}
                                                </p>

                                                <div className="flex items-center gap-2">
                                                    <div className={cn('inline-flex items-center rounded border', theme.line)}>
                                                        <button
                                                            type="button"
                                                            disabled={blocked || item.quantity <= item.min_quantity}
                                                            onClick={() => setQuantity(item.id, item.quantity - 1)}
                                                            className="grid size-7 place-items-center disabled:opacity-30"
                                                            aria-label="Kurangi"
                                                        >
                                                            <Minus className="size-3" />
                                                        </button>
                                                        <span className="w-8 text-center text-xs font-semibold tabular-nums">{item.quantity}</span>
                                                        <button
                                                            type="button"
                                                            disabled={blocked || (item.max_quantity !== null && item.quantity >= item.max_quantity)}
                                                            onClick={() => setQuantity(item.id, item.quantity + 1)}
                                                            className="grid size-7 place-items-center disabled:opacity-30"
                                                            aria-label="Tambah"
                                                        >
                                                            <Plus className="size-3" />
                                                        </button>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => remove(item.id)}
                                                        className={cn('grid size-7 place-items-center rounded', theme.muted)}
                                                        aria-label={`Hapus ${item.name}`}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </main>

            {/* Sticky summary, the last thing on screen wherever you scrolled to. */}
            {cart.items.length > 0 && (
                <div className={cn('fixed inset-x-0 bottom-0 z-40 border-t bg-[var(--sf-card)]', theme.line)}>
                    <div className="mx-auto flex max-w-4xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                        <label className="flex items-center gap-2 text-[0.8125rem]">
                            <input
                                type="checkbox"
                                checked={allSelected}
                                onChange={() => setSelected(allSelected ? [] : buyable.map((item) => item.id))}
                                className="size-4 accent-[var(--sf-primary)]"
                            />
                            Pilih Semua ({buyable.length})
                        </label>

                        {selected.length > 0 && (
                            <button type="button" onClick={removeSelected} className={cn('text-[0.8125rem]', theme.muted)}>
                                Hapus
                            </button>
                        )}

                        <div className="ml-auto flex items-center gap-3">
                            <div className="text-right">
                                <p className={cn('text-[0.6875rem]', theme.muted)}>Total ({chosen.length} produk)</p>
                                <p className="text-lg font-bold leading-tight text-[var(--sf-primary)]">{formatIDR(total)}</p>
                            </div>

                            <button
                                type="button"
                                disabled={chosen.length === 0}
                                onClick={() => setCheckout(true)}
                                className={cn(theme.btnPrimary, 'h-11 rounded px-7 text-sm disabled:opacity-40')}
                            >
                                Checkout
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {checkout && (
                <CheckoutSheet
                    cart={{ ...cart, items: chosen, subtotal: total, item_count: chosen.reduce((n, i) => n + i.quantity, 0) }}
                    cartItemIds={chosen.map((item) => item.id)}
                    storeUsername={store.username}
                    isPreview={false}
                    theme={theme}
                    onClose={() => setCheckout(false)}
                />
            )}
        </div>
    );
}
