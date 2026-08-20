import { useForm } from '@inertiajs/react';
import { Minus, Plus, ShieldCheck, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { cn, formatIDR, uid } from '@/lib/utils';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import type { StorefrontProduct } from '@/types';

/**
 * Buy-now sheet. Submits to the store checkout endpoint, which re-prices
 * everything server-side — amounts shown here are only a preview for the buyer.
 */
export function CheckoutSheet({
    product,
    storeUsername,
    isPreview,
    theme,
    onClose,
}: {
    product: StorefrontProduct;
    storeUsername: string;
    isPreview: boolean;
    theme: StorefrontTheme;
    onClose: () => void;
}) {
    const [quantity, setQuantity] = useState(1);
    const [customPrice, setCustomPrice] = useState(product.minimum_price ?? 0);

    const { data, setData, post, transform, processing, errors } = useForm({
        items: [] as any[],
        name: '',
        email: '',
        phone: '',
        note: '',
        coupon_code: '',
        marketing_consent: false as boolean,
        terms: false as boolean,
        idempotency_key: uid(),
    });

    const unitPrice = product.is_pay_what_you_want ? customPrice : product.price;
    const subtotal = unitPrice * quantity;

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (isPreview) return;

        transform((current) => ({
            ...current,
            items: [
                {
                    product_id: product.id,
                    quantity,
                    ...(product.is_pay_what_you_want ? { price: customPrice } : {}),
                },
            ],
        }));

        post(`/${storeUsername}/checkout`);
    };

    const field = cn(
        'w-full rounded-xl border bg-transparent px-4 py-3 text-sm outline-none transition-colors',
        theme.line,
        'focus:border-[var(--sf-primary)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--sf-primary)_28%,transparent)]',
    );

    return (
        <div
            className="fixed inset-0 z-[80] flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="checkout-title"
            onClick={(e) => e.target === e.currentTarget && onClose()}
            style={theme.vars}
        >
            <div
                className={cn(
                    theme.card,
                    'max-h-[92vh] w-full max-w-md animate-rise overflow-y-auto rounded-b-none shadow-2xl sm:rounded-b-2xl',
                    theme.dark ? 'text-slate-100' : 'text-slate-900',
                )}
            >
                <div
                    className={cn(
                        'sticky top-0 z-10 flex items-start justify-between gap-3 border-b bg-[var(--sf-card)] p-5',
                        theme.line,
                    )}
                >
                    <div className="min-w-0">
                        <p className={cn('text-xs font-bold uppercase tracking-wide', theme.muted)}>Langkah 1 dari 3 · Data pembeli</p>
                        <h2 id="checkout-title" className="truncate text-lg font-extrabold">
                            {product.name}
                        </h2>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup"
                        className={cn('grid size-9 shrink-0 place-items-center rounded-lg hover:bg-black/5', theme.muted)}
                    >
                        <X className="size-5" />
                    </button>
                </div>

                <div className="grid grid-cols-3 gap-1.5 px-5 pt-4" aria-label="Progres pembayaran">
                    <span className="h-1.5 rounded-full bg-[var(--sf-primary)]" /><span className="h-1.5 rounded-full bg-[var(--sf-line)]" /><span className="h-1.5 rounded-full bg-[var(--sf-line)]" />
                </div>

                <form onSubmit={submit} className="space-y-4 p-5">
                    {isPreview && (
                        <p className="rounded-xl bg-amber-100 px-4 py-3 text-sm text-amber-900">
                            Ini mode preview — checkout dinonaktifkan sampai toko dipublikasikan.
                        </p>
                    )}

                    {product.is_pay_what_you_want ? (
                        <label className="block">
                            <span className="mb-1.5 block text-sm font-semibold">Bayar seikhlasnya</span>
                            <input
                                type="number"
                                min={product.minimum_price ?? 0}
                                step={1000}
                                value={customPrice}
                                onChange={(e) => setCustomPrice(Number(e.target.value))}
                                className={field}
                            />
                            <span className={cn('mt-1.5 block text-xs', theme.muted)}>
                                Minimal {formatIDR(product.minimum_price ?? 0)}
                            </span>
                        </label>
                    ) : (
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm font-semibold">Jumlah</span>
                            <div className={cn('flex items-center gap-1 rounded-xl border p-1', theme.line)}>
                                <button
                                    type="button"
                                    onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                    aria-label="Kurangi"
                                    className="grid size-8 place-items-center rounded-lg hover:bg-black/5 disabled:opacity-40"
                                    disabled={quantity <= 1}
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="w-10 text-center font-bold tabular-nums">{quantity}</span>
                                <button
                                    type="button"
                                    onClick={() => setQuantity((q) => Math.min(99, q + 1))}
                                    aria-label="Tambah"
                                    className="grid size-8 place-items-center rounded-lg hover:bg-black/5"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    <Field label="Nama" required error={errors.name}>
                        <input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="name"
                            required
                            className={field}
                        />
                    </Field>

                    <Field label="Email" required error={errors.email} hint="Produk dan struk dikirim ke email ini.">
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="email"
                            required
                            className={field}
                        />
                    </Field>

                    <Field label="Nomor WhatsApp" error={errors.phone} hint="Opsional">
                        <input
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            autoComplete="tel"
                            className={field}
                        />
                    </Field>

                    <details className={cn('rounded-xl border p-4', theme.line)}>
                        <summary className="cursor-pointer text-sm font-bold">Punya kupon atau catatan? <span className={cn('font-normal', theme.muted)}>(opsional)</span></summary>
                        <div className="mt-4 space-y-4">
                            <Field label="Kode kupon" error={errors.coupon_code}><input value={data.coupon_code} onChange={(e) => setData('coupon_code', e.target.value.toUpperCase())} placeholder="Masukkan kode promo" className={cn(field, 'uppercase')} /></Field>
                            <Field label="Catatan untuk penjual" error={errors.note}><textarea rows={2} value={data.note} onChange={(e) => setData('note', e.target.value)} className={cn(field, 'resize-y')} /></Field>
                        </div>
                    </details>

                    {errors.items && (
                        <p className="rounded-xl bg-rose-100 px-4 py-3 text-sm text-rose-900">{errors.items}</p>
                    )}

                    <div className={cn('rounded-xl border p-4', theme.line)}>
                        <div className="flex items-center justify-between text-sm">
                            <span className={theme.muted}>Subtotal</span>
                            <span className="text-lg font-extrabold tabular-nums text-[var(--sf-primary)]">
                                {formatIDR(subtotal)}
                            </span>
                        </div>
                        <p className={cn('mt-1.5 text-xs', theme.muted)}>
                            Biaya layanan pembayaran dihitung setelah kamu pilih metode bayar.
                        </p>
                    </div>

                    <label className={cn('flex items-start gap-2.5 text-xs leading-relaxed', theme.muted)}>
                        <input
                            type="checkbox"
                            checked={data.marketing_consent}
                            onChange={(e) => setData('marketing_consent', e.target.checked)}
                            className="mt-0.5 size-4 shrink-0 accent-[var(--sf-primary)]"
                        />
                        Boleh kirim info promo ke emailku.
                    </label>

                    <label className={cn('flex items-start gap-2.5 text-xs leading-relaxed', theme.muted)}>
                        <input
                            type="checkbox"
                            checked={data.terms}
                            onChange={(e) => setData('terms', e.target.checked)}
                            className="mt-0.5 size-4 shrink-0 accent-[var(--sf-primary)]"
                            required
                        />
                        Aku setuju dengan syarat pembelian dan kebijakan refund yang berlaku.
                    </label>
                    {errors.terms && <p className="text-xs text-rose-500">{errors.terms}</p>}

                    <button
                        type="submit"
                        disabled={processing || isPreview}
                        className={cn(theme.btnPrimary, 'h-13 w-full px-5 text-base shadow-md')}
                    >
                        Lanjut pilih pembayaran · {formatIDR(subtotal)}
                    </button>

                    <p className={cn('flex items-center justify-center gap-1.5 text-xs', theme.muted)}>
                        <ShieldCheck className="size-3.5" />
                        Pembayaran diproses aman lewat JualanYok
                    </p>
                </form>
            </div>
        </div>
    );
}

function Field({
    label,
    required,
    error,
    hint,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold">
                {label}
                {required && <span className="ml-0.5 text-rose-500">*</span>}
            </span>
            {children}
            {error ? (
                <span className="mt-1.5 block text-xs text-rose-500">{error}</span>
            ) : hint ? (
                <span className="mt-1.5 block text-xs opacity-60">{hint}</span>
            ) : null}
        </label>
    );
}
