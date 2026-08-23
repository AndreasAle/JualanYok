export type MarketplaceProvider = 'Shopee' | 'Tokopedia' | 'TikTok Shop' | 'Lazada' | 'Blibli' | 'Marketplace';

export interface MarketplaceMeta {
    name: MarketplaceProvider;
    shortName: string;
    color: string;
    surface: string;
}

const PROVIDERS: Record<MarketplaceProvider, MarketplaceMeta> = {
    Shopee: { name: 'Shopee', shortName: 'SP', color: '#EE4D2D', surface: '#FFF1ED' },
    Tokopedia: { name: 'Tokopedia', shortName: 'TP', color: '#03AC0E', surface: '#ECFDF0' },
    'TikTok Shop': { name: 'TikTok Shop', shortName: 'TT', color: '#111827', surface: '#F3F4F6' },
    Lazada: { name: 'Lazada', shortName: 'LZ', color: '#4E2B84', surface: '#F3EEFF' },
    Blibli: { name: 'Blibli', shortName: 'BL', color: '#0095DA', surface: '#EAF8FF' },
    Marketplace: { name: 'Marketplace', shortName: 'MP', color: '#6D28D9', surface: '#F3EEFF' },
};

export function detectMarketplace(input?: string | null): MarketplaceMeta {
    if (!input) return PROVIDERS.Marketplace;

    try {
        const candidate = /^https?:\/\//i.test(input) ? input : `https://${input}`;
        const host = new URL(candidate).hostname.toLowerCase();

        if (host.includes('shopee') || host.includes('shp.ee')) return PROVIDERS.Shopee;
        if (host.includes('tokopedia')) return PROVIDERS.Tokopedia;
        if (host.includes('tiktok')) return PROVIDERS['TikTok Shop'];
        if (host.includes('lazada')) return PROVIDERS.Lazada;
        if (host.includes('blibli')) return PROVIDERS.Blibli;
    } catch {
        return PROVIDERS.Marketplace;
    }

    return PROVIDERS.Marketplace;
}

export function marketplaceCta(provider?: string | null): string {
    return `Beli di ${provider || 'Marketplace'}`;
}
