import { cn } from '@/lib/utils';

/**
 * Miniature wireframe of a storefront template.
 *
 * Drawn from the template's real block order, so two templates only look alike
 * if they genuinely are alike. A colour swatch alone cannot tell a creator that
 * one template leads with products while another leads with a portfolio.
 */
export function TemplateThumbnail({
    blocks,
    primary,
    accent,
    className,
}: {
    blocks: string[];
    primary: string;
    accent: string;
    className?: string;
}) {
    return (
        <div
            className={cn('flex flex-col overflow-hidden bg-white', className)}
            style={{ ['--tp' as string]: primary, ['--ta' as string]: accent }}
            aria-hidden="true"
        >
            {/* Cover band + profile card, present on every storefront */}
            <div className="h-7 w-full shrink-0" style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }} />

            <div className="-mt-2.5 px-2">
                <div className="rounded-md border border-black/5 bg-white p-1.5 shadow-sm">
                    <div className="flex items-center gap-1.5">
                        <div className="size-4 shrink-0 rounded" style={{ background: primary }} />
                        <div className="min-w-0 flex-1 space-y-0.5">
                            <Bar w="60%" h={3} tone="strong" />
                            <Bar w="40%" h={2} />
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex-1 space-y-1.5 overflow-hidden p-2">
                {blocks.slice(0, 9).map((type, i) => (
                    <BlockShape key={i} type={type} primary={primary} accent={accent} />
                ))}
            </div>
        </div>
    );
}

/* -------------------------------------------------------------------------- */

function Bar({
    w = '100%',
    h = 2,
    tone = 'muted',
}: {
    w?: string;
    h?: number;
    tone?: 'muted' | 'strong';
}) {
    return (
        <div
            className={cn('rounded-full', tone === 'strong' ? 'bg-slate-800' : 'bg-slate-300')}
            style={{ width: w, height: h }}
        />
    );
}

function Tile({ primary, accent, h = 14 }: { primary: string; accent: string; h?: number }) {
    return (
        <div className="overflow-hidden rounded" style={{ height: h }}>
            <div className="h-2/3 w-full" style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }} />
            <div className="flex h-1/3 flex-col justify-center gap-px bg-slate-100 px-0.5">
                <Bar w="70%" h={1.5} />
            </div>
        </div>
    );
}

function Card({ children }: { children: React.ReactNode }) {
    return <div className="rounded border border-black/5 bg-slate-50 p-1">{children}</div>;
}

/** One representative shape per block type. */
function BlockShape({ type, primary, accent }: { type: string; primary: string; accent: string }) {
    switch (type) {
        case 'HEADING':
            return (
                <div className="space-y-0.5 py-0.5">
                    <Bar w="80%" h={4} tone="strong" />
                </div>
            );

        case 'TEXT':
            return (
                <div className="space-y-0.5">
                    <Bar w="100%" />
                    <Bar w="92%" />
                    <Bar w="68%" />
                </div>
            );

        case 'SOCIAL_LINKS':
            return (
                <Card>
                    <div className="flex justify-center gap-1">
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="size-2.5 rounded-full bg-slate-300" />
                        ))}
                    </div>
                </Card>
            );

        case 'FEATURED_PRODUCTS':
        case 'PRODUCT_COLLECTION':
            return (
                <div className="grid grid-cols-2 gap-1">
                    <Tile primary={primary} accent={accent} />
                    <Tile primary={primary} accent={accent} />
                </div>
            );

        case 'PRODUCT':
        case 'AFFILIATE_PRODUCT':
            return (
                <div className="flex gap-1 rounded border border-black/5 p-1">
                    <div className="size-6 shrink-0 rounded" style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }} />
                    <div className="flex-1 space-y-0.5 pt-0.5">
                        <Bar w="80%" h={2.5} tone="strong" />
                        <Bar w="45%" />
                    </div>
                </div>
            );

        case 'PROMO_BANNER':
            return (
                <div
                    className="flex h-8 flex-col items-center justify-center gap-1 rounded"
                    style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }}
                >
                    <div className="h-1.5 w-14 rounded-full bg-white/85" />
                    <div className="h-2 w-8 rounded-sm border border-dashed border-white/70" />
                </div>
            );

        case 'COUNTDOWN':
            return (
                <div
                    className="flex h-7 items-center justify-center gap-1 rounded"
                    style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }}
                >
                    {[0, 1, 2, 3].map((i) => (
                        <div key={i} className="size-3.5 rounded-sm bg-white/30" />
                    ))}
                </div>
            );

        case 'TESTIMONIAL':
            return (
                <Card>
                    <div className="space-y-0.5">
                        <Bar w="88%" />
                        <Bar w="64%" />
                        <div className="flex items-center gap-1 pt-0.5">
                            <div className="size-2.5 rounded-full" style={{ background: primary }} />
                            <Bar w="34%" h={2} />
                        </div>
                    </div>
                </Card>
            );

        case 'FAQ':
            return (
                <div className="divide-y divide-black/5 overflow-hidden rounded border border-black/5">
                    {[0, 1].map((i) => (
                        <div key={i} className="flex items-center justify-between gap-1 p-1">
                            <Bar w="62%" h={2} />
                            <div className="size-1.5 rounded-full bg-slate-300" />
                        </div>
                    ))}
                </div>
            );

        case 'LEAD_FORM':
            return (
                <Card>
                    <div className="space-y-1">
                        <Bar w="52%" h={2.5} tone="strong" />
                        <div className="h-2 rounded-sm bg-white ring-1 ring-black/5" />
                        <div className="h-2 rounded-sm bg-white ring-1 ring-black/5" />
                        <div className="h-2.5 rounded-sm" style={{ background: primary }} />
                    </div>
                </Card>
            );

        case 'WHATSAPP_CTA':
            return <div className="h-3.5 rounded-full bg-[#25D366]" />;

        case 'LINK_BUTTON':
            return <div className="h-3.5 rounded-full" style={{ background: primary }} />;

        case 'GALLERY':
            return (
                <div className="grid grid-cols-3 gap-1">
                    {[0, 1, 2].map((i) => (
                        <div key={i} className="aspect-square rounded bg-slate-200" />
                    ))}
                </div>
            );

        case 'VIDEO':
        case 'EMBED':
            return (
                <div className="grid aspect-video place-items-center rounded bg-slate-800">
                    <div className="size-0 border-y-[4px] border-l-[7px] border-y-transparent border-l-white" />
                </div>
            );

        case 'IMAGE':
            return <div className="aspect-[16/7] rounded bg-slate-200" />;

        case 'ARTICLE':
            return (
                <Card>
                    <div className="space-y-0.5">
                        <Bar w="72%" h={2.5} tone="strong" />
                        <Bar w="94%" />
                        <Bar w="40%" h={1.5} />
                    </div>
                </Card>
            );

        case 'DIVIDER':
            return <div className="h-px bg-slate-200" />;

        case 'SPACER':
            return <div className="h-2" />;

        default:
            return <Bar w="100%" h={3} />;
    }
}
