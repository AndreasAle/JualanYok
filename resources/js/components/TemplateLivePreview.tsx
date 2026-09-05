import { useEffect, useRef, useState } from 'react';
import { StorefrontView } from '@/components/storefront/MarketplaceStorefrontView';
import type { StorefrontBlock } from '@/types';

/** The width the storefront is laid out at before being scaled to fit. */
const CANVAS_WIDTH = 900;

export interface TemplateBlueprintBlock {
    type: string;
    title?: string | null;
    content?: Record<string, unknown>;
    style?: Record<string, unknown>;
}

/**
 * A template preview that is the template.
 *
 * The previews this replaces were miniature storefronts drawn by hand out of
 * divs and icons — a picture of a shop rather than the shop. They drifted from
 * what a template actually produced the moment either changed, and being
 * drawings, they looked drawn.
 *
 * This renders the real blueprint through the real storefront components with
 * the template's real palette, then scales the whole thing down to fit. What a
 * creator sees is what applying the template gives them, because it is the same
 * code path.
 */
export function TemplateLivePreview({
    blueprint,
    theme,
    storeName,
    tagline,
    templateSlug,
    className,
}: {
    blueprint: TemplateBlueprintBlock[];
    theme: Record<string, unknown>;
    storeName: string;
    tagline?: string | null;
    templateSlug?: string | null;
    className?: string;
}) {
    const frame = useRef<HTMLDivElement | null>(null);
    const [scale, setScale] = useState(0.3);

    /*
     * Scaled to the frame's width, and re-measured when that changes. A fixed
     * scale would be wrong on every breakpoint but the one it was chosen at.
     */
    useEffect(() => {
        const fit = () => {
            if (!frame.current) return;

            setScale(frame.current.clientWidth / CANVAS_WIDTH);
        };

        fit();

        if (!('ResizeObserver' in window) || !frame.current) {
            return;
        }

        const observer = new ResizeObserver(fit);
        observer.observe(frame.current);

        return () => observer.disconnect();
    }, []);

    const blocks: StorefrontBlock[] = blueprint.map((block, index) => ({
        id: -(index + 1),
        type: block.type,
        title: block.title ?? null,
        content: (block.content ?? {}) as Record<string, unknown>,
        style: (block.style ?? {}) as Record<string, unknown>,
        visible_mobile: true,
        visible_desktop: true,
        animation: null,
    }));

    return (
        <div ref={frame} className={className}>
            {/*
                Nothing in here is reachable: a preview that can be clicked is a
                preview someone will click, and every button inside points at a
                shop that does not exist yet.
            */}
            <div
                aria-hidden="true"
                className="pointer-events-none select-none"
                style={{
                    width: CANVAS_WIDTH,
                    transform: `scale(${scale})`,
                    transformOrigin: 'top left',
                }}
            >
                {/*
                    The frame crops it. A template preview is the top of a shop,
                    the way a screenshot would be — showing all fifteen sections
                    at thumbnail size would show none of them legibly.
                */}
                <div>
                    <StorefrontView
                        store={{
                            id: 0,
                            username: 'contoh',
                            name: storeName,
                            tagline: tagline ?? null,
                            bio: null,
                            avatar_url: null,
                            cover_url: null,
                            socials: {},
                            whatsapp: null,
                            show_branding: false,
                            public_url: '#',
                            template_slug: templateSlug ?? null,
                            theme,
                        }}
                        blocks={blocks}
                        isPreview
                        onBuy={() => {}}
                    />
                </div>
            </div>
        </div>
    );
}
