import { ArrowRight, ExternalLink, ImageIcon, Loader2, ShieldQuestion, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn, formatIDR } from '@/lib/utils';
import type { buildStorefrontTheme } from '@/lib/storefront-theme';
import type { StorefrontProduct } from '@/types';

type Theme = ReturnType<typeof buildStorefrontTheme>;

/** Long enough to read the destination, short enough not to be a toll gate. */
const HANDOFF_MS = 700;

/**
 * The moment before a buyer leaves for a marketplace.
 *
 * An affiliate tile looks exactly like a product the shop sells, and tapping it
 * used to replace the page with Shopee without a word. That is the sort of jump
 * people read as being hijacked — and the ones who meant it still lose their
 * place in the storefront.
 *
 * So it says where it is about to go, which product it is about to open, and
 * what stops being this shop's responsibility once it does: price, stock,
 * payment, delivery and refunds all belong to the marketplace from here on.
 * Whose rules apply is exactly what a buyer needs to know before they arrive,
 * not after something goes wrong.
 */
export function ExternalRedirectSheet({
    product,
    theme,
    onConfirm,
    onClose,
}: {
    product: StorefrontProduct;
    theme: Theme;
    /** Performs the navigation; kept outside so the click stays a user gesture. */
    onConfirm: () => void;
    onClose: () => void;
}) {
    const [leaving, setLeaving] = useState(false);
    const provider = product.external_provider || 'marketplace';

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && !leaving && onClose();

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [leaving, onClose]);

    const go = () => {
        setLeaving(true);

        // A beat of "opening Shopee" before the page goes: a tap that changes
        // nothing for half a second reads as a tap that failed, and gets
        // repeated.
        window.setTimeout(onConfirm, HANDOFF_MS);
    };

    return (
        <div
            className="fixed inset-0 z-[85] grid place-items-end sm:place-items-center"
            role="dialog"
            aria-modal="true"
            aria-label={`Beralih ke ${provider}`}
        >
            <button
                type="button"
                className="absolute inset-0 bg-black/50 backdrop-blur-[2px] animate-[jy-fade_.2s_ease-out]"
                onClick={() => !leaving && onClose()}
                aria-label="Batal"
                tabIndex={-1}
            />

            <div className="relative w-full animate-rise rounded-t-2xl bg-[var(--sf-card)] p-5 shadow-2xl sm:max-w-sm sm:rounded-2xl">
                {!leaving && (
                    <button
                        type="button"
                        onClick={onClose}
                        className={cn('absolute right-3 top-3 grid size-8 place-items-center rounded-lg', theme.muted)}
                        aria-label="Tutup"
                    >
                        <X className="size-4" />
                    </button>
                )}

                <span className="grid size-11 place-items-center rounded-full bg-[color-mix(in_oklab,var(--sf-primary)_12%,transparent)] text-[var(--sf-primary)]">
                    <ShieldQuestion className="size-5" />
                </span>

                <h2 className="mt-3 text-[1.0625rem] font-semibold leading-snug">
                    Kamu akan beralih ke {provider}
                </h2>
                <p className={cn('mt-1 text-[0.8125rem] leading-6', theme.muted)}>
                    Halaman produk ini dibuka di {provider}. Pembelian, pembayaran, dan pengirimannya
                    diurus di sana.
                </p>

                {/* Which product, spelled out. "You are leaving" without naming
                    the destination item is a warning nobody can act on. */}
                <div className={cn('mt-4 flex gap-3 rounded-lg border p-3', theme.line)}>
                    <span className="size-14 shrink-0 overflow-hidden rounded bg-[color-mix(in_oklab,var(--sf-primary)_8%,transparent)]">
                        {product.thumbnail_url ? (
                            <img src={product.thumbnail_url} alt="" className="size-full object-cover" />
                        ) : (
                            <span className="grid size-full place-items-center">
                                <ImageIcon className="size-5 opacity-40" />
                            </span>
                        )}
                    </span>

                    <span className="min-w-0 flex-1">
                        <span className="line-clamp-2 text-[0.8125rem] font-medium leading-5">{product.name}</span>
                        <span className={cn('mt-1 block text-xs', theme.muted)}>
                            {product.price > 0 ? formatIDR(product.price) : 'Harga mengikuti marketplace'}
                        </span>
                    </span>
                </div>

                <p className={cn('mt-3 text-[0.6875rem] leading-5', theme.muted)}>
                    Harga dan stok terbaru, serta kebijakan refund, mengikuti {provider} — bukan toko ini.
                </p>

                <div className="mt-4 flex gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={leaving}
                        className={cn('h-11 flex-1 rounded border text-sm font-medium disabled:opacity-40', theme.line)}
                    >
                        Batal
                    </button>

                    <button
                        type="button"
                        onClick={go}
                        disabled={leaving}
                        className={cn(theme.btnPrimary, 'h-11 flex-[1.4] rounded text-sm')}
                    >
                        {leaving ? (
                            <>
                                <Loader2 className="size-4 animate-spin" /> Membuka {provider}…
                            </>
                        ) : (
                            <>
                                <ExternalLink className="size-4" /> Lanjut <ArrowRight className="size-4" />
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
