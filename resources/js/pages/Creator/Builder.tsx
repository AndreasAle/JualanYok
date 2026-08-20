import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2, ChevronDown, ChevronUp, Copy, ExternalLink, Eye, GripVertical, Loader2, Monitor, Plus,
    LayoutTemplate, PartyPopper, Rocket, Save, Smartphone, Tablet, Trash2, X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { StorefrontView } from '@/components/storefront/MarketplaceStorefrontView';
import { GalleryPicker, MediaPicker } from '@/components/media-picker';
import { TemplateThumbnail } from '@/components/template-thumbnail';
import { ConfirmButton } from '@/components/shared';
import {
    Alert, Badge, Button, Card, EmptyState, Field, Input, Select, Switch, Textarea,
} from '@/components/ui';
import { EMBED_PROVIDERS, toEmbedUrl } from '@/lib/embed';
import { cn } from '@/lib/utils';
import type { StorefrontBlock } from '@/types';

interface BuilderBlock {
    id: number;
    type: string;
    type_label: string;
    title: string | null;
    content: Record<string, any>;
    style: Record<string, any>;
    position: number;
    is_published: boolean;
    visible_mobile: boolean;
    visible_desktop: boolean;
    starts_at: string | null;
    ends_at: string | null;
    animation: string | null;
    has_unpublished_changes: boolean;
    impressions: number;
    clicks: number;
}

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

export default function Builder({
    store, theme, blocks: initialBlocks, blockTypes, products, templates, limits,
}: {
    store: any;
    theme: any;
    blocks: BuilderBlock[];
    blockTypes: Record<string, { value: string; label: string; group: string }[]>;
    products: { id: number; name: string; price: number; thumbnail_url: string | null; type: string }[];
    templates: any[];
    limits: { blocks: number | null; blocks_used: number; can_remove_branding: boolean; can_use_premium_templates: boolean };
}) {
    const [blocks, setBlocks] = useState(initialBlocks);
    const [activeId, setActiveId] = useState<number | null>(initialBlocks[0]?.id ?? null);
    const [device, setDevice] = useState<'mobile' | 'tablet' | 'desktop'>('mobile');
    const [pickerOpen, setPickerOpen] = useState(false);
    const [templatePickerOpen, setTemplatePickerOpen] = useState(false);
    const [mobileTab, setMobileTab] = useState<'blocks' | 'edit' | 'preview'>('blocks');
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const pageUrl = usePage().url;
    const firstProductReady = pageUrl.includes('first_product=1');
    const justPublished = pageUrl.includes('published=1');

    useEffect(() => setBlocks(initialBlocks), [initialBlocks]);

    const active = blocks.find((b) => b.id === activeId) ?? null;
    const activeTemplate = templates.find((t: any) => t.id === store.storefront_template_id) ?? null;

    const atLimit = limits.blocks !== null && limits.blocks_used >= limits.blocks;

    /* ----------------------------------------------------------------- */

    const patchLocal = (id: number, patch: Partial<BuilderBlock>) => {
        setBlocks((current) => current.map((b) => (b.id === id ? { ...b, ...patch } : b)));
    };

    const move = (id: number, direction: -1 | 1) => {
        const index = blocks.findIndex((b) => b.id === id);
        const target = index + direction;

        if (index < 0 || target < 0 || target >= blocks.length) return;

        const reordered = [...blocks];
        [reordered[index], reordered[target]] = [reordered[target], reordered[index]];

        setBlocks(reordered);

        router.post(
            '/dashboard/blocks/reorder',
            { ids: reordered.map((b) => b.id) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const addBlock = (type: string) => {
        setPickerOpen(false);

        router.post(
            '/dashboard/blocks',
            { type, content: defaultContent(type) },
            {
                preserveScroll: true,
                onSuccess: () => setMobileTab('edit'),
            },
        );
    };

    const removeBlock = (id: number) => {
        router.delete(`/dashboard/blocks/${id}`, {
            preserveScroll: true,
            onSuccess: () => setActiveId(null),
        });
    };

    /* ----------------------------------------------------------------- */

    const previewBlocks: StorefrontBlock[] = useMemo(
        () =>
            blocks
                .filter((b) => b.is_published)
                .map((b) => ({
                    id: b.id,
                    type: b.type,
                    title: b.title,
                    content: hydrate(b, products),
                    style: b.style ?? {},
                    visible_mobile: b.visible_mobile,
                    visible_desktop: b.visible_desktop,
                    animation: b.animation,
                })),
        [blocks, products],
    );

    return (
        <DashboardLayout title="Atur Tampilan" area="creator">
            <Head title="Atur Tampilan Toko" />

            {justPublished && (
                <section className="mb-5 overflow-hidden rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5 shadow-[0_18px_50px_rgba(16,185,129,.12)] dark:border-emerald-500/20 dark:bg-emerald-500/10 sm:p-6">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                        <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-emerald-500 text-white shadow-lg"><PartyPopper className="size-7" /></span>
                        <div className="min-w-0 flex-1"><p className="text-xs font-black uppercase tracking-[.16em] text-emerald-700 dark:text-emerald-300">Toko berhasil dipublikasikan</p><h2 className="mt-1 text-xl font-black tracking-tight">Tokomu sekarang bisa dikunjungi pembeli.</h2><p className="mt-1 text-sm text-muted">Buka toko untuk pemeriksaan terakhir, lalu bagikan alamatnya ke audiensmu.</p></div>
                        <div className="flex shrink-0 gap-2"><Button variant="outline" onClick={() => navigator.clipboard.writeText(store.public_url)}><Copy className="size-4" /> Salin link</Button><a href={store.public_url} target="_blank" rel="noopener noreferrer" className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] bg-emerald-600 px-4 text-sm font-extrabold text-white"><ExternalLink className="size-4" /> Buka toko</a></div>
                    </div>
                </section>
            )}

            {firstProductReady && !justPublished && (
                <section className="mb-5 rounded-[1.4rem] border border-violet-200 bg-violet-50 p-4 dark:border-violet-500/20 dark:bg-violet-500/10 sm:p-5">
                    <div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-600 text-white"><CheckCircle2 className="size-5" /></span><div><p className="font-extrabold">Produk pertama sudah masuk ke pratinjau</p><p className="mt-1 text-sm text-muted">Langkah 3 dari 3 — cek tampilan mobile di kanan, lalu tekan <strong>Publikasikan toko</strong>.</p></div></div>
                </section>
            )}

            {/* Toolbar */}
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight">Atur Tampilan</h1>
                    <p className="text-sm text-muted">
                        Susun block tokomu. Perubahan tersimpan otomatis sebagai draft.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <SaveIndicator state={saveState} />

                    <a
                        href={`/${store.username}/preview`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] border border-line px-4 text-sm font-semibold hover:bg-surface-2"
                    >
                        <Eye className="size-4" />
                        Buka pratinjau
                    </a>

                    {store.is_published ? (
                        <Button
                            variant="gradient"
                            onClick={() => router.post('/dashboard/toko/publish', {}, { preserveScroll: true })}
                        >
                            <Rocket className="size-4" />
                            Publikasikan Perubahan
                        </Button>
                    ) : (
                        <Button
                            variant="gradient"
                            onClick={() => router.post('/dashboard/toko/publish', {}, { preserveScroll: true })}
                        >
                            <Rocket className="size-4" />
                            Publikasikan Toko
                        </Button>
                    )}
                </div>
            </div>

            {atLimit && (
                <div className="mb-4">
                    <Alert tone="warning" title="Block sudah mentok">
                        Paket kamu maksimal {limits.blocks} block.{' '}
                        <a href="/dashboard/langganan" className="font-bold underline">
                            Upgrade
                        </a>{' '}
                        buat nambah lagi.
                    </Alert>
                </div>
            )}

            {/* Mobile tabs */}
            <div className="mb-4 grid grid-cols-3 gap-1 rounded-[var(--radius-field)] bg-surface-2 p-1 lg:hidden">
                {(['blocks', 'edit', 'preview'] as const).map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        onClick={() => setMobileTab(tab)}
                        className={cn(
                            'rounded-[calc(var(--radius-field)-2px)] py-2 text-sm font-semibold transition-colors',
                            mobileTab === tab ? 'bg-surface shadow-soft' : 'text-muted',
                        )}
                    >
                        {tab === 'blocks' ? 'Block' : tab === 'edit' ? 'Atur' : 'Preview'}
                    </button>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-[260px_1fr_380px]">
                {/* Block list */}
                <div className={cn(mobileTab === 'blocks' ? 'block' : 'hidden', 'lg:block')}>
                    <Card className="p-3">
                        <div className="mb-2 flex items-center justify-between px-1">
                            <p className="text-sm font-bold">
                                Block{' '}
                                <span className="text-muted">
                                    ({limits.blocks_used}
                                    {limits.blocks !== null && `/${limits.blocks}`})
                                </span>
                            </p>
                            <Button
                                variant="gradient"
                                size="sm"
                                onClick={() => setPickerOpen(true)}
                                disabled={atLimit}
                            >
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        </div>

                        {blocks.length === 0 ? (
                            <EmptyState
                                title="Belum ada block"
                                description="Mulai dengan menambahkan block pertama."
                                action={
                                    <Button variant="gradient" size="sm" onClick={() => setPickerOpen(true)}>
                                        <Plus className="size-4" />
                                        Tambah Block
                                    </Button>
                                }
                            />
                        ) : (
                            <ul className="space-y-1">
                                {blocks.map((block, i) => (
                                    <li key={block.id}>
                                        <div
                                            className={cn(
                                                'flex items-center gap-1 rounded-[var(--radius-field)] p-2 transition-colors',
                                                activeId === block.id ? 'bg-brand-100 dark:bg-brand-900/40' : 'hover:bg-surface-2',
                                            )}
                                        >
                                            <span className="cursor-grab text-muted" aria-hidden="true">
                                                <GripVertical className="size-4" />
                                            </span>

                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setActiveId(block.id);
                                                    setMobileTab('edit');
                                                }}
                                                className="min-w-0 flex-1 py-0.5 text-left"
                                            >
                                                <span className="block truncate text-sm font-semibold">
                                                    {block.title || block.type_label}
                                                </span>

                                                <span className="mt-0.5 flex flex-wrap items-center gap-1">
                                                    {block.title && (
                                                        <span className="text-[11px] text-muted">
                                                            {block.type_label}
                                                        </span>
                                                    )}
                                                    {!block.is_published && (
                                                        <span className="rounded bg-surface-2 px-1.5 py-px text-[10px] font-bold text-muted">
                                                            Disembunyikan
                                                        </span>
                                                    )}
                                                    {block.has_unpublished_changes && (
                                                        <span className="rounded bg-amber-100 px-1.5 py-px text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                            Draft
                                                        </span>
                                                    )}
                                                </span>
                                            </button>

                                            <span className="flex shrink-0 flex-col">
                                                <button
                                                    type="button"
                                                    onClick={() => move(block.id, -1)}
                                                    disabled={i === 0}
                                                    aria-label="Naikkan"
                                                    className="text-muted hover:text-fg disabled:opacity-30"
                                                >
                                                    <ChevronUp className="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => move(block.id, 1)}
                                                    disabled={i === blocks.length - 1}
                                                    aria-label="Turunkan"
                                                    className="text-muted hover:text-fg disabled:opacity-30"
                                                >
                                                    <ChevronDown className="size-3.5" />
                                                </button>
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {/* Templates */}
                    <Card className="mt-3 p-4">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-sm font-bold">Template</p>
                                <p className="mt-0.5 truncate text-xs text-muted">
                                    {activeTemplate ? activeTemplate.name : 'Belum pakai template'}
                                </p>
                            </div>
                            {activeTemplate?.is_premium && <Badge tone="brand">Pro</Badge>}
                        </div>

                        {activeTemplate && (
                            <div className="mt-3 overflow-hidden rounded-[var(--radius-field)] border border-line">
                                <TemplateThumbnail
                                    blocks={activeTemplate.blocks ?? []}
                                    primary={activeTemplate.theme?.primary_color ?? '#7C3AED'}
                                    accent={activeTemplate.theme?.accent_color ?? '#FB7185'}
                                    className="h-44"
                                />
                            </div>
                        )}

                        <Button
                            variant="outline"
                            block
                            className="mt-3"
                            onClick={() => setTemplatePickerOpen(true)}
                        >
                            <LayoutTemplate className="size-4" />
                            Ganti Template
                        </Button>

                        <p className="mt-2 text-xs text-muted">
                            Mengganti template akan menyusun ulang semua block. Produkmu tetap aman.
                        </p>
                    </Card>
                </div>

                {/* Editor */}
                <div className={cn(mobileTab === 'edit' ? 'block' : 'hidden', 'lg:block')}>
                    {active ? (
                        <BlockEditor
                            key={active.id}
                            block={active}
                            products={products}
                            onLocalChange={(patch) => patchLocal(active.id, patch)}
                            onSaveState={setSaveState}
                            onDelete={() => removeBlock(active.id)}
                        />
                    ) : (
                        <Card>
                            <EmptyState
                                title="Pilih block dulu"
                                description="Klik salah satu block di sebelah kiri buat mengubah isinya."
                            />
                        </Card>
                    )}
                </div>

                {/* Preview */}
                <div className={cn(mobileTab === 'preview' ? 'block' : 'hidden', 'lg:block')}>
                    <div className="lg:sticky lg:top-24">
                        <div className="mb-3 flex items-center justify-center gap-1 rounded-[var(--radius-field)] bg-surface-2 p-1">
                            {([
                                { key: 'mobile', icon: <Smartphone className="size-4" />, label: 'Mobile' },
                                { key: 'tablet', icon: <Tablet className="size-4" />, label: 'Tablet' },
                                { key: 'desktop', icon: <Monitor className="size-4" />, label: 'Desktop' },
                            ] as const).map((option) => (
                                <button
                                    key={option.key}
                                    type="button"
                                    onClick={() => setDevice(option.key)}
                                    aria-pressed={device === option.key}
                                    className={cn(
                                        'flex flex-1 items-center justify-center gap-1.5 rounded-[calc(var(--radius-field)-2px)] py-2 text-xs font-semibold transition-colors',
                                        device === option.key
                                            ? 'bg-surface text-fg shadow-soft'
                                            : 'text-muted hover:text-fg',
                                    )}
                                >
                                    {option.icon}
                                    {option.label}
                                </button>
                            ))}
                        </div>

                        <DevicePreview device={device}>
                            <LivePreview store={store} theme={theme} blocks={previewBlocks} />
                        </DevicePreview>

                        <p className="mt-2 text-center text-xs text-muted">
                            Lebar asli {DEVICE_WIDTHS[device]}px · diperkecil agar muat di panel
                        </p>
                    </div>
                </div>
            </div>

            {pickerOpen && (
                <BlockPicker groups={blockTypes} onPick={addBlock} onClose={() => setPickerOpen(false)} />
            )}

            {templatePickerOpen && (
                <TemplatePicker
                    templates={templates}
                    activeId={store.storefront_template_id}
                    canUsePremium={limits.can_use_premium_templates}
                    onClose={() => setTemplatePickerOpen(false)}
                />
            )}
        </DashboardLayout>
    );
}

/* -------------------------------------------------------------------------- */

const DEVICE_WIDTHS = { mobile: 375, tablet: 768, desktop: 1280 } as const;
const DEVICE_HEIGHTS = { mobile: 720, tablet: 780, desktop: 800 } as const;

/**
 * Renders the storefront at the device's real pixel width, then scales the
 * whole frame down to fit the panel.
 *
 * Rendering at true width matters: the storefront sizes itself with container
 * queries, so a 375px-wide frame produces the same layout a phone would. A
 * frame that was merely "narrow" on a desktop viewport used to render the
 * desktop grid squashed, which is why preview tiles looked clipped.
 */
function DevicePreview({ device, children }: { device: keyof typeof DEVICE_WIDTHS; children: ReactNode }) {
    const holder = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(1);

    const width = DEVICE_WIDTHS[device];
    const height = DEVICE_HEIGHTS[device];

    useEffect(() => {
        const el = holder.current;
        if (!el) return;

        const fit = () => setScale(Math.min(1, el.clientWidth / width));

        fit();

        const observer = new ResizeObserver(fit);
        observer.observe(el);

        return () => observer.disconnect();
    }, [width]);

    return (
        <div ref={holder} className="w-full">
            <div
                className="mx-auto overflow-hidden rounded-[28px] border-8 border-[var(--fg)] bg-surface shadow-lift"
                style={{ width: width * scale, height: height * scale }}
            >
                <div
                    className="origin-top-left overflow-y-auto"
                    style={{ width, height, transform: `scale(${scale})` }}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}

function SaveIndicator({ state }: { state: SaveState }) {
    if (state === 'idle') return null;

    const map = {
        saving: { icon: <Loader2 className="size-3.5 animate-spin" />, text: 'Menyimpan…', tone: 'text-muted' },
        saved: { icon: <Save className="size-3.5" />, text: 'Tersimpan', tone: 'text-[var(--success)]' },
        error: { icon: <X className="size-3.5" />, text: 'Gagal menyimpan', tone: 'text-[var(--danger)]' },
    } as const;

    const config = map[state];

    return (
        <span className={cn('flex items-center gap-1.5 text-xs font-semibold', config.tone)} role="status">
            {config.icon}
            {config.text}
        </span>
    );
}

/**
 * The preview renders the very same component the public storefront uses, so
 * the header, avatar, cover and blocks cannot drift apart. Only interactivity
 * is disabled via `isPreview`.
 */
function LivePreview({ store, theme, blocks }: { store: any; theme: any; blocks: StorefrontBlock[] }) {
    return (
        <StorefrontView
            store={{
                id: store.id,
                username: store.username,
                name: store.name,
                tagline: store.tagline,
                bio: store.bio,
                avatar_url: store.avatar_url,
                cover_url: store.cover_url,
                socials: store.socials ?? {},
                whatsapp: store.whatsapp,
                show_branding: store.show_branding,
                public_url: store.public_url,
                template_slug: store.template_slug,
                theme: theme ?? {},
            }}
            blocks={blocks}
            isPreview
            onBuy={() => {}}
        />
    );
}

/**
 * Full template chooser. Each card renders the template's real block order as a
 * miniature, so the creator compares structure rather than guessing from a
 * colour swatch.
 */
function TemplatePicker({
    templates,
    activeId,
    canUsePremium,
    onClose,
}: {
    templates: any[];
    activeId: number | null;
    canUsePremium: boolean;
    onClose: () => void;
}) {
    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="template-picker-title"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="max-h-[88vh] w-full max-w-5xl animate-rise overflow-y-auto">
                <div className="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-line bg-surface p-5">
                    <div>
                        <h2 id="template-picker-title" className="text-lg font-bold">
                            Pilih template
                        </h2>
                        <p className="mt-0.5 text-sm text-muted">
                            Tiap template punya susunan block yang beda, bukan cuma beda warna.
                        </p>
                    </div>

                    <Button variant="ghost" size="icon" onClick={onClose} aria-label="Tutup">
                        <X className="size-5" />
                    </Button>
                </div>

                <div className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.map((template) => {
                        const locked = template.is_premium && !canUsePremium;
                        const isActive = template.id === activeId;

                        return (
                            <div
                                key={template.slug}
                                className={cn(
                                    'flex flex-col overflow-hidden rounded-[var(--radius-card)] border transition-all',
                                    isActive
                                        ? 'border-[var(--primary)] ring-2 ring-[var(--primary)]'
                                        : 'border-line hover:shadow-lift',
                                )}
                            >
                                <div className="relative bg-surface-2 p-3">
                                    <TemplateThumbnail
                                        blocks={template.blocks ?? []}
                                        primary={template.theme?.primary_color ?? '#7C3AED'}
                                        accent={template.theme?.accent_color ?? '#FB7185'}
                                        className="mx-auto h-64 w-full max-w-48 rounded-lg shadow-soft"
                                    />

                                    {locked && (
                                        <div className="absolute inset-0 grid place-items-center bg-black/45">
                                            <span className="rounded-full bg-white px-3 py-1.5 text-xs font-bold text-slate-900">
                                                Tersedia di paket Pro
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-1 flex-col p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <h3 className="font-bold">{template.name}</h3>
                                        {template.is_premium && <Badge tone="brand">Pro</Badge>}
                                    </div>

                                    <p className="mt-1 text-sm text-muted">{template.tagline}</p>

                                    {template.description && (
                                        <p className="mt-2 text-xs text-muted">{template.description}</p>
                                    )}

                                    <p className="mt-3 text-xs text-muted">
                                        {(template.blocks ?? []).length} block · cocok untuk {template.use_case}
                                    </p>

                                    <div className="mt-4">
                                        {isActive ? (
                                            <Button variant="secondary" block disabled>
                                                Template Terpasang
                                            </Button>
                                        ) : (
                                            <ConfirmButton
                                                title={`Pasang template ${template.name}?`}
                                                message="Semua block yang ada sekarang akan diganti dengan struktur template ini. Produk, pesanan, dan saldo kamu tetap aman."
                                                confirmLabel="Ya, ganti template"
                                                variant="primary"
                                                onConfirm={() =>
                                                    router.post(`/dashboard/toko/template/${template.slug}`, {
                                                        replace: true,
                                                    })
                                                }
                                            >
                                                <Button
                                                    variant={locked ? 'outline' : 'gradient'}
                                                    block
                                                    disabled={locked}
                                                >
                                                    {locked ? 'Perlu paket Pro' : 'Pakai Template Ini'}
                                                </Button>
                                            </ConfirmButton>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </Card>
        </div>
    );
}


function BlockPicker({
    groups,
    onPick,
    onClose,
}: {
    groups: Record<string, { value: string; label: string; group: string }[]>;
    onPick: (type: string) => void;
    onClose: () => void;
}) {
    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="picker-title"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="max-h-[85vh] w-full max-w-2xl animate-rise overflow-y-auto">
                <div className="sticky top-0 flex items-center justify-between border-b border-line bg-surface p-5">
                    <h2 id="picker-title" className="font-bold">
                        Pilih jenis block
                    </h2>
                    <Button variant="ghost" size="icon" onClick={onClose} aria-label="Tutup">
                        <X className="size-5" />
                    </Button>
                </div>

                <div className="space-y-5 p-5">
                    {Object.entries(groups).map(([group, items]) => (
                        <div key={group}>
                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-muted">{group}</p>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {items.map((item) => (
                                    <button
                                        key={item.value}
                                        type="button"
                                        onClick={() => onPick(item.value)}
                                        className="rounded-[var(--radius-field)] border border-line p-3 text-left text-sm font-semibold transition-colors hover:border-[var(--primary)] hover:bg-surface-2"
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
        </div>
    );
}

/* -------------------------------------------------------------------------- */

function BlockEditor({
    block,
    products,
    onLocalChange,
    onSaveState,
    onDelete,
}: {
    block: BuilderBlock;
    products: { id: number; name: string; price: number; type: string }[];
    onLocalChange: (patch: Partial<BuilderBlock>) => void;
    onSaveState: (state: SaveState) => void;
    onDelete: () => void;
}) {
    const [draft, setDraft] = useState({
        title: block.title ?? '',
        content: block.content ?? {},
        is_published: block.is_published,
        visible_mobile: block.visible_mobile,
        visible_desktop: block.visible_desktop,
        starts_at: block.starts_at?.slice(0, 16) ?? '',
        ends_at: block.ends_at?.slice(0, 16) ?? '',
    });

    const first = useRef(true);

    /** Debounced autosave — the builder never has an explicit save button. */
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }

        onSaveState('saving');

        const timer = setTimeout(() => {
            router.put(
                `/dashboard/blocks/${block.id}`,
                {
                    ...draft,
                    starts_at: draft.starts_at || null,
                    ends_at: draft.ends_at || null,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => onSaveState('saved'),
                    onError: () => onSaveState('error'),
                },
            );
        }, 900);

        return () => clearTimeout(timer);
    }, [draft]);

    const patch = (updates: Partial<typeof draft>) => {
        setDraft((current) => {
            const next = { ...current, ...updates };
            onLocalChange(next as Partial<BuilderBlock>);
            return next;
        });
    };

    const setContent = (updates: Record<string, any>) => {
        patch({ content: { ...draft.content, ...updates } });
    };

    return (
        <Card className="p-5">
            <div className="mb-4 flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-muted">Block</p>
                    <p className="font-bold">{block.type_label}</p>
                    <p className="mt-0.5 text-xs text-muted">
                        {block.impressions} tayang · {block.clicks} klik
                    </p>
                </div>

                <div className="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Duplikat block"
                        onClick={() =>
                            router.post(`/dashboard/blocks/${block.id}/duplicate`, {}, { preserveScroll: true })
                        }
                    >
                        <Copy className="size-4" />
                    </Button>

                    <ConfirmButton
                        title="Hapus block ini?"
                        message="Block ini akan dihapus dari tokomu. Tindakan ini nggak bisa dibatalkan."
                        confirmLabel="Ya, hapus"
                        onConfirm={onDelete}
                    >
                        <Button variant="ghost" size="icon" aria-label="Hapus block">
                            <Trash2 className="size-4 text-[var(--danger)]" />
                        </Button>
                    </ConfirmButton>
                </div>
            </div>

            <div className="space-y-4">
                <Field label="Judul block" hint="Kosongkan kalau nggak mau ditampilkan.">
                    <Input value={draft.title} onChange={(e) => patch({ title: e.target.value })} />
                </Field>

                <ContentFields type={block.type} content={draft.content} products={products} onChange={setContent} />

                <div className="space-y-3 border-t border-line pt-4">
                    <Switch
                        checked={draft.is_published}
                        onChange={(v) => patch({ is_published: v })}
                        label="Tampilkan block"
                        description="Matikan buat menyembunyikan tanpa menghapus."
                    />
                    <Switch
                        checked={draft.visible_mobile}
                        onChange={(v) => patch({ visible_mobile: v })}
                        label="Tampil di mobile"
                    />
                    <Switch
                        checked={draft.visible_desktop}
                        onChange={(v) => patch({ visible_desktop: v })}
                        label="Tampil di desktop"
                    />
                </div>

                <div className="grid gap-3 border-t border-line pt-4 sm:grid-cols-2">
                    <Field label="Mulai tampil" hint="Opsional">
                        <Input
                            type="datetime-local"
                            value={draft.starts_at}
                            onChange={(e) => patch({ starts_at: e.target.value })}
                        />
                    </Field>
                    <Field label="Berhenti tampil" hint="Opsional">
                        <Input
                            type="datetime-local"
                            value={draft.ends_at}
                            onChange={(e) => patch({ ends_at: e.target.value })}
                        />
                    </Field>
                </div>
            </div>
        </Card>
    );
}

/** Per-block-type form fields. */
function ContentFields({
    type,
    content,
    products,
    onChange,
}: {
    type: string;
    content: Record<string, any>;
    products: { id: number; name: string }[];
    onChange: (updates: Record<string, any>) => void;
}) {
    switch (type) {
        case 'HEADING':
            return (
                <>
                    <Field label="Teks judul">
                        <Input value={content.text ?? ''} onChange={(e) => onChange({ text: e.target.value })} />
                    </Field>
                    <Field label="Ukuran">
                        <Select value={content.size ?? 'md'} onChange={(e) => onChange({ size: e.target.value })}>
                            <option value="sm">Kecil</option>
                            <option value="md">Sedang</option>
                            <option value="lg">Besar</option>
                        </Select>
                    </Field>
                </>
            );

        case 'TEXT':
            return (
                <Field label="Isi teks" hint="Satu baris kosong = paragraf baru.">
                    <Textarea rows={5} value={content.body ?? ''} onChange={(e) => onChange({ body: e.target.value })} />
                </Field>
            );

        case 'LINK_BUTTON':
            return (
                <>
                    <Field label="Label tombol">
                        <Input value={content.label ?? ''} onChange={(e) => onChange({ label: e.target.value })} />
                    </Field>
                    <Field label="URL tujuan">
                        <Input
                            type="url"
                            placeholder="https://"
                            value={content.url ?? ''}
                            onChange={(e) => onChange({ url: e.target.value })}
                        />
                    </Field>
                </>
            );

        case 'SOCIAL_LINKS':
            return (
                <div className="space-y-3">
                    {['instagram', 'tiktok', 'youtube', 'x', 'website'].map((platform) => (
                        <Field key={platform} label={platform[0].toUpperCase() + platform.slice(1)}>
                            <Input
                                type="url"
                                placeholder="https://"
                                value={content.links?.[platform] ?? ''}
                                onChange={(e) =>
                                    onChange({ links: { ...(content.links ?? {}), [platform]: e.target.value } })
                                }
                            />
                        </Field>
                    ))}
                </div>
            );

        case 'IMAGE':
            return (
                <>
                    <MediaPicker
                        label="Gambar"
                        value={content.url ?? ''}
                        onChange={(url) => onChange({ url })}
                        hint="JPG, PNG, WEBP, atau GIF. Maksimal 4 MB."
                    />
                    <Field label="Teks alternatif" hint="Dibaca oleh pembaca layar dan muncul kalau gambar gagal dimuat.">
                        <Input value={content.alt ?? ''} onChange={(e) => onChange({ alt: e.target.value })} />
                    </Field>
                </>
            );

        case 'GALLERY':
            return (
                <GalleryPicker
                    images={content.images ?? []}
                    onChange={(images) => onChange({ images })}
                />
            );

        case 'VIDEO':
        case 'EMBED': {
            const target = toEmbedUrl(content.url ?? '');
            const filled = (content.url ?? '').trim() !== '';

            return (
                <Field
                    label="URL"
                    hint={filled ? undefined : `Tempel link ${EMBED_PROVIDERS}.`}
                    error={filled && !target ? `Link ini nggak bisa di-embed. Didukung: ${EMBED_PROVIDERS}.` : undefined}
                >
                    <Input
                        value={content.url ?? ''}
                        onChange={(e) => onChange({ url: e.target.value })}
                        invalid={filled && !target}
                        placeholder="https://youtube.com/watch?v=..."
                    />
                    {target && (
                        <p className="mt-1.5 text-xs font-medium text-[var(--success)]">
                            Terdeteksi {target.provider} — siap ditampilkan.
                        </p>
                    )}
                </Field>
            );
        }

        case 'SPACER':
            return (
                <Field label="Tinggi (px)">
                    <Input
                        type="number"
                        min={8}
                        max={200}
                        value={content.height ?? 24}
                        onChange={(e) => onChange({ height: Number(e.target.value) })}
                    />
                </Field>
            );

        case 'FAQ':
            return (
                <RepeaterField
                    label="Pertanyaan"
                    items={content.items ?? []}
                    fields={[
                        { key: 'question', label: 'Pertanyaan' },
                        { key: 'answer', label: 'Jawaban', multiline: true },
                    ]}
                    onChange={(items) => onChange({ items })}
                />
            );

        case 'TESTIMONIAL':
            return (
                <RepeaterField
                    label="Testimoni"
                    items={content.items ?? []}
                    fields={[
                        { key: 'name', label: 'Nama' },
                        { key: 'role', label: 'Keterangan' },
                        { key: 'text', label: 'Testimoni', multiline: true },
                    ]}
                    onChange={(items) => onChange({ items })}
                />
            );

        case 'COUNTDOWN':
            return (
                <>
                    <Field label="Label">
                        <Input value={content.label ?? ''} onChange={(e) => onChange({ label: e.target.value })} />
                    </Field>
                    <Field label="Berakhir pada">
                        <Input
                            type="datetime-local"
                            value={content.ends_at?.slice(0, 16) ?? ''}
                            onChange={(e) => onChange({ ends_at: e.target.value })}
                        />
                    </Field>
                </>
            );

        case 'PROMO_BANNER':
            return (
                <>
                    <Field label="Headline">
                        <Input value={content.headline ?? ''} onChange={(e) => onChange({ headline: e.target.value })} />
                    </Field>
                    <Field label="Subteks">
                        <Input value={content.subtext ?? ''} onChange={(e) => onChange({ subtext: e.target.value })} />
                    </Field>
                    <Field label="Kode promo" hint="Opsional">
                        <Input
                            value={content.code ?? ''}
                            onChange={(e) => onChange({ code: e.target.value.toUpperCase() })}
                            className="uppercase"
                        />
                    </Field>
                    <MediaPicker
                        label="Gambar latar"
                        value={content.image ?? ''}
                        onChange={(url) => onChange({ image: url })}
                        hint="Opsional. Kalau kosong, dipakai gradasi warna tema."
                    />
                </>
            );

        case 'LEAD_FORM':
            return (
                <>
                    <Field label="Headline">
                        <Input value={content.headline ?? ''} onChange={(e) => onChange({ headline: e.target.value })} />
                    </Field>
                    <Field label="Subteks">
                        <Input value={content.subtext ?? ''} onChange={(e) => onChange({ subtext: e.target.value })} />
                    </Field>
                    <Field label="Label tombol">
                        <Input
                            value={content.button_label ?? ''}
                            onChange={(e) => onChange({ button_label: e.target.value })}
                        />
                    </Field>
                    <Switch
                        checked={content.ask_phone ?? false}
                        onChange={(v) => onChange({ ask_phone: v })}
                        label="Minta nomor WhatsApp"
                    />
                </>
            );

        case 'WHATSAPP_CTA':
            return (
                <>
                    <Field label="Nomor WhatsApp" hint="Format 62xxx tanpa tanda +.">
                        <Input value={content.number ?? ''} onChange={(e) => onChange({ number: e.target.value })} />
                    </Field>
                    <Field label="Label tombol">
                        <Input value={content.label ?? ''} onChange={(e) => onChange({ label: e.target.value })} />
                    </Field>
                    <Field label="Pesan otomatis">
                        <Textarea
                            rows={2}
                            value={content.message ?? ''}
                            onChange={(e) => onChange({ message: e.target.value })}
                        />
                    </Field>
                </>
            );

        case 'PRODUCT':
        case 'AFFILIATE_PRODUCT':
            return (
                <Field label="Pilih produk">
                    <Select
                        value={content.product_id ?? ''}
                        onChange={(e) => onChange({ product_id: Number(e.target.value) })}
                    >
                        <option value="">— pilih produk —</option>
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            );

        case 'PRODUCT_COLLECTION':
            return (
                <Field label="Pilih beberapa produk" hint="Tahan Ctrl/Cmd buat pilih lebih dari satu.">
                    <Select
                        multiple
                        size={Math.min(8, Math.max(3, products.length))}
                        value={(content.product_ids ?? []).map(String)}
                        onChange={(e) =>
                            onChange({
                                product_ids: Array.from(e.target.selectedOptions).map((o) => Number(o.value)),
                            })
                        }
                        className="h-auto"
                    >
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                            </option>
                        ))}
                    </Select>
                </Field>
            );

        case 'FEATURED_PRODUCTS':
            return (
                <Field label="Jumlah produk ditampilkan">
                    <Input
                        type="number"
                        min={1}
                        max={12}
                        value={content.limit ?? 4}
                        onChange={(e) => onChange({ limit: Number(e.target.value) })}
                    />
                </Field>
            );

        case 'ARTICLE':
            return (
                <>
                    <Field label="Judul artikel">
                        <Input value={content.title ?? ''} onChange={(e) => onChange({ title: e.target.value })} />
                    </Field>
                    <Field label="Ringkasan">
                        <Textarea
                            rows={3}
                            value={content.excerpt ?? ''}
                            onChange={(e) => onChange({ excerpt: e.target.value })}
                        />
                    </Field>
                    <Field label="Link artikel">
                        <Input value={content.url ?? ''} onChange={(e) => onChange({ url: e.target.value })} />
                    </Field>
                </>
            );

        case 'DIVIDER':
            return <p className="text-sm text-muted">Block ini nggak butuh pengaturan tambahan.</p>;

        default:
            return null;
    }
}

function RepeaterField({
    label,
    items,
    fields,
    onChange,
}: {
    label: string;
    items: Record<string, string>[];
    fields: { key: string; label: string; multiline?: boolean }[];
    onChange: (items: Record<string, string>[]) => void;
}) {
    return (
        <div>
            <p className="mb-2 text-sm font-semibold">{label}</p>

            <div className="space-y-3">
                {items.map((item, index) => (
                    <Card key={index} className="p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-xs font-bold text-muted">#{index + 1}</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Hapus"
                                onClick={() => onChange(items.filter((_, i) => i !== index))}
                            >
                                <Trash2 className="size-4 text-[var(--danger)]" />
                            </Button>
                        </div>

                        <div className="space-y-2">
                            {fields.map((field) => (
                                <Field key={field.key} label={field.label}>
                                    {field.multiline ? (
                                        <Textarea
                                            rows={2}
                                            value={item[field.key] ?? ''}
                                            onChange={(e) =>
                                                onChange(
                                                    items.map((row, i) =>
                                                        i === index ? { ...row, [field.key]: e.target.value } : row,
                                                    ),
                                                )
                                            }
                                        />
                                    ) : (
                                        <Input
                                            value={item[field.key] ?? ''}
                                            onChange={(e) =>
                                                onChange(
                                                    items.map((row, i) =>
                                                        i === index ? { ...row, [field.key]: e.target.value } : row,
                                                    ),
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>

            <Button
                variant="outline"
                size="sm"
                className="mt-2"
                onClick={() => onChange([...items, Object.fromEntries(fields.map((f) => [f.key, '']))])}
            >
                <Plus className="size-4" />
                Tambah {label}
            </Button>
        </div>
    );
}

/* -------------------------------------------------------------------------- */

/** Sensible starting content so a new block never renders empty. */
function defaultContent(type: string): Record<string, any> {
    switch (type) {
        case 'HEADING':
            return { text: 'Judul baru', size: 'md', align: 'center' };
        case 'TEXT':
            return { body: 'Tulis sesuatu tentang tokomu di sini.' };
        case 'LINK_BUTTON':
            return { label: 'Klik di sini', url: 'https://' };
        case 'SOCIAL_LINKS':
            return { links: {} };
        case 'GALLERY':
            return { images: [] };
        case 'IMAGE':
            return { url: '', alt: '' };
        case 'SPACER':
            return { height: 24 };
        case 'FAQ':
            return { items: [{ question: 'Pertanyaan pertama?', answer: 'Jawabannya di sini.' }] };
        case 'TESTIMONIAL':
            return { items: [{ name: 'Nama pembeli', role: 'Pelanggan', text: 'Tulis testimoni di sini.' }] };
        case 'PROMO_BANNER':
            return { headline: 'Promo spesial!', subtext: 'Berlaku terbatas.' };
        case 'LEAD_FORM':
            return { headline: 'Gabung dulu yuk', button_label: 'Kirim', ask_phone: false };
        case 'WHATSAPP_CTA':
            return { label: 'Chat WhatsApp', message: 'Halo, aku mau tanya soal produkmu.' };
        case 'FEATURED_PRODUCTS':
            return { limit: 4 };
        case 'PRODUCT_COLLECTION':
            return { product_ids: [] };
        default:
            return {};
    }
}

/** Resolves product references locally so the preview updates without a round trip. */
function hydrate(
    block: BuilderBlock,
    products: { id: number; name: string; price: number; thumbnail_url: string | null; type: string }[],
): Record<string, any> {
    const content = { ...(block.content ?? {}) };

    const ids: number[] = [
        ...(content.product_id ? [content.product_id] : []),
        ...(content.product_ids ?? []),
    ];

    if (ids.length > 0) {
        content.products = products
            .filter((p) => ids.includes(p.id))
            .map((p) => ({
                id: p.id,
                slug: '',
                type: p.type,
                type_label: '',
                name: p.name,
                short_description: null,
                thumbnail_url: p.thumbnail_url,
                price: p.price,
                compare_at_price: null,
                discount_percent: 0,
                is_pay_what_you_want: false,
                minimum_price: null,
                external_url: null,
                is_buyable: true,
            }));
    }

    if (block.type === 'FEATURED_PRODUCTS' && !content.products) {
        content.products = products.slice(0, content.limit ?? 4).map((p) => ({
            id: p.id,
            slug: '',
            type: p.type,
            type_label: '',
            name: p.name,
            short_description: null,
            thumbnail_url: p.thumbnail_url,
            price: p.price,
            compare_at_price: null,
            discount_percent: 0,
            is_pay_what_you_want: false,
            minimum_price: null,
            external_url: null,
            is_buyable: true,
        }));
    }

    return content;
}
