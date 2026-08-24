import { BadgeCheck, Check, Share2, Store } from 'lucide-react';
import { useState } from 'react';
import { BlockRenderer } from '@/components/storefront/BlockRenderer';
import { buildStorefrontTheme, type StorefrontTheme } from '@/lib/storefront-theme';
import { cn } from '@/lib/utils';
import type { StorefrontBlock, StorefrontProduct, StoreTheme } from '@/types';

export interface StorefrontStore {
    id: number;
    username: string;
    name: string;
    tagline: string | null;
    bio: string | null;
    avatar_url: string | null;
    cover_url: string | null;
    socials: Record<string, string>;
    whatsapp: string | null;
    seo_title?: string;
    seo_description?: string | null;
    show_branding: boolean;
    public_url: string;
    template_slug?: string | null;
    theme: Partial<StoreTheme>;
}

/**
 * The complete storefront body — header, blocks and footer.
 *
 * The public page and the builder preview both render this one component, so
 * the preview is genuinely WYSIWYG. Any header or spacing change lands in both
 * places at once; there is no second implementation to drift out of sync.
 */
export function StorefrontView({
    store,
    blocks,
    isPreview,
    theme,
    onBuy,
}: {
    store: StorefrontStore;
    blocks: StorefrontBlock[];
    isPreview: boolean;
    /** Pass a prebuilt theme to keep it in sync with a live editor. */
    theme?: StorefrontTheme;
    onBuy: (product: StorefrontProduct) => void;
}) {
    const t = theme ?? buildStorefrontTheme(store.theme);

    return (
        <div className="min-h-full" style={t.pageStyle}>
            <StoreHeader store={store} theme={t} isPreview={isPreview} />

            <main className="@container mx-auto max-w-6xl px-4 pb-20 sm:px-6">
                {blocks.length === 0 ? (
                    <div className={cn(t.card, 'mt-8 p-12 text-center')}>
                        <Store className="mx-auto size-10 opacity-30" />
                        <p className="mt-4 text-lg font-bold">Tokonya masih kosong</p>
                        <p className={cn('mx-auto mt-1.5 max-w-sm text-sm', t.muted)}>
                            {isPreview
                                ? 'Tambah block dari editor buat mulai mengisi halaman ini.'
                                : 'Pemilik toko lagi menyiapkan isinya. Mampir lagi nanti ya.'}
                        </p>
                    </div>
                ) : (
                    <div className={t.sectionSpacing}>
                        {blocks.map((block) => (
                            <BlockRenderer
                                key={block.id}
                                block={block}
                                ctx={{
                                    storeUsername: store.username,
                                    theme: t,
                                    productLayout: store.theme.product_layout ?? 'grid',
                                    isPreview,
                                    onBuy,
                                }}
                            />
                        ))}
                    </div>
                )}

                {store.show_branding && (
                    <footer className="mt-16 text-center">
                        <a
                            href="/"
                            className={cn(
                                t.card,
                                'inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold transition-transform hover:-translate-y-0.5',
                            )}
                        >
                            Dibuat dengan
                            <span className="font-black text-[var(--sf-primary)]">JualanYok</span>
                        </a>
                    </footer>
                )}
            </main>
        </div>
    );
}

/* -------------------------------------------------------------------------- */

function StoreHeader({
    store,
    theme,
    isPreview,
}: {
    store: StorefrontStore;
    theme: StorefrontTheme;
    isPreview: boolean;
}) {
    const [copied, setCopied] = useState(false);

    const share = async () => {
        if (isPreview) return;

        const url = store.public_url;

        if (navigator.share) {
            try {
                await navigator.share({ title: store.name, url });
                return;
            } catch {
                // Dismissed — fall through to copying instead.
            }
        }

        await navigator.clipboard.writeText(url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <header>
            {/* Brand band — the only place the store's colours run full-bleed. */}
            <div
                className="relative h-28 w-full sm:h-40"
                style={
                    store.cover_url
                        ? { background: `center / cover no-repeat url(${store.cover_url})` }
                        : theme.coverStyle
                }
            >
                {!store.cover_url && (
                    <div
                        className="absolute inset-0 opacity-20"
                        style={{
                            backgroundImage: 'radial-gradient(circle at 20% 25%, #fff 1.5px, transparent 1.5px)',
                            backgroundSize: '28px 28px',
                        }}
                        aria-hidden="true"
                    />
                )}
            </div>

            <div className="@container mx-auto max-w-6xl px-4 sm:px-6">
                <div className={cn(theme.card, 'relative -mt-8 p-5 sm:-mt-10 sm:p-6')}>
                    <div className="flex flex-col gap-4 @2xl:flex-row @2xl:items-start @2xl:gap-6">
                        <div className="size-20 shrink-0 overflow-hidden rounded-2xl shadow-md sm:size-24">
                            {store.avatar_url ? (
                                <img src={store.avatar_url} alt="" className="size-full object-cover" />
                            ) : (
                                <span
                                    className="grid size-full place-items-center text-3xl font-black"
                                    style={{ background: 'var(--sf-primary)', color: 'var(--sf-on-primary)' }}
                                >
                                    {store.name[0]?.toUpperCase()}
                                </span>
                            )}
                        </div>

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{store.name}</h1>
                                <BadgeCheck
                                    className="size-5 text-[var(--sf-primary)]"
                                    aria-label="Toko terverifikasi"
                                />
                            </div>

                            <p className={cn('mt-0.5 text-sm', theme.muted)}>@{store.username}</p>

                            {store.tagline && <p className="mt-2 text-[15px] font-medium">{store.tagline}</p>}

                            {store.bio && (
                                <p className={cn('mt-2 max-w-2xl text-sm leading-relaxed', theme.muted)}>
                                    {store.bio}
                                </p>
                            )}
                        </div>

                        <div className="flex shrink-0 gap-2">
                            <button
                                type="button"
                                onClick={share}
                                className={cn(theme.btnPrimary, 'h-11 px-5 text-sm')}
                            >
                                {copied ? <Check className="size-4" /> : <Share2 className="size-4" />}
                                {copied ? 'Tersalin!' : 'Bagikan Toko'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div className="h-6 sm:h-8" />
        </header>
    );
}
