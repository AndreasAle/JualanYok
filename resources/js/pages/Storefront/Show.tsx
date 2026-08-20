import { Head } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { CheckoutSheet } from '@/components/storefront/CheckoutSheet';
import { StorefrontView, type StorefrontStore } from '@/components/storefront/MarketplaceStorefrontView';
import { buildStorefrontTheme } from '@/lib/storefront-theme';
import type { StorefrontBlock, StorefrontProduct } from '@/types';

export type { StorefrontStore };

export default function StorefrontShow({
    store,
    blocks,
    isPreview,
}: {
    store: StorefrontStore;
    blocks: StorefrontBlock[];
    isPreview: boolean;
}) {
    const theme = buildStorefrontTheme(store.theme);
    const [checkoutProduct, setCheckoutProduct] = useState<StorefrontProduct | null>(null);

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
                onBuy={setCheckoutProduct}
            />

            {checkoutProduct && (
                <CheckoutSheet
                    product={checkoutProduct}
                    storeUsername={store.username}
                    isPreview={isPreview}
                    theme={theme}
                    onClose={() => setCheckoutProduct(null)}
                />
            )}
        </div>
    );
}
