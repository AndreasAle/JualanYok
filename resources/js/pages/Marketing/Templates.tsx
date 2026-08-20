import { ArrowRight, Check, ChevronRight, Layers3, Palette, SlidersHorizontal, Sparkles, WandSparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PageCta, PageHero, Reveal } from '@/components/marketing-page';
import { TemplateShowcasePreview } from '@/components/template-showcase-preview';
import { Badge, ButtonLink } from '@/components/ui';
import MarketingLayout from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';

interface Template {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    use_case: string | null;
    is_premium: boolean;
    theme: Record<string, string> | null;
    block_count: number;
    blueprint: string[];
}

const BLOCK_LABELS: Record<string, string> = {
    HEADING: 'Headline', TEXT: 'Story', LINK_BUTTON: 'Link', SOCIAL_LINKS: 'Social', IMAGE: 'Image',
    GALLERY: 'Portfolio', VIDEO: 'Video', FAQ: 'FAQ', TESTIMONIAL: 'Testimoni', COUNTDOWN: 'Countdown',
    PROMO_BANNER: 'Promo', LEAD_FORM: 'Leads', WHATSAPP_CTA: 'WhatsApp', PRODUCT: 'Produk',
    PRODUCT_COLLECTION: 'Katalog', FEATURED_PRODUCTS: 'Best seller', AFFILIATE_PRODUCT: 'Affiliate', ARTICLE: 'Artikel',
};

const CATEGORY_LABELS: Record<string, string> = {
    'produk digital': 'Digital',
    'jasa & konsultasi': 'Jasa',
    'kelas online': 'Kelas',
    'produk fisik': 'Produk fisik',
    affiliate: 'Affiliate',
    'F&B': 'F&B',
    'link personal': 'Personal',
};

export default function Templates({ templates }: { templates: Template[] }) {
    const [category, setCategory] = useState('Semua');
    const categories = useMemo(() => ['Semua', ...Array.from(new Set(templates.map((template) => template.use_case).filter(Boolean) as string[]))], [templates]);
    const visible = category === 'Semua' ? templates : templates.filter((template) => template.use_case === category);

    return (
        <MarketingLayout title="Template storefront" description="Template storefront JualanYok yang dirancang berdasarkan cara jualan, bukan sekadar variasi warna.">
            <PageHero
                eyebrow="Template storefront terbaru"
                title={<>Mulai dengan desain yang sudah <span className="gradient-text">punya strategi.</span></>}
                description="Setiap template punya urutan cerita, penempatan produk, dan CTA yang berbeda sesuai cara kamu jualan. Pilih fondasinya, lalu bikin sepenuhnya milikmu."
            >
                <TemplateHeroVisual templates={templates.slice(0, 3)} />
            </PageHero>

            <section className="mx-auto max-w-6xl px-5 py-20 sm:px-6 lg:py-28">
                <Reveal>
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-2xl">
                            <p className="section-kicker">PILIH SESUAI BISNISMU</p>
                            <h2 className="section-title">Bukan satu desain yang dicat ulang tujuh kali.</h2>
                            <p className="section-body">Setiap preview adalah contoh toko jadi dengan brand, produk, harga, dan konten dummy sesuai jenis bisnisnya.</p>
                        </div>
                        <div className="flex items-center gap-2 text-xs font-bold text-muted"><SlidersHorizontal className="size-4" /> {visible.length} template ditemukan</div>
                    </div>
                </Reveal>

                <Reveal delay={80}>
                    <div className="mt-9 flex gap-2 overflow-x-auto pb-2 [scrollbar-width:none]">
                        {categories.map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setCategory(item)}
                                className={cn(
                                    'shrink-0 rounded-full border px-4 py-2 text-xs font-extrabold transition',
                                    category === item
                                        ? 'border-[#171722] bg-[#171722] text-white shadow-md'
                                        : 'border-line bg-surface text-muted hover:border-violet-300 hover:text-fg',
                                )}
                            >
                                {CATEGORY_LABELS[item] ?? item}
                            </button>
                        ))}
                    </div>
                </Reveal>

                <div className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {visible.map((template, index) => (
                        <Reveal key={template.slug} delay={(index % 3) * 80}>
                            <TemplateCard template={template} recommended={category === 'Semua' && index === 0} />
                        </Reveal>
                    ))}
                </div>
            </section>

            <HowTemplatesWork />
            <PageCta
                eyebrow="Tidak perlu mulai dari kanvas kosong"
                title="Pilih layoutnya. Isi ceritamu. Publish."
                description="Semua template tetap fleksibel—ubah warna, font, urutan block, produk, dan CTA kapan saja dari dashboard."
                secondaryHref="/features"
                secondaryLabel="Lihat semua fitur"
            />
        </MarketingLayout>
    );
}

function TemplateHeroVisual({ templates }: { templates: Template[] }) {
    return (
        <div className="relative mx-auto h-[390px] w-full max-w-xl sm:h-[430px]">
            {templates.map((template, index) => {
                const primary = template.theme?.primary_color ?? '#7C3AED';
                const accent = template.theme?.accent_color ?? '#FB7185';
                const positions = ['left-[2%] top-14 -rotate-6', 'left-1/2 top-2 z-20 -translate-x-1/2', 'right-[2%] top-16 rotate-6'];
                return (
                    <div key={template.slug} className={cn('absolute w-[43%] rounded-[1.4rem] border border-white/80 bg-white p-2 shadow-[0_24px_60px_rgba(67,38,120,.2)]', positions[index])}>
                        <div className="flex items-center gap-1 px-1 pb-2"><span className="size-1.5 rounded-full bg-rose-300" /><span className="size-1.5 rounded-full bg-amber-300" /><span className="size-1.5 rounded-full bg-emerald-300" /></div>
                        <TemplateShowcasePreview slug={template.slug} primary={primary} accent={accent} className="aspect-[3/4] rounded-xl" />
                        <p className="px-1 pb-1 pt-2 text-center text-[9px] font-extrabold text-neutral-800 sm:text-[10px]">{template.name}</p>
                    </div>
                );
            })}
            <div className="absolute bottom-2 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2 rounded-full border border-white/90 bg-white/90 px-4 py-2 text-[10px] font-extrabold text-neutral-800 shadow-xl backdrop-blur">
                <WandSparkles className="size-3.5 text-violet-600" /> 7 struktur siap pakai
            </div>
        </div>
    );
}

function TemplateCard({ template, recommended }: { template: Template; recommended: boolean }) {
    const primary = template.theme?.primary_color ?? '#7C3AED';
    const accent = template.theme?.accent_color ?? '#FB7185';
    const uniqueBlocks = Array.from(new Set(template.blueprint));

    return (
        <article className="group flex h-full flex-col overflow-hidden rounded-[1.65rem] border border-neutral-200/80 bg-surface shadow-soft transition duration-500 hover:-translate-y-1 hover:shadow-[0_24px_60px_rgba(28,20,46,.12)] dark:border-line">
            <div className="relative overflow-hidden p-6" style={{ background: `linear-gradient(145deg, ${primary}18, ${accent}28)` }}>
                <div className="absolute left-4 top-4 z-10 flex gap-2">
                    {recommended && <span className="rounded-full bg-[#171722] px-3 py-1 text-[9px] font-extrabold uppercase tracking-wide text-white">Rekomendasi</span>}
                    {template.is_premium && <span className="inline-flex items-center gap-1 rounded-full bg-white/90 px-3 py-1 text-[9px] font-extrabold text-violet-700 shadow-sm"><Sparkles className="size-3" /> Premium</span>}
                </div>
                <div className="mx-auto w-[68%] rounded-[1.35rem] border-[5px] border-[#181820] bg-[#181820] p-1.5 shadow-[0_24px_50px_rgba(28,20,46,.25)] transition duration-700 group-hover:-translate-y-1 group-hover:scale-[1.02]">
                    <div className="mx-auto mb-1 h-1 w-8 rounded-full bg-white/20" />
                    <TemplateShowcasePreview slug={template.slug} primary={primary} accent={accent} display="catalog" className="aspect-[9/14] rounded-[.8rem]" />
                </div>
                <div className="absolute bottom-4 right-4 flex gap-1.5 rounded-full border border-white/80 bg-white/85 p-2 shadow-sm backdrop-blur">
                    <span className="size-3.5 rounded-full border border-black/5" style={{ backgroundColor: primary }} />
                    <span className="size-3.5 rounded-full border border-black/5" style={{ backgroundColor: accent }} />
                </div>
            </div>

            <div className="flex flex-1 flex-col p-6">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-[9px] font-extrabold uppercase tracking-[.16em] text-violet-600">{template.use_case}</p>
                        <h2 className="mt-2 text-xl font-extrabold tracking-tight">{template.name}</h2>
                    </div>
                    <ChevronRight className="mt-1 size-5 text-muted transition-transform group-hover:translate-x-1" />
                </div>
                <p className="mt-2 text-sm font-semibold">{template.tagline}</p>
                <p className="mt-2 text-xs leading-6 text-muted">{template.description}</p>

                <div className="mt-5 flex flex-wrap gap-1.5">
                    {uniqueBlocks.slice(0, 5).map((type) => <Badge key={type} className="border border-line bg-surface-2 text-[9px]">{BLOCK_LABELS[type] ?? type}</Badge>)}
                    {uniqueBlocks.length > 5 && <Badge className="border border-line bg-surface-2 text-[9px]">+{uniqueBlocks.length - 5}</Badge>}
                </div>

                <div className="mt-6 grid grid-cols-3 gap-2 border-y border-line py-4 text-center">
                    <Meta icon={<Layers3 />} value={`${template.block_count} block`} />
                    <Meta icon={<Palette />} value={template.theme?.card_style ?? 'custom'} />
                    <Meta icon={<SlidersHorizontal />} value={template.theme?.button_style ?? 'flexible'} />
                </div>

                <ButtonLink href={`/templates/${template.slug}/demo`} block className="mt-5 rounded-full bg-[#171722] text-white hover:bg-black">
                    Lihat demo langsung <ArrowRight />
                </ButtonLink>
            </div>
        </article>
    );
}

function Meta({ icon, value }: { icon: React.ReactNode; value: string }) {
    return <div className="min-w-0"><span className="mx-auto block size-3.5 text-violet-500 [&>svg]:size-full">{icon}</span><p className="mt-1.5 truncate text-[9px] font-bold capitalize text-muted">{value}</p></div>;
}

function HowTemplatesWork() {
    const steps = [
        ['01', 'Pilih fondasi', 'Cocokkan dengan jenis produk dan cara kamu bercerita.'],
        ['02', 'Masukkan konten', 'Ganti teks, gambar, produk, warna, dan link sosialmu.'],
        ['03', 'Atur sesukamu', 'Tambah, hapus, atau pindahkan block tanpa batas template.'],
        ['04', 'Publikasikan', 'Toko langsung siap dibagikan dengan alamatmu sendiri.'],
    ];
    return (
        <section className="bg-[#f5f5f6] py-20 dark:bg-subtle lg:py-24">
            <div className="mx-auto max-w-6xl px-5 sm:px-6">
                <Reveal><div className="mx-auto max-w-2xl text-center"><p className="section-kicker">DARI TEMPLATE KE TOKO MILIKMU</p><h2 className="section-title">Empat langkah, tanpa terjebak desain dari nol.</h2></div></Reveal>
                <div className="mt-12 grid gap-px overflow-hidden rounded-[1.5rem] border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map(([number, title, body], index) => (
                        <Reveal key={number} delay={index * 70} className="h-full">
                            <div className="h-full bg-surface p-6"><span className="text-xs font-black tracking-[.18em] text-violet-400">{number}</span><h3 className="mt-8 font-extrabold">{title}</h3><p className="mt-2 text-xs leading-6 text-muted">{body}</p><Check className="mt-6 size-4 text-emerald-500" /></div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
