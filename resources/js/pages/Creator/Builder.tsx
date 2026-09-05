import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign, Building2, CheckCircle2, ChevronDown, ChevronUp, CircleHelp, Code2, Copy,
    ExternalLink, Eye, FileText, GalleryHorizontal, GalleryHorizontalEnd, GripVertical, Heading1,
    Image as ImageIcon, LayoutGrid, LayoutTemplate, Link2, ListOrdered, Loader2, Megaphone,
    MessageCircle, MessageSquareQuote, Monitor, MoveHorizontal, MoveVertical, Package, Paintbrush,
    Palette, PartyPopper, Plus, Rocket, Save, Share2, ShoppingBag, SlidersHorizontal, Smartphone,
    Sparkles, Tablet, Timer, Trash2, TrendingUp, Type, Video, X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { BlockStylePanel } from '@/components/block-style-panel';
import type { BlockStyleTokens } from '@/lib/block-style';
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
import type { StorefrontBlock, StoreTheme } from '@/types';

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

type ThemeDraft = Pick<StoreTheme,
    'primary_color' | 'accent_color' | 'background_type' | 'background_value' |
    'font_family' | 'button_style' | 'card_style' | 'product_layout' | 'color_scheme'
> & { extras: Required<NonNullable<StoreTheme['extras']>> };

interface StylePreset {
    id: string;
    name: string;
    description: string;
    primary: string;
    accent: string;
    background: string;
    scheme: 'light' | 'dark';
    surface: string;
    badge: string;
    badgeText: string;
    contact: string;
    spacing: ThemeDraft['extras']['spacing'];
}

const STYLE_PRESETS: StylePreset[] = [
    { id: 'signature', name: 'JualanYok Signature', description: 'Lilac lembut dengan sentuhan rose.', primary: '#111827', accent: '#7C3AED', background: 'linear-gradient(145deg,#F7F4FF 0%,#F0EDFF 52%,#FFF3F6 100%)', scheme: 'light', surface: '#FFFFFF', badge: '#F3EEFF', badgeText: '#6D28D9', contact: '#7C3AED', spacing: 'balanced' },
    { id: 'editorial', name: 'Editorial Cream', description: 'Hangat, bersih, dan terasa butik.', primary: '#292524', accent: '#C2410C', background: 'linear-gradient(145deg,#FFFCF5 0%,#F8F1E6 55%,#FFF8EF 100%)', scheme: 'light', surface: '#FFFEFB', badge: '#F7EBDD', badgeText: '#9A3412', contact: '#C2410C', spacing: 'airy' },
    { id: 'coastal', name: 'Coastal Studio', description: 'Biru muda untuk katalog modern.', primary: '#172554', accent: '#0284C7', background: 'linear-gradient(145deg,#F5FAFF 0%,#EAF5FF 52%,#F2FBFA 100%)', scheme: 'light', surface: '#FFFFFF', badge: '#E0F2FE', badgeText: '#0369A1', contact: '#0284C7', spacing: 'balanced' },
    { id: 'sakura', name: 'Sakura Milk', description: 'Soft pink yang tetap profesional.', primary: '#4C1D2F', accent: '#DB2777', background: 'linear-gradient(145deg,#FFF8FB 0%,#FCEEF5 52%,#FFF7F2 100%)', scheme: 'light', surface: '#FFFFFF', badge: '#FCE7F3', badgeText: '#BE185D', contact: '#DB2777', spacing: 'balanced' },
    { id: 'matcha', name: 'Matcha Atelier', description: 'Natural dan premium untuk lifestyle.', primary: '#173B2C', accent: '#15803D', background: 'linear-gradient(145deg,#F7FAF5 0%,#EDF5EA 52%,#F6F3E8 100%)', scheme: 'light', surface: '#FFFEFA', badge: '#DCFCE7', badgeText: '#166534', contact: '#15803D', spacing: 'airy' },
    { id: 'peach', name: 'Peach Sorbet', description: 'Hangat dan cerah untuk F&B serta beauty.', primary: '#431407', accent: '#F97316', background: 'radial-gradient(circle at 15% 10%,#FED7AA 0,transparent 38%),linear-gradient(145deg,#FFF7ED,#FFF1F2)', scheme: 'light', surface: '#FFFFFF', badge: '#FFEDD5', badgeText: '#C2410C', contact: '#EA580C', spacing: 'balanced' },
    { id: 'lavender-grid', name: 'Lavender Grid', description: 'Studio digital dengan tekstur modern.', primary: '#24104F', accent: '#8B5CF6', background: 'linear-gradient(#8B5CF60D 1px,transparent 1px),linear-gradient(90deg,#8B5CF60D 1px,transparent 1px),#F8F7FF', scheme: 'light', surface: '#FFFFFF', badge: '#EDE9FE', badgeText: '#6D28D9', contact: '#7C3AED', spacing: 'compact' },
    { id: 'nordic', name: 'Nordic Ice', description: 'Minimal, tenang, dan terasa editorial.', primary: '#0F172A', accent: '#475569', background: 'radial-gradient(circle at 85% 5%,#DBEAFE 0,transparent 34%),linear-gradient(145deg,#FFFFFF,#F1F5F9)', scheme: 'light', surface: '#FFFFFF', badge: '#E2E8F0', badgeText: '#334155', contact: '#0F172A', spacing: 'airy' },
    { id: 'mocha', name: 'Mocha Paper', description: 'Nuansa craft premium dan personal.', primary: '#3F2D25', accent: '#A16207', background: 'linear-gradient(135deg,#F5F0E8 25%,#FAF7F2 25%,#FAF7F2 50%,#F5F0E8 50%,#F5F0E8 75%,#FAF7F2 75%)', scheme: 'light', surface: '#FFFCF7', badge: '#EDE0D1', badgeText: '#713F12', contact: '#92400E', spacing: 'balanced' },
    { id: 'citrus', name: 'Citrus Pop', description: 'Segar untuk kampanye dan produk muda.', primary: '#18332A', accent: '#65A30D', background: 'radial-gradient(circle at 12% 15%,#D9F99D 0,transparent 32%),linear-gradient(145deg,#FAFFF3,#F0FDFA)', scheme: 'light', surface: '#FFFFFF', badge: '#ECFCCB', badgeText: '#3F6212', contact: '#4D7C0F', spacing: 'compact' },
    { id: 'mono', name: 'Mono Gallery', description: 'Hitam-putih modern untuk portofolio.', primary: '#111111', accent: '#525252', background: 'linear-gradient(145deg,#FAFAFA,#EEEEEE)', scheme: 'light', surface: '#FFFFFF', badge: '#E5E5E5', badgeText: '#171717', contact: '#171717', spacing: 'airy' },
    { id: 'candy', name: 'Candy Mesh', description: 'Playful tanpa kehilangan kesan premium.', primary: '#3B0764', accent: '#C026D3', background: 'radial-gradient(circle at 15% 20%,#F5D0FE 0,transparent 34%),radial-gradient(circle at 85% 5%,#C4B5FD 0,transparent 36%),#FFF7FD', scheme: 'light', surface: '#FFFFFF', badge: '#FAE8FF', badgeText: '#A21CAF', contact: '#C026D3', spacing: 'balanced' },
];

const FONT_PREVIEW_STACKS: Record<string, string> = {
    jakarta: '"Plus Jakarta Sans", sans-serif',
    inter: 'Inter, sans-serif',
    manrope: 'Manrope, sans-serif',
    'dm-sans': '"DM Sans", sans-serif',
    outfit: 'Outfit, sans-serif',
    sora: 'Sora, sans-serif',
    space: '"Space Grotesk", sans-serif',
    poppins: 'Poppins, sans-serif',
    nunito: 'Nunito, sans-serif',
    playfair: '"Playfair Display", Georgia, serif',
    lora: 'Lora, Georgia, serif',
    system: 'ui-sans-serif, system-ui, sans-serif',
};

function readablePreview(hex: string): string {
    const rgb = [1, 3, 5].map((end) => Number.parseInt(hex.slice(end, end + 2), 16));
    const brightness = (rgb[0] * 299 + rgb[1] * 587 + rgb[2] * 114) / 1000;
    return brightness > 150 ? '#111827' : '#FFFFFF';
}

function normalizeTheme(theme: Partial<StoreTheme> | null | undefined): ThemeDraft {
    return {
        primary_color: theme?.primary_color ?? '#111827',
        accent_color: theme?.accent_color ?? '#7C3AED',
        background_type: theme?.background_type ?? 'solid',
        background_value: theme?.background_value ?? '#F6F7FB',
        font_family: theme?.font_family ?? 'jakarta',
        button_style: theme?.button_style ?? 'rounded',
        card_style: theme?.card_style ?? 'soft',
        product_layout: theme?.product_layout ?? 'grid',
        color_scheme: theme?.color_scheme ?? 'light',
        extras: {
            surface_color: theme?.extras?.surface_color ?? (theme?.color_scheme === 'dark' ? '#191C23' : '#FFFFFF'),
            badge_background_color: theme?.extras?.badge_background_color ?? '#F5F3FF',
            badge_text_color: theme?.extras?.badge_text_color ?? theme?.primary_color ?? '#7C3AED',
            contact_button_color: theme?.extras?.contact_button_color ?? '#25D366',
            spacing: theme?.extras?.spacing ?? 'balanced',
        },
    };
}

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
    const [appearanceOpen, setAppearanceOpen] = useState(false);
    const [mobileTab, setMobileTab] = useState<'blocks' | 'edit' | 'preview'>('blocks');
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const [themeSaveState, setThemeSaveState] = useState<SaveState>('idle');
    const [themeDraft, setThemeDraft] = useState<ThemeDraft>(() => normalizeTheme(theme));
    const pageUrl = usePage().url;
    const firstProductReady = pageUrl.includes('first_product=1');
    const justPublished = pageUrl.includes('published=1');

    useEffect(() => setBlocks(initialBlocks), [initialBlocks]);
    useEffect(() => setThemeDraft(normalizeTheme(theme)), [theme]);

    const active = blocks.find((b) => b.id === activeId) ?? null;
    const activeTemplate = templates.find((t: any) => t.id === store.storefront_template_id) ?? null;

    const atLimit = limits.blocks !== null && limits.blocks_used >= limits.blocks;

    const saveTheme = () => {
        if (themeDraft.background_type === 'image' && !themeDraft.background_value.trim()) return;

        setThemeSaveState('saving');
        router.put('/dashboard/toko/tema', themeDraft, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setThemeSaveState('saved');
                setAppearanceOpen(false);
            },
            onError: () => setThemeSaveState('error'),
        });
    };

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
                        <div className="min-w-0 flex-1"><p className="text-xs font-semibold uppercase tracking-[.06em] text-emerald-700 dark:text-emerald-300">Toko berhasil dipublikasikan</p><h2 className="mt-1 text-xl font-bold tracking-tight">Tokomu sekarang bisa dikunjungi pembeli.</h2><p className="mt-1 text-sm text-muted">Buka toko untuk pemeriksaan terakhir, lalu bagikan alamatnya ke audiensmu.</p></div>
                        <div className="flex shrink-0 gap-2"><Button variant="outline" onClick={() => navigator.clipboard.writeText(store.public_url)}><Copy className="size-4" /> Salin link</Button><a href={store.public_url} target="_blank" rel="noopener noreferrer" className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] bg-emerald-600 px-4 text-sm font-semibold text-white"><ExternalLink className="size-4" /> Buka toko</a></div>
                    </div>
                </section>
            )}

            {firstProductReady && !justPublished && (
                <section className="mb-5 rounded-[1.4rem] border border-violet-200 bg-violet-50 p-4 dark:border-violet-500/20 dark:bg-violet-500/10 sm:p-5">
                    <div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-600 text-white"><CheckCircle2 className="size-5" /></span><div><p className="font-semibold">Produk pertama sudah masuk ke pratinjau</p><p className="mt-1 text-sm text-muted">Langkah 3 dari 3 — cek tampilan mobile di kanan, lalu tekan <strong>Publikasikan toko</strong>.</p></div></div>
                </section>
            )}

            {/* Toolbar */}
            <div className="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">Atur Tampilan</h1>
                    <p className="text-[0.8125rem] text-muted sm:text-sm">
                        Susun block tokomu. Perubahan tersimpan otomatis sebagai draft.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <SaveIndicator state={saveState} />

                    <a
                        href={`/${store.username}/preview`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-[var(--radius-field)] border border-line px-3 text-[0.8125rem] font-semibold hover:bg-surface-2 sm:h-11 sm:flex-none sm:px-4 sm:text-sm"
                    >
                        <Eye className="size-4" />
                        Buka pratinjau
                    </a>

                    {store.is_published ? (
                        <Button
                            data-tour="publish"
                            variant="gradient"
                            className="h-10 flex-1 px-3 text-[0.8125rem] sm:h-11 sm:flex-none sm:px-5 sm:text-sm"
                            onClick={() => router.post('/dashboard/toko/publish', {}, { preserveScroll: true })}
                        >
                            <Rocket className="size-4" />
                            Publikasikan Perubahan
                        </Button>
                    ) : (
                        <Button
                            data-tour="publish"
                            variant="gradient"
                            className="h-10 flex-1 px-3 text-[0.8125rem] sm:h-11 sm:flex-none sm:px-5 sm:text-sm"
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

            {/*
                Three columns need the width for three. At lg the sidebar takes
                264px and the two fixed rails another 640, which left the editor
                — the column actually being worked in — about twenty pixels
                wide. Two columns until xl, and every column allowed to shrink.
            */}
            <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[240px_minmax(0,1fr)_360px]">
                {/* Block list, and the store-wide settings under it */}
                <div
                    className={cn(
                        'min-w-0',
                        mobileTab === 'preview' ? 'hidden' : 'block',
                        mobileTab === 'edit' && 'order-2',
                        'lg:order-none lg:block',
                    )}
                >
                    <Card className={cn('p-3', mobileTab === 'blocks' ? 'block' : 'hidden', 'lg:block')}>
                        <div className="mb-2 flex items-center justify-between px-1">
                            <p className="text-sm font-semibold">
                                Block{' '}
                                <span className="text-muted">
                                    ({limits.blocks_used}
                                    {limits.blocks !== null && `/${limits.blocks}`})
                                </span>
                            </p>
                            <Button
                                data-tour="add-block"
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
                            <ul data-tour="block-list" className="space-y-1">
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
                                                        <span className="rounded bg-surface-2 px-1.5 py-px text-[11px] font-bold text-muted">
                                                            Disembunyikan
                                                        </span>
                                                    )}
                                                    {block.has_unpublished_changes && (
                                                        <span className="rounded bg-amber-100 px-1.5 py-px text-[11px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
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

                    {/* Store appearance */}
                    <Card className={cn('overflow-hidden p-0 lg:mt-3', mobileTab === 'edit' ? 'block' : 'hidden', 'lg:block')}>
                        <div
                            className="relative h-24 overflow-hidden border-b border-line"
                            style={{ background: themeDraft.background_type === 'image' ? `center / cover url("${themeDraft.background_value}")` : themeDraft.background_type === 'gradient' && !themeDraft.background_value.includes('gradient(') ? `linear-gradient(145deg, ${themeDraft.primary_color}18, ${themeDraft.accent_color}22)` : themeDraft.background_value }}
                        >
                            <div className="absolute inset-x-3 bottom-3 flex items-center gap-2 rounded-xl border border-white/60 bg-white/85 p-2 shadow-sm backdrop-blur-md">
                                <span className="size-7 rounded-lg shadow-sm" style={{ background: themeDraft.primary_color }} />
                                <span className="size-7 rounded-lg shadow-sm" style={{ background: themeDraft.accent_color }} />
                                <span className="ml-auto text-[11px] font-semibold uppercase tracking-[.06em] text-slate-700">Gaya aktif</span>
                            </div>
                        </div>
                        <div className="p-4">
                            <div className="flex items-start gap-3">
                                <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><Paintbrush className="size-4" /></span>
                                <div><p className="text-sm font-bold">Desain toko</p><p className="mt-0.5 text-xs leading-5 text-muted">Background, warna, font, tombol, dan kartu.</p></div>
                            </div>
                            <Button variant="outline" block className="mt-3" onClick={() => setAppearanceOpen(true)}>
                                <Palette className="size-4" /> Sesuaikan gaya
                            </Button>
                        </div>
                    </Card>

                    {/* Templates */}
                    <Card className={cn('mt-3 p-4', mobileTab === 'edit' ? 'block' : 'hidden', 'lg:block')}>
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
                <div className={cn('min-w-0', mobileTab === 'edit' ? 'block' : 'hidden', 'order-1 lg:order-none lg:block')}>
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
                {/*
                    On a small laptop the preview drops below the editor and
                    takes the full width, which is more useful than a 360px rail
                    squeezed out of a screen that has no room for one. It only
                    becomes a sticky sidebar once there is width for three.
                */}
                <div
                    data-tour="preview"
                    className={cn('min-w-0 lg:col-span-2 xl:col-span-1', mobileTab === 'preview' ? 'block' : 'hidden', 'lg:block')}
                >
                    <div className="xl:sticky xl:top-24">
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
                            <LivePreview store={store} theme={themeDraft} blocks={previewBlocks} />
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

            {appearanceOpen && (
                <AppearanceStudio
                    value={themeDraft}
                    saveState={themeSaveState}
                    onChange={(patch) => {
                        setThemeSaveState('idle');
                        setThemeDraft((current) => ({ ...current, ...patch }));
                    }}
                    onSave={saveTheme}
                    onClose={() => {
                        setThemeDraft(normalizeTheme(theme));
                        setThemeSaveState('idle');
                        setAppearanceOpen(false);
                    }}
                />
            )}
        </DashboardLayout>
    );
}

/* -------------------------------------------------------------------------- */

const DEVICE_WIDTHS = { mobile: 375, tablet: 768, desktop: 1280 } as const;
const DEVICE_HEIGHTS = { mobile: 720, tablet: 780, desktop: 800 } as const;

function AppearanceStudio({
    value,
    saveState,
    onChange,
    onSave,
    onClose,
}: {
    value: ThemeDraft;
    saveState: SaveState;
    onChange: (patch: Partial<ThemeDraft>) => void;
    onSave: () => void;
    onClose: () => void;
}) {
    const activePreset = STYLE_PRESETS.find((preset) =>
        preset.background === value.background_value
        && preset.primary === value.primary_color
        && preset.accent === value.accent_color,
    );
    const backgroundPreview = value.background_type === 'image' && value.background_value
        ? `center / cover no-repeat url("${value.background_value}")`
        : value.background_type === 'gradient' && !value.background_value.includes('gradient(')
          ? `linear-gradient(145deg, ${value.primary_color}18, ${value.accent_color}22)`
          : value.background_value;

    const chooseBackgroundType = (type: ThemeDraft['background_type']) => {
        if (type === 'solid') onChange({ background_type: type, background_value: '#F6F7FB' });
        if (type === 'gradient') onChange({ background_type: type, background_value: 'brand-gradient' });
        if (type === 'image') onChange({ background_type: type, background_value: '' });
    };

    return (
        <div className="fixed inset-0 z-[95] overflow-y-auto bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" aria-labelledby="appearance-title">
            <div className="mx-auto w-full max-w-6xl overflow-hidden rounded-[1.75rem] border border-white/20 bg-surface shadow-2xl">
                <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-7 sm:py-5">
                    <div className="flex items-start gap-3">
                        <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300"><Sparkles className="size-5" /></span>
                        <div><p className="text-[11px] font-semibold uppercase tracking-[.06em] text-[var(--primary)]">JualanYok Style Studio</p><h2 id="appearance-title" className="mt-1 text-xl font-bold tracking-tight">Buat tokomu punya karakter</h2><p className="mt-1 text-sm text-muted">Struktur template tetap aman. Kamu hanya mengubah identitas visualnya.</p></div>
                    </div>
                    <Button variant="ghost" size="icon" onClick={onClose} aria-label="Tutup pengaturan tampilan"><X className="size-5" /></Button>
                </div>

                <div className="grid lg:grid-cols-[1fr_340px]">
                    <div className="space-y-7 p-5 sm:p-7">
                        <section>
                            <div className="mb-3 flex items-end justify-between gap-3"><div><h3 className="font-semibold">Background premium</h3><p className="mt-0.5 text-xs text-muted">Satu klik mengatur background, kartu, badge, tombol, dan ritme yang sudah dikurasi.</p></div><Badge tone="brand">12 pilihan</Badge></div>
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {STYLE_PRESETS.map((preset) => {
                                    const active = activePreset?.id === preset.id;
                                    return (
                                        <button
                                            key={preset.id}
                                            type="button"
                                            onClick={() => onChange({
                                                primary_color: preset.primary,
                                                accent_color: preset.accent,
                                                background_type: 'gradient',
                                                background_value: preset.background,
                                                color_scheme: preset.scheme,
                                                extras: {
                                                    surface_color: preset.surface,
                                                    badge_background_color: preset.badge,
                                                    badge_text_color: preset.badgeText,
                                                    contact_button_color: preset.contact,
                                                    spacing: preset.spacing,
                                                },
                                            })}
                                            className={cn('overflow-hidden rounded-2xl border bg-surface text-left transition hover:-translate-y-0.5 hover:shadow-lift', active ? 'border-[var(--primary)] ring-2 ring-[var(--primary)]/20' : 'border-line')}
                                        >
                                            <span className="relative block h-20" style={{ background: preset.background }}><span className="absolute bottom-2 left-2 size-5 rounded-full border-2 border-white shadow-sm" style={{ background: preset.primary }} /><span className="absolute bottom-2 left-6 size-5 rounded-full border-2 border-white shadow-sm" style={{ background: preset.accent }} />{active && <span className="absolute right-2 top-2 grid size-6 place-items-center rounded-full bg-slate-950 text-white"><CheckCircle2 className="size-4" /></span>}</span>
                                            <span className="block p-3"><b className="block text-xs">{preset.name}</b><small className="mt-1 block text-[11px] leading-4 text-muted">{preset.description}</small></span>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <section className="border-t border-line pt-6">
                            <div className="mb-4"><h3 className="font-semibold">Buat gaya sendiri</h3><p className="mt-0.5 text-xs text-muted">Atur detail tanpa mengubah susunan block dari template.</p></div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-4">
                                    <div>
                                        <p className="mb-2 text-xs font-bold">Jenis background</p>
                                        <div className="grid grid-cols-3 gap-1 rounded-xl bg-surface-2 p-1">
                                            {([['solid', <Palette className="size-3.5" />, 'Warna'], ['gradient', <Sparkles className="size-3.5" />, 'Gradien'], ['image', <ImageIcon className="size-3.5" />, 'Gambar']] as const).map(([type, icon, label]) => <button key={type} type="button" onClick={() => chooseBackgroundType(type)} className={cn('flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-[11px] font-bold transition', value.background_type === type ? 'bg-surface text-fg shadow-sm' : 'text-muted')}>{icon}{label}</button>)}
                                        </div>
                                    </div>

                                    {value.background_type === 'solid' && <ColorControl label="Warna background" value={/^#[0-9A-F]{6}$/i.test(value.background_value) ? value.background_value : '#F6F7FB'} onChange={(background_value) => onChange({ background_value })} />}
                                    {value.background_type === 'gradient' && <div className="rounded-xl border border-line bg-surface-2 p-3 text-xs leading-5 text-muted"><Sparkles className="mb-2 size-4 text-[var(--primary)]" />Gradien khusus otomatis mengikuti warna utama dan aksen. Kamu juga bisa memilih preset di atas.</div>}
                                    {value.background_type === 'image' && <MediaPicker label="Gambar background" value={value.background_value} onChange={(background_value) => onChange({ background_value })} hint="Pilih gambar vertikal atau tekstur ringan. Maksimal 4 MB." />}

                                    <div className="grid grid-cols-2 gap-3"><ColorControl label="Warna utama" value={value.primary_color} onChange={(primary_color) => onChange({ primary_color })} /><ColorControl label="Warna aksen" value={value.accent_color} onChange={(accent_color) => onChange({ accent_color })} /></div>

                                    <div className="rounded-2xl border border-line bg-surface-2 p-4">
                                        <div className="mb-3"><p className="text-xs font-semibold">Warna komponen</p><p className="mt-0.5 text-[11px] leading-4 text-muted">Ubah kartu putih, badge informasi, dan CTA tanpa mencari menu lain.</p></div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <ColorControl label="Kartu profil" value={value.extras.surface_color} onChange={(surface_color) => onChange({ extras: { ...value.extras, surface_color } })} />
                                            <ColorControl label="Badge" value={value.extras.badge_background_color} onChange={(badge_background_color) => onChange({ extras: { ...value.extras, badge_background_color } })} />
                                            <ColorControl label="Teks badge" value={value.extras.badge_text_color} onChange={(badge_text_color) => onChange({ extras: { ...value.extras, badge_text_color } })} />
                                            <ColorControl label="Hubungi Aku" value={value.extras.contact_button_color} onChange={(contact_button_color) => onChange({ extras: { ...value.extras, contact_button_color } })} />
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <Field label="Font toko"><Select value={value.font_family} onChange={(event) => onChange({ font_family: event.target.value })}><optgroup label="Modern"><option value="jakarta">Plus Jakarta Sans</option><option value="inter">Inter</option><option value="manrope">Manrope</option><option value="dm-sans">DM Sans</option><option value="outfit">Outfit</option><option value="sora">Sora</option><option value="space">Space Grotesk</option><option value="poppins">Poppins</option><option value="nunito">Nunito</option></optgroup><optgroup label="Editorial"><option value="playfair">Playfair Display</option><option value="lora">Lora</option></optgroup><optgroup label="Cepat & universal"><option value="system">System UI</option></optgroup></Select></Field>
                                    <div className="rounded-2xl border border-line bg-surface-2 p-4" style={{ fontFamily: FONT_PREVIEW_STACKS[value.font_family] ?? FONT_PREVIEW_STACKS.jakarta }}><p className="text-[11px] font-bold uppercase tracking-[.06em] text-muted">Contoh font</p><p className="mt-2 text-xl font-semibold leading-tight">Tokomu, karaktermu.</p><p className="mt-1 text-xs text-muted">Produk terbaik layak tampil meyakinkan.</p></div>
                                    <ChoiceControl icon={<Paintbrush className="size-4" />} label="Bentuk tombol" value={value.button_style} options={[['rounded', 'Modern'], ['pill', 'Pill'], ['square', 'Tegas']]} onChange={(button_style) => onChange({ button_style: button_style as ThemeDraft['button_style'] })} />
                                    <ChoiceControl icon={<Type className="size-4" />} label="Gaya kartu" value={value.card_style} options={[['soft', 'Soft'], ['outline', 'Garis'], ['flat', 'Flat']]} onChange={(card_style) => onChange({ card_style: card_style as ThemeDraft['card_style'] })} />
                                    <ChoiceControl icon={<MoveVertical className="size-4" />} label="Jarak antarbagian" value={value.extras.spacing} options={[['compact', 'Rapat'], ['balanced', 'Nyaman'], ['airy', 'Lega']]} onChange={(spacing) => onChange({ extras: { ...value.extras, spacing: spacing as ThemeDraft['extras']['spacing'] } })} />
                                    <ChoiceControl icon={<Eye className="size-4" />} label="Mode warna" value={value.color_scheme} options={[['light', 'Terang'], ['dark', 'Gelap']]} onChange={(color_scheme) => onChange({ color_scheme: color_scheme as ThemeDraft['color_scheme'] })} />
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside className="border-t border-line bg-surface-2 p-5 lg:border-l lg:border-t-0 lg:p-6">
                        <div className="lg:sticky lg:top-6">
                            <p className="text-xs font-semibold uppercase tracking-[.06em] text-muted">Preview gaya</p>
                            <div className="mt-3 overflow-hidden rounded-[1.5rem] border-4 border-slate-900 shadow-lift" style={{ background: backgroundPreview || '#F6F7FB' }}>
                                <div className="h-28 p-4" style={{ background: `linear-gradient(135deg, ${value.primary_color}, ${value.accent_color})` }}><div className="h-2 w-20 rounded-full bg-white/80" /><div className="mt-3 h-2 w-32 rounded-full bg-white/40" /></div>
                                <div className="p-4">
                                    <div className={cn('p-4', value.card_style === 'soft' ? 'rounded-2xl shadow-lg' : value.card_style === 'outline' ? 'rounded-2xl border border-slate-300' : 'rounded-2xl')} style={{ background: value.extras.surface_color }}><div className="h-3 w-28 rounded-full bg-slate-900" /><div className="mt-2 h-2 w-full rounded-full bg-slate-300" /><div className="mt-1.5 h-2 w-2/3 rounded-full bg-slate-200" /><div className="mt-3 flex gap-2"><span className="rounded-full px-2 py-1 text-[8px] font-bold" style={{ background: value.extras.badge_background_color, color: value.extras.badge_text_color }}>Terverifikasi</span><span className="rounded-full px-2 py-1 text-[8px] font-bold" style={{ background: value.extras.badge_background_color, color: value.extras.badge_text_color }}>3 produk</span></div><div className={cn('mt-4 grid h-9 place-items-center text-[11px] font-bold', value.button_style === 'pill' ? 'rounded-full' : value.button_style === 'square' ? 'rounded-lg' : 'rounded-xl')} style={{ background: value.extras.contact_button_color, color: readablePreview(value.extras.contact_button_color) }}>Hubungi Aku</div></div>
                                    <div className={cn('grid grid-cols-2 gap-2', value.extras.spacing === 'compact' ? 'mt-2' : value.extras.spacing === 'airy' ? 'mt-6' : 'mt-4')}><div className="h-24 rounded-xl shadow-sm" style={{ background: `${value.extras.surface_color}E6` }} /><div className="h-24 rounded-xl shadow-sm" style={{ background: `${value.extras.surface_color}E6` }} /></div>
                                </div>
                            </div>
                            <div className="mt-4 rounded-xl border border-line bg-surface p-3 text-xs leading-5 text-muted"><b className="text-fg">Layout tidak berubah.</b> Produk, urutan block, dan isi tokomu tetap aman saat mengganti gaya.</div>
                        </div>
                    </aside>
                </div>

                <div className="sticky bottom-0 flex flex-col-reverse gap-3 border-t border-line bg-surface/95 px-5 py-4 backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <SaveIndicator state={saveState} />
                    <div className="flex gap-2 sm:ml-auto"><Button variant="outline" onClick={onClose}>Batal</Button><Button variant="gradient" onClick={onSave} disabled={saveState === 'saving' || (value.background_type === 'image' && !value.background_value)}>{saveState === 'saving' ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />} Simpan gaya</Button></div>
                </div>
            </div>
        </div>
    );
}

function ColorControl({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
    return <label className="block"><span className="mb-2 block text-xs font-bold">{label}</span><span className="flex h-11 items-center gap-2 rounded-xl border border-line bg-surface px-2"><input type="color" value={value} onChange={(event) => onChange(event.target.value.toUpperCase())} className="size-7 cursor-pointer rounded-lg border-0 bg-transparent p-0" /><span className="text-xs font-semibold uppercase text-muted">{value}</span></span></label>;
}

function ChoiceControl({ icon, label, value, options, onChange }: { icon: ReactNode; label: string; value: string; options: [string, string][]; onChange: (value: string) => void }) {
    return <div><p className="mb-2 flex items-center gap-2 text-xs font-bold">{icon}{label}</p><div className="grid grid-cols-3 gap-1 rounded-xl bg-surface-2 p-1">{options.map(([key, text]) => <button key={key} type="button" onClick={() => onChange(key)} className={cn('rounded-lg px-2 py-2 text-[11px] font-bold transition', value === key ? 'bg-surface text-fg shadow-sm' : 'text-muted')}>{text}</button>)}</div></div>;
}

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


const BLOCK_VISUALS: Record<string, { description: string; icon: ReactNode; tone: string }> = {
    HEADING: { description: 'Judul untuk membuka sebuah bagian.', icon: <Heading1 className="size-5" />, tone: 'violet' },
    TEXT: { description: 'Paragraf, deskripsi, atau cerita brand.', icon: <Type className="size-5" />, tone: 'violet' },
    LINK_BUTTON: { description: 'Arahkan pengunjung ke tautan tertentu.', icon: <Link2 className="size-5" />, tone: 'slate' },
    SOCIAL_LINKS: { description: 'Hubungkan semua akun sosialmu.', icon: <Share2 className="size-5" />, tone: 'slate' },
    IMAGE: { description: 'Tampilkan satu visual unggulan.', icon: <ImageIcon className="size-5" />, tone: 'violet' },
    GALLERY: { description: 'Susun beberapa gambar dalam galeri.', icon: <GalleryHorizontal className="size-5" />, tone: 'violet' },
    VIDEO: { description: 'Sematkan video dari YouTube.', icon: <Video className="size-5" />, tone: 'violet' },
    DIVIDER: { description: 'Pisahkan bagian dengan garis rapi.', icon: <MoveVertical className="size-5" />, tone: 'slate' },
    SPACER: { description: 'Beri ruang antarbagian halaman.', icon: <MoveVertical className="size-5" />, tone: 'slate' },
    FAQ: { description: 'Jawab pertanyaan umum calon pembeli.', icon: <CircleHelp className="size-5" />, tone: 'slate' },
    TESTIMONIAL: { description: 'Bangun kepercayaan lewat ulasan.', icon: <MessageSquareQuote className="size-5" />, tone: 'slate' },
    COUNTDOWN: { description: 'Ciptakan urgensi untuk promo.', icon: <Timer className="size-5" />, tone: 'rose' },
    PROMO_BANNER: { description: 'Sorot penawaran atau pengumuman.', icon: <Megaphone className="size-5" />, tone: 'rose' },
    LEAD_FORM: { description: 'Kumpulkan email dan kontak audiens.', icon: <FileText className="size-5" />, tone: 'rose' },
    WHATSAPP_CTA: { description: 'Buka percakapan WhatsApp langsung.', icon: <MessageCircle className="size-5" />, tone: 'rose' },
    PRODUCT: { description: 'Tampilkan satu produk secara fokus.', icon: <Package className="size-5" />, tone: 'emerald' },
    PRODUCT_COLLECTION: { description: 'Bangun etalase beberapa produk.', icon: <LayoutGrid className="size-5" />, tone: 'emerald' },
    FEATURED_PRODUCTS: { description: 'Sorot produk pilihan atau terlaris.', icon: <ShoppingBag className="size-5" />, tone: 'emerald' },
    AFFILIATE_PRODUCT: { description: 'Rekomendasikan produk marketplace.', icon: <BadgeDollarSign className="size-5" />, tone: 'emerald' },
    ARTICLE: { description: 'Bagikan artikel atau insight singkat.', icon: <FileText className="size-5" />, tone: 'violet' },
    EMBED: { description: 'Sematkan konten dari platform lain.', icon: <Code2 className="size-5" />, tone: 'slate' },
};

const SHOWCASE_VISUALS: Record<string, { description: string; icon: ReactNode; tone: string }> = {
    CAROUSEL: { description: 'Slide gambar yang bisa digeser.', icon: <GalleryHorizontalEnd className="size-5" />, tone: 'amber' },
    MARQUEE: { description: 'Teks berjalan untuk promo atau pengumuman.', icon: <MoveHorizontal className="size-5" />, tone: 'amber' },
    STATS: { description: 'Pamerkan angka: pembeli, rating, pengalaman.', icon: <TrendingUp className="size-5" />, tone: 'emerald' },
    LOGO_CLOUD: { description: 'Deretan logo brand yang pernah kerja sama.', icon: <Building2 className="size-5" />, tone: 'slate' },
    BEFORE_AFTER: { description: 'Geser untuk bandingkan hasil kerjamu.', icon: <SlidersHorizontal className="size-5" />, tone: 'rose' },
    STEPS: { description: 'Jelaskan alur pesan atau cara kerjanya.', icon: <ListOrdered className="size-5" />, tone: 'violet' },
};

const BLOCK_TONES: Record<string, string> = {
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    slate: 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300',
};

function BlockPicker({
    groups,
    onPick,
    onClose,
}: {
    groups: Record<string, { value: string; label: string; group: string }[]>;
    onPick: (type: string) => void;
    onClose: () => void;
}) {
    const [activeGroup, setActiveGroup] = useState('Semua');
    const entries = Object.entries(groups);
    const visibleGroups = activeGroup === 'Semua' ? entries : entries.filter(([group]) => group === activeGroup);

    return (
        <div
            className="fixed inset-0 z-[90] grid place-items-center bg-black/50 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="picker-title"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <Card className="max-h-[88vh] w-full max-w-3xl animate-rise overflow-y-auto">
                <div className="sticky top-0 z-10 border-b border-line bg-surface/95 p-5 backdrop-blur-xl sm:p-6">
                    <div className="flex items-start justify-between gap-4">
                    <div><p className="text-[11px] font-semibold uppercase tracking-[.06em] text-violet-600">Koleksi block</p><h2 id="picker-title" className="mt-1 text-xl font-bold tracking-tight">Tambahkan bagian baru</h2><p className="mt-1 text-sm text-muted">Pilih berdasarkan tujuan. Isinya bisa kamu ubah setelah ditambahkan.</p></div>
                    <Button variant="ghost" size="icon" onClick={onClose} aria-label="Tutup">
                        <X className="size-5" />
                    </Button>
                    </div>
                    <div className="mt-5 flex gap-1 overflow-x-auto rounded-xl bg-surface-2 p-1 [scrollbar-width:none]">
                        {['Semua', ...entries.map(([group]) => group)].map((group) => <button key={group} type="button" onClick={() => setActiveGroup(group)} className={cn('shrink-0 rounded-lg px-3 py-2 text-xs font-bold transition', activeGroup === group ? 'bg-surface text-fg shadow-sm' : 'text-muted hover:text-fg')}>{group}</button>)}
                    </div>
                </div>

                <div className="space-y-7 p-5 sm:p-6">
                    {visibleGroups.map(([group, items]) => (
                        <div key={group}>
                            <div className="mb-3 flex items-center justify-between"><p className="text-xs font-semibold uppercase tracking-[.06em] text-muted">{group}</p><span className="text-[11px] font-bold text-muted">{items.length} pilihan</span></div>
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {items.map((item) => {
                                    const visual = BLOCK_VISUALS[item.value] ?? SHOWCASE_VISUALS[item.value] ?? { description: 'Tambahkan bagian baru ke tokomu.', icon: <Plus className="size-5" />, tone: 'slate' };
                                    return <button key={item.value} type="button" onClick={() => onPick(item.value)} className="group flex items-start gap-3 rounded-2xl border border-line bg-surface p-3.5 text-left transition duration-200 hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-[0_10px_28px_rgba(16,24,40,.08)]"><span className={cn('grid size-11 shrink-0 place-items-center rounded-xl', BLOCK_TONES[visual.tone])}>{visual.icon}</span><span className="min-w-0 flex-1"><b className="flex items-center justify-between gap-2 text-sm">{item.label}<Plus className="size-4 text-muted transition group-hover:rotate-90 group-hover:text-violet-600" /></b><small className="mt-1 block text-xs font-normal leading-5 text-muted">{visual.description}</small></span></button>;
                                })}
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
        style: (block.style ?? {}) as BlockStyleTokens,
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

                <BlockStylePanel value={draft.style} onChange={(style) => patch({ style })} />

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

        case 'CAROUSEL': {
            const slides = (content.slides ?? []) as { image?: string; title?: string; subtitle?: string; url?: string }[];
            const updateSlide = (index: number, updates: Record<string, string>) => {
                onChange({ slides: slides.map((slide, slideIndex) => slideIndex === index ? { ...slide, ...updates } : slide) });
            };
            const moveSlide = (index: number, direction: -1 | 1) => {
                const target = index + direction;
                if (target < 0 || target >= slides.length) return;
                const next = [...slides];
                [next[index], next[target]] = [next[target], next[index]];
                onChange({ slides: next });
            };

            return (
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field label="Rasio carousel">
                            <Select value={content.aspect ?? 'wide'} onChange={(event) => onChange({ aspect: event.target.value })}>
                                <option value="wide">Lebar 16:9</option>
                                <option value="square">Kotak 1:1</option>
                                <option value="tall">Vertikal 3:4</option>
                            </Select>
                        </Field>
                        <div className="flex items-end rounded-xl border border-line px-3 py-2">
                            <Switch
                                checked={content.autoplay ?? true}
                                onChange={(checked) => onChange({ autoplay: checked })}
                                label="Putar otomatis"
                                description="Geser slide secara otomatis di etalase."
                            />
                        </div>
                    </div>

                    {slides.map((slide, index) => (
                        <div key={index} className="rounded-2xl border border-line bg-surface-2 p-4">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold">Slide {index + 1}</p>
                                <div className="flex gap-1">
                                    <button type="button" onClick={() => moveSlide(index, -1)} disabled={index === 0} className="grid size-8 place-items-center rounded-lg border border-line bg-surface disabled:opacity-35" aria-label="Geser slide ke kiri"><ChevronUp className="size-4 -rotate-90" /></button>
                                    <button type="button" onClick={() => moveSlide(index, 1)} disabled={index === slides.length - 1} className="grid size-8 place-items-center rounded-lg border border-line bg-surface disabled:opacity-35" aria-label="Geser slide ke kanan"><ChevronDown className="size-4 -rotate-90" /></button>
                                    <button type="button" onClick={() => onChange({ slides: slides.filter((_, slideIndex) => slideIndex !== index) })} className="grid size-8 place-items-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600" aria-label="Hapus slide"><Trash2 className="size-4" /></button>
                                </div>
                            </div>
                            <MediaPicker
                                label="Gambar slide"
                                value={slide.image ?? ''}
                                onChange={(image) => updateSlide(index, { image })}
                                hint="Upload gambar atau pakai URL. Rekomendasi minimal 1200 px."
                            />
                            <div className="mt-3 grid gap-3">
                                <Field label="Judul (opsional)"><Input value={slide.title ?? ''} onChange={(event) => updateSlide(index, { title: event.target.value })} /></Field>
                                <Field label="Keterangan (opsional)"><Input value={slide.subtitle ?? ''} onChange={(event) => updateSlide(index, { subtitle: event.target.value })} /></Field>
                                <Field label="Link tujuan (opsional)"><Input type="url" placeholder="https://" value={slide.url ?? ''} onChange={(event) => updateSlide(index, { url: event.target.value })} /></Field>
                            </div>
                        </div>
                    ))}

                    {slides.length < 8 && (
                        <Button type="button" variant="outline" block onClick={() => onChange({ slides: [...slides, { image: '', title: '', subtitle: '', url: '' }] })}>
                            <ImageIcon className="size-4" /> Tambah slide gambar
                        </Button>
                    )}
                    {slides.length === 0 && <p className="rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">Carousel masih kosong. Tambahkan minimal satu slide gambar.</p>}
                </div>
            );
        }

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

        /* Showcase blocks ship with a filled-in example, so a creator sees the
           shape immediately instead of an empty box they have to guess at. */
        case 'CAROUSEL':
            return { slides: [{ image: '', title: '', subtitle: '' }], aspect: 'wide', autoplay: true };
        case 'MARQUEE':
            return { items: ['Gratis ongkir', 'Dikirim hari ini', 'Garansi 7 hari'], speed: 'normal' };
        case 'STATS':
            return {
                stats: [
                    { value: '1200', suffix: '+', label: 'Pembeli puas' },
                    { value: '4.9', label: 'Rating rata-rata' },
                    { value: '3', suffix: ' th', label: 'Pengalaman' },
                ],
            };
        case 'LOGO_CLOUD':
            return { logos: [{ image: '', name: 'Brand A' }], grayscale: true };
        case 'BEFORE_AFTER':
            return { before: '', after: '', before_label: 'Sebelum', after_label: 'Sesudah' };
        case 'STEPS':
            return {
                layout: 'vertical',
                steps: [
                    { title: 'Pilih produk', description: 'Cek katalog dan pilih yang kamu mau.' },
                    { title: 'Bayar', description: 'Scan QRIS atau transfer, bebas pilih.' },
                    { title: 'Terima', description: 'File langsung masuk ke emailmu.' },
                ],
            };
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
