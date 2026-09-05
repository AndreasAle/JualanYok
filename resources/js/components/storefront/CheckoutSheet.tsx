import { useForm } from '@inertiajs/react';
import { Loader2, MapPin, Minus, Plus, Search, ShieldCheck, Truck, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { MapPicker } from '@/components/storefront/MapPicker';
import { cn, formatIDR, uid } from '@/lib/utils';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import type { CartPayload, StorefrontProduct } from '@/types';

type ShippingAddress = { address_line: string; district: string; city: string; province: string; postal_code: string; area_id: string; note: string; latitude: number | null; longitude: number | null };
type AreaResult = { id: string; name: string; postal_code?: string | number | null; administrative_division_level_1_name?: string | null; administrative_division_level_2_name?: string | null; administrative_division_level_3_name?: string | null };
type ShippingQuote = { provider: string; courier_company: string; courier_name: string; courier_type: string; service_name: string; delivery_fee: number; amount: number; insurance_fee: number; duration?: string | null; token: string };

/**
 * Checkout sheet for both paths: a single "buy now" product, or the whole
 * basket. Submits to the store checkout endpoint, which re-prices everything
 * server-side — amounts shown here are only a preview for the buyer. In cart
 * mode the browser sends no line items at all; the server rebuilds them from
 * the stored cart.
 */
export function CheckoutSheet({
    product = null,
    variantId = null,
    quantity: initialQuantity = 1,
    cart = null,
    cartItemIds = null,
    storeUsername,
    isPreview,
    theme,
    onClose,
}: {
    product?: StorefrontProduct | null;
    /** Chosen on the product page; the server re-validates it belongs here. */
    variantId?: number | null;
    /** Seeded from the product page's own stepper, so the choice carries over. */
    quantity?: number;
    cart?: CartPayload | null;
    /** Which cart rows the buyer ticked; the server still re-prices them. */
    cartItemIds?: number[] | null;
    storeUsername: string;
    isPreview: boolean;
    theme: StorefrontTheme;
    onClose: () => void;
}) {
    const fromCart = !product;
    const [quantity, setQuantity] = useState(Math.max(1, initialQuantity));
    const [customPrice, setCustomPrice] = useState(product?.minimum_price ?? 0);
    const [areaQuery, setAreaQuery] = useState('');
    const [areas, setAreas] = useState<AreaResult[]>([]);
    const [quotes, setQuotes] = useState<ShippingQuote[]>([]);
    const [shippingBusy, setShippingBusy] = useState(false);
    const [shippingError, setShippingError] = useState('');
    const areaSearchSequence = useRef(0);
    const areaSearchTimer = useRef<number | null>(null);

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
        from_cart: false as boolean,
        shipping_address: { address_line: '', district: '', city: '', province: '', postal_code: '', area_id: '', note: '', latitude: null, longitude: null } as ShippingAddress,
        shipping_quote_token: '',
    });

    const unitPrice = product?.is_pay_what_you_want ? customPrice : (product?.price ?? 0);
    const subtotal = fromCart ? (cart?.subtotal ?? 0) : unitPrice * quantity;
    const hasPhysical = product?.type === 'PHYSICAL' || !!cart?.items.some((item) => item.type === 'PHYSICAL' && item.issue === null);
    const selectedQuote = quotes.find((quote) => quote.token === data.shipping_quote_token);
    const checkoutTotal = subtotal + (selectedQuote?.amount ?? 0);

    const linesPayload = fromCart
        ? { from_cart: true, items: [] as any[], ...(cartItemIds ? { cart_item_ids: cartItemIds } : {}) }
        : { from_cart: false, items: [{ product_id: product!.id, ...(variantId ? { variant_id: variantId } : {}), quantity }] };

    const searchAreas = async (queryOverride?: string) => {
        if (areaSearchTimer.current !== null) {
            window.clearTimeout(areaSearchTimer.current);
            areaSearchTimer.current = null;
        }
        const typed = (queryOverride ?? areaQuery).trim();
        if (typed.length < 3) {
            setShippingError('Ketik minimal 3 huruf kecamatan, kota, provinsi, atau kode pos.');
            return;
        }
        const requestId = ++areaSearchSequence.current;
        setShippingBusy(true); setShippingError(''); setAreas([]); setQuotes([]); setData('shipping_quote_token', '');
        try {
            const response = await fetch(`/${storeUsername}/pengiriman/area?q=${encodeURIComponent(typed)}`, { headers: { Accept: 'application/json' } });
            const body = await response.json();
            if (requestId !== areaSearchSequence.current) return;
            if (!response.ok) throw new Error(body.message ?? 'Area tidak ditemukan.');
            setAreas(body.areas ?? []);
            if (!(body.areas ?? []).length) throw new Error('Wilayah tidak ditemukan. Periksa ejaan lalu coba lagi.');
        } catch (error) {
            if (requestId === areaSearchSequence.current) setShippingError(error instanceof Error ? error.message : 'Gagal mencari area.');
        } finally {
            if (requestId === areaSearchSequence.current) setShippingBusy(false);
        }
    };

    const selectArea = (area: AreaResult) => {
        areaSearchSequence.current += 1;
        const address = {
            ...data.shipping_address,
            area_id: area.id,
            district: area.administrative_division_level_3_name ?? area.name,
            city: area.administrative_division_level_2_name ?? '',
            province: area.administrative_division_level_1_name ?? '',
            postal_code: area.postal_code == null ? '' : String(area.postal_code),
        };
        setData('shipping_address', address);
        setAreaQuery(area.administrative_division_level_3_name ?? area.name); setAreas([]); setQuotes([]); setData('shipping_quote_token', '');

        if (address.address_line.trim()) {
            void loadQuotes(address);
        }
    };

    const loadQuotes = async (address: ShippingAddress = data.shipping_address) => {
        setShippingBusy(true); setShippingError(''); setQuotes([]); setData('shipping_quote_token', '');
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch(`/${storeUsername}/pengiriman/tarif`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ...linesPayload, shipping_address: address }),
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message ?? Object.values(body.errors ?? {})[0] ?? 'Tarif tidak tersedia.');
            const nextQuotes = body.quotes ?? [];
            setQuotes(nextQuotes);
            if (!nextQuotes.length) throw new Error('Belum ada layanan kurir ke alamat ini.');
            setData('shipping_quote_token', nextQuotes[0].token);
        } catch (error) { setShippingError(error instanceof Error ? error.message : 'Gagal memuat ongkir.'); }
        finally { setShippingBusy(false); }
    };

    useEffect(() => {
        const query = areaQuery.trim();
        if (!hasPhysical || data.shipping_address.area_id || query.length < 3) return;

        areaSearchTimer.current = window.setTimeout(() => void searchAreas(query), 500);
        return () => {
            if (areaSearchTimer.current !== null) window.clearTimeout(areaSearchTimer.current);
        };
        // Search is deliberately debounced from the buyer's current input.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [areaQuery, data.shipping_address.area_id, hasPhysical]);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (isPreview) return;

        if (hasPhysical && !data.shipping_address.area_id) {
            setShippingError('Pilih satu wilayah dari hasil pencarian alamat.');
            return;
        }

        if (hasPhysical && !data.shipping_quote_token) {
            setShippingError('Pilihan kurir belum tersedia. Lengkapi alamat lalu tampilkan ongkir.');
            if (data.shipping_address.address_line.trim()) void loadQuotes();
            return;
        }

        transform((current) => ({
            ...current,
            ...(fromCart
                ? { from_cart: true, items: [] as any[] }
                : {
                      items: [
                          {
                              product_id: product!.id,
                              ...(variantId ? { variant_id: variantId } : {}),
                              quantity,
                              ...(product!.is_pay_what_you_want ? { price: customPrice } : {}),
                          },
                      ],
                  }),
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
                            {product ? product.name : `${cart?.item_count ?? 0} item di keranjang`}
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

                <form onSubmit={submit} className="space-y-3.5 p-4 sm:p-5">
                    {isPreview && (
                        <p className="rounded-xl bg-amber-100 px-4 py-3 text-sm text-amber-900">
                            Ini mode preview — checkout dinonaktifkan sampai toko dipublikasikan.
                        </p>
                    )}

                    {fromCart ? (
                        <ul className={cn('space-y-2 rounded-xl border p-4', theme.line)}>
                            {(cart?.items ?? [])
                                .filter((line) => line.issue === null)
                                .map((line) => (
                                    <li key={line.id} className="flex items-start justify-between gap-3 text-sm">
                                        <span className="min-w-0">
                                            <span className="line-clamp-1 font-semibold">{line.name}</span>
                                            <span className={cn('text-xs', theme.muted)}>{line.quantity}×</span>
                                        </span>
                                        <span className="shrink-0 font-bold tabular-nums">{formatIDR(line.line_total)}</span>
                                    </li>
                                ))}
                        </ul>
                    ) : product!.is_pay_what_you_want ? (
                        <label className="block">
                            <span className="mb-1.5 block text-sm font-semibold">Bayar seikhlasnya</span>
                            <input
                                type="number"
                                min={product?.minimum_price ?? 0}
                                step={1000}
                                value={customPrice}
                                onChange={(e) => setCustomPrice(Number(e.target.value))}
                                className={field}
                            />
                            <span className={cn('mt-1.5 block text-xs', theme.muted)}>
                                Minimal {formatIDR(product?.minimum_price ?? 0)}
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

                    <Field label={hasPhysical ? 'Nomor HP penerima' : 'Nomor WhatsApp'} required={hasPhysical} error={errors.phone} hint={hasPhysical ? 'Dipakai kurir dan untuk konfirmasi pesanan.' : 'Dipakai untuk konfirmasi pembayaran.'}>
                        <input
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            autoComplete="tel"
                            required={hasPhysical}
                            className={field}
                        />
                    </Field>

                    {hasPhysical && (
                        <section className={cn('space-y-4 rounded-2xl border p-4', theme.line)}>
                            <div className="flex items-center gap-2.5">
                                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] text-[var(--sf-primary)]"><MapPin className="size-4" /></span>
                                <p className="text-sm font-semibold">Alamat pengiriman</p>
                                <span className={cn('ml-auto text-[0.6875rem]', theme.muted)}>
                                    Indonesia · {data.name || 'penerima di atas'}
                                </span>
                            </div>

                            <Field
                                label="Detail alamat"
                                required
                                error={(errors as Record<string, string>)['shipping_address.address_line']}
                            >
                                <textarea
                                    rows={2}
                                    value={data.shipping_address.address_line}
                                    onChange={(e) => {
                                        setData('shipping_address', { ...data.shipping_address, address_line: e.target.value });
                                        setQuotes([]); setData('shipping_quote_token', ''); setShippingError('');
                                    }}
                                    className={cn(field, 'resize-y')}
                                    placeholder="Jl. Merdeka No. 18, RT 02/RW 04, pagar hitam"
                                    autoComplete="street-address"
                                    required
                                />
                            </Field>

                            <Field
                                label="Kecamatan / kota"
                                required
                                error={(errors as Record<string, string>)['shipping_address.area_id']}
                            >
                                <div className="relative">
                                    <div className="flex gap-2">
                                        <input
                                            value={areaQuery}
                                            onChange={(e) => {
                                                areaSearchSequence.current += 1;
                                                setAreaQuery(e.target.value); setAreas([]); setQuotes([]); setShippingBusy(false); setShippingError('');
                                                setData({
                                                    ...data,
                                                    shipping_address: { ...data.shipping_address, district: '', city: '', province: '', postal_code: '', area_id: '', latitude: null, longitude: null },
                                                    shipping_quote_token: '',
                                                });
                                            }}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); void searchAreas(); } }}
                                            className={field}
                                            placeholder="Ketik 3 huruf, lalu pilih hasilnya"
                                            autoComplete="off"
                                        />
                                        <button type="button" onClick={() => void searchAreas()} disabled={shippingBusy} className={cn('grid size-12 shrink-0 place-items-center rounded-xl border transition hover:bg-black/5 disabled:opacity-50', theme.line)} aria-label="Cari wilayah pengiriman">
                                            {shippingBusy ? <Loader2 className="size-4 animate-spin" /> : <Search className="size-4" />}
                                        </button>
                                    </div>

                                    {areas.length > 0 && (
                                        <div className={cn('absolute inset-x-0 top-[calc(100%+0.4rem)] z-20 max-h-60 overflow-y-auto rounded-xl border bg-[var(--sf-card)] p-1 shadow-xl', theme.line)}>
                                            {areas.map((area) => (
                                                <button key={area.id} type="button" onClick={() => selectArea(area)} className="block w-full rounded-lg px-3 py-2.5 text-left transition hover:bg-black/5">
                                                    <span className="block text-xs font-bold">{area.name}</span>
                                                    <span className={cn('text-[11px]', theme.muted)}>{[area.administrative_division_level_3_name, area.administrative_division_level_2_name, area.administrative_division_level_1_name, area.postal_code].filter(Boolean).join(' · ')}</span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </Field>

                            {/*
                                The pin sits with the address it refines rather
                                than behind the district picker. Hidden until an
                                area had been chosen, nobody knew it was there —
                                and it is the difference between instant couriers
                                being offered and silently missing.
                            */}
                            <MapPicker
                                storeUsername={storeUsername}
                                latitude={data.shipping_address.latitude}
                                longitude={data.shipping_address.longitude}
                                hint={[data.shipping_address.district, data.shipping_address.city, data.shipping_address.province].filter(Boolean).join(', ')}
                                onChange={(position) => {
                                    setData('shipping_address', {
                                        ...data.shipping_address,
                                        latitude: position?.latitude ?? null,
                                        longitude: position?.longitude ?? null,
                                    });

                                    // Instant couriers only quote with a
                                    // coordinate, so the list is stale the moment
                                    // the pin moves.
                                    setQuotes([]);
                                    setData('shipping_quote_token', '');
                                }}
                            />

                            {data.shipping_address.area_id && (
                                <div className="space-y-3">
                                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                        <strong>Alamat terverifikasi:</strong> {[data.shipping_address.district, data.shipping_address.city, data.shipping_address.province, data.shipping_address.postal_code].filter(Boolean).join(', ')}
                                    </div>
                                    <Field label="Kode pos" required><input value={data.shipping_address.postal_code} onChange={(e) => setData('shipping_address', { ...data.shipping_address, postal_code: e.target.value })} inputMode="numeric" className={field} required /></Field>
                                    <Field label="Catatan kurir" hint="Opsional"><input value={data.shipping_address.note} onChange={(e) => setData('shipping_address', { ...data.shipping_address, note: e.target.value })} className={field} placeholder="Rumah pagar hitam" /></Field>

                                    {quotes.length === 0 && (
                                        <button type="button" onClick={() => void loadQuotes()} disabled={shippingBusy || !data.shipping_address.address_line.trim()} className={cn('inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border text-sm font-bold transition hover:bg-black/5 disabled:opacity-50', theme.line)}>{shippingBusy ? <><Loader2 className="size-4 animate-spin" /> Menghitung ongkir</> : <><Truck className="size-4" /> Tampilkan pilihan kurir</>}</button>
                                    )}
                                </div>
                            )}
                            {shippingError && <p className="rounded-xl bg-rose-100 px-3 py-2 text-xs font-semibold text-rose-800">{shippingError}</p>}
                            {quotes.length > 0 && (
                                <div className="space-y-2"><div className="flex items-center justify-between gap-3"><p className="text-xs font-bold uppercase tracking-wide">Pilih layanan</p><span className={cn('text-[10px] font-semibold', theme.muted)}>Termurah dipilih otomatis</span></div>{quotes.map((quote) => (
                                    <label key={quote.token} className={cn('flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3', theme.line, data.shipping_quote_token === quote.token && 'ring-2 ring-[var(--sf-primary)]')}>
                                        <span className="min-w-0"><span className="block text-sm font-extrabold">{quote.courier_name} · {quote.service_name}</span><span className={cn('block text-xs', theme.muted)}>{quote.duration || 'Estimasi dari kurir'}{quote.insurance_fee > 0 ? ` · termasuk asuransi ${formatIDR(quote.insurance_fee)}` : ''}</span></span>
                                        <span className="flex shrink-0 items-center gap-2"><strong className="text-sm">{formatIDR(quote.amount)}</strong><input type="radio" name="shipping_quote" checked={data.shipping_quote_token === quote.token} onChange={() => setData('shipping_quote_token', quote.token)} /></span>
                                    </label>
                                ))}</div>
                            )}
                            {errors.shipping_quote_token && <p className="text-xs text-rose-500">{errors.shipping_quote_token}</p>}
                        </section>
                    )}

                    <details className={cn('rounded-xl border p-4', theme.line)}>
                        <summary className="cursor-pointer text-[0.8125rem] font-medium">Kupon atau catatan <span className={cn('font-normal', theme.muted)}>(opsional)</span></summary>
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
                                {formatIDR(checkoutTotal)}
                            </span>
                        </div>
                        <p className={cn('mt-1 text-xs', theme.muted)}>Biaya layanan dihitung di langkah pembayaran.</p>
                    </div>

                    <div className="space-y-1.5">
                        <label className={cn('flex items-start gap-2.5 text-xs leading-5', theme.muted)}>
                            <input
                                type="checkbox"
                                checked={data.terms}
                                onChange={(e) => setData('terms', e.target.checked)}
                                className="mt-px size-4 shrink-0 accent-[var(--sf-primary)]"
                                required
                            />
                            Setuju dengan syarat pembelian dan kebijakan refund.
                        </label>

                        <label className={cn('flex items-start gap-2.5 text-xs leading-5', theme.muted)}>
                            <input
                                type="checkbox"
                                checked={data.marketing_consent}
                                onChange={(e) => setData('marketing_consent', e.target.checked)}
                                className="mt-px size-4 shrink-0 accent-[var(--sf-primary)]"
                            />
                            Boleh kirim info promo ke emailku.
                        </label>
                    </div>
                    {errors.terms && <p className="text-xs text-rose-500">{errors.terms}</p>}

                    <button
                        type="submit"
                        disabled={processing || isPreview}
                        className={cn(theme.btnPrimary, 'h-13 w-full px-5 text-base shadow-md')}
                    >
                        {processing
                            ? 'Memproses...'
                            : hasPhysical && !data.shipping_address.area_id
                              ? 'Lengkapi alamat pengiriman'
                              : hasPhysical && !data.shipping_quote_token
                                ? 'Tampilkan pilihan kurir'
                                : `Lanjut pilih pembayaran · ${formatIDR(checkoutTotal)}`}
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
