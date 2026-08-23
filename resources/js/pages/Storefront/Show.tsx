import { Head, router } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { CartSheet } from '@/components/storefront/CartSheet';
import { CheckoutSheet } from '@/components/storefront/CheckoutSheet';
import { StorefrontView, type StorefrontStore } from '@/components/storefront/MarketplaceStorefrontView';
import { buildStorefrontTheme } from '@/lib/storefront-theme';
import type { CartPayload, StorefrontBlock, StorefrontProduct } from '@/types';

export type { StorefrontStore };

/** Which sheet is on screen. Only one at a time — they cover the same space. */
type Sheet = { kind: 'product'; product: StorefrontProduct } | { kind: 'cart' } | { kind: 'checkout-cart' } | null;

export default function StorefrontShow({
    store,
    blocks,
    isPreview,
    cart,
}: {
    store: StorefrontStore;
    blocks: StorefrontBlock[];
    isPreview: boolean;
    cart: CartPayload | null;
}) {
    const theme = buildStorefrontTheme(store.theme);
    const [sheet, setSheet] = useState<Sheet>(null);

    const addToCart = (product: StorefrontProduct) => {
        if (isPreview) return;

        router.post(
            `/${store.username}/keranjang`,
            { product_id: product.id, quantity: 1 },
            { preserveScroll: true, preserveState: true, onSuccess: () => setSheet({ kind: 'cart' }) },
        );
    };

    const buy = (product: StorefrontProduct) => {
        if (product.type === 'EXTERNAL') {
            if (product.external_url && !isPreview) window.location.assign(product.external_url);
            return;
        }

        setSheet({ kind: 'product', product });
    };

    return (
        <div className="min-h-screen" style={theme.pageStyle}>
            <Head title={store.seo_title ?? store.name}>
                {store.seo_description && <meta name="description" content={store.seo_description} />}
                <meta property="og:title" content={store.seo_title ?? store.name} />
                {store.seo_description && <meta property="og:description" content={store.seo_description} />}
                {store.cover_url && <meta property="og:image" content={store.cover_url} />}
            </Head>

            {isPreview && (
                <div className="sticky top-0 z-50 flex items-center justify-center gap-2 bg-amber-400 px-4 py-2 text-xs font-bold text-black">
                    <Eye className="size-4" />
                    Mode preview — ini versi draft, pengunjung belum melihat halaman ini.
                </div>
            )}

            <StorefrontView
                store={store}
                blocks={blocks}
                isPreview={isPreview}
                theme={theme}
                onBuy={buy}
                onAddToCart={isPreview ? undefined : addToCart}
                cartCount={cart?.item_count ?? 0}
                onOpenCart={isPreview ? undefined : () => setSheet({ kind: 'cart' })}
            />

            {sheet?.kind === 'cart' && (
                <CartSheet
                    cart={cart}
                    storeUsername={store.username}
                    theme={theme}
                    onCheckout={() => setSheet({ kind: 'checkout-cart' })}
                    onClose={() => setSheet(null)}
                />
            )}

            {sheet?.kind === 'product' && sheet.product.type !== 'EXTERNAL' && (
                <CheckoutSheet
                    product={sheet.product}
                    storeUsername={store.username}
                    isPreview={isPreview}
                    theme={theme}
                    onClose={() => setSheet(null)}
                />
            )}

            {sheet?.kind === 'checkout-cart' && (
                <CheckoutSheet
                    cart={cart}
                    storeUsername={store.username}
                    isPreview={isPreview}
                    theme={theme}
                    onClose={() => setSheet(null)}
                />
            )}
        </div>
    );
}
