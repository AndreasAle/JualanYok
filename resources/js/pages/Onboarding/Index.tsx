import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRight, BadgePercent, BriefcaseBusiness, Check, CheckCircle2, CircleDot,
    GraduationCap, PackageOpen, Rocket, ShieldCheck, ShoppingBag, Sparkles, Store,
} from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { TemplateShowcasePreview } from '@/components/template-showcase-preview';
import { ThemeToggle } from '@/components/theme-toggle';
import { Badge, Button, Field, Input, Textarea } from '@/components/ui';
import { Logo } from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';

interface Goal { key: string; label: string; description: string }
interface Template {
    slug: string; name: string; tagline: string | null; use_case: string | null;
    is_premium: boolean; theme: Record<string, string> | null; blocks: string[];
}

const STEPS = [
    { label: 'Arah bisnis', short: 'Bisnis', description: 'Jenis usaha dan bidangmu' },
    { label: 'Tampilan toko', short: 'Template', description: 'Fondasi desain yang sesuai' },
    { label: 'Identitas toko', short: 'Profil', description: 'Nama, alamat, dan ceritamu' },
    { label: 'Review & mulai', short: 'Selesai', description: 'Periksa sebelum membuat produk' },
];

const GOAL_ICONS: Record<string, ReactNode> = {
    digital: <PackageOpen />, service: <BriefcaseBusiness />, course: <GraduationCap />,
    physical: <ShoppingBag />, affiliate: <BadgePercent />,
};

export default function Onboarding({ goals, niches, templates, suggestedUsername, selectedTemplate }: {
    goals: Goal[]; niches: string[]; templates: Template[]; suggestedUsername: string; selectedTemplate?: string | null;
}) {
    const [step, setStep] = useState(0);
    const { data, setData, post, processing, errors } = useForm({
        goal: '', niche: '', template: selectedTemplate ?? '', store_name: '', username: suggestedUsername,
        tagline: '', bio: '', whatsapp: '', socials: {} as Record<string, string>, publish: false as boolean,
    });
    const serverErrors = errors as Record<string, string | undefined>;
    const selectedGoal = goals.find((goal) => goal.key === data.goal);
    const selectedTemplateData = templates.find((template) => template.slug === data.template);
    const canAdvance = [
        Boolean(data.goal && data.niche), Boolean(data.template),
        data.store_name.trim().length >= 2 && data.username.trim().length >= 3, true,
    ][step];

    const rankedTemplates = useMemo(() => {
        const match: Record<string, string> = {
            digital: 'produk digital', service: 'jasa & konsultasi', course: 'kelas online',
            physical: 'produk fisik', affiliate: 'affiliate',
        };
        const preferred = match[data.goal];
        if (!preferred) return templates;
        return [...templates].sort((a, b) => Number(b.use_case === preferred) - Number(a.use_case === preferred));
    }, [data.goal, templates]);

    const move = (direction: -1 | 1) => {
        if (direction === 1 && !canAdvance) return;
        setStep((current) => Math.max(0, Math.min(STEPS.length - 1, current + direction)));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <div className="min-h-screen overflow-x-hidden bg-[#f4f4f7] text-fg dark:bg-[#111116]">
            <Head title="Siapkan Toko" />
            <header className="sticky top-0 z-50 border-b border-black/[.06] bg-white/90 backdrop-blur-xl dark:border-white/10 dark:bg-[#15151b]/90">
                <div className="mx-auto flex h-[4.5rem] max-w-[1320px] items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Logo className="[&_img]:h-7" />
                    <div className="flex items-center gap-3">
                        <p className="hidden text-right text-[11px] leading-4 text-muted sm:block">Toko belum dipublikasikan<span className="block font-bold text-fg">Aman sebagai draft</span></p>
                        <ThemeToggle />
                    </div>
                </div>
            </header>

            <MobileProgress step={step} />
            <main className="mx-auto grid max-w-[1320px] gap-6 px-4 pb-32 pt-5 sm:px-6 sm:pt-7 lg:grid-cols-[330px_minmax(0,1fr)] lg:gap-8 lg:px-8 lg:pb-12 lg:pt-8">
                <OnboardingRail step={step} data={data} goal={selectedGoal} template={selectedTemplateData} />
                <section className="min-w-0 overflow-hidden rounded-[1.75rem] border border-black/[.055] bg-white shadow-[0_20px_70px_rgba(30,24,50,.07)] dark:border-white/10 dark:bg-[#1b1b22]">
                    <div className="border-b border-line px-5 py-5 sm:px-8 sm:py-6 lg:px-10">
                        <div className="flex items-center justify-between gap-4">
                            <div><p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-400">Langkah {step + 1} dari {STEPS.length}</p><p className="mt-1 text-xs text-muted">{STEPS[step].description}</p></div>
                            <span className="grid size-10 place-items-center rounded-xl bg-[#f0edff] text-sm font-black text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{String(step + 1).padStart(2, '0')}</span>
                        </div>
                    </div>

                    <div className="min-h-[570px] px-5 py-7 sm:px-8 sm:py-9 lg:px-10 lg:py-10">
                        <div key={step} className="animate-rise">
                            {step === 0 && <BusinessStep goals={goals} niches={niches} goal={data.goal} niche={data.niche} onGoal={(value) => setData('goal', value)} onNiche={(value) => setData('niche', value)} />}
                            {step === 1 && <TemplateStep templates={rankedTemplates} selected={data.template} onSelect={(value) => setData('template', value)} />}
                            {step === 2 && <IdentityStep data={data} errors={errors} template={selectedTemplateData} onChange={setData} />}
                            {step === 3 && <ReviewStep data={data} goal={selectedGoal} template={selectedTemplateData} serverError={serverErrors.plan} />}
                        </div>
                    </div>

                    <DesktopNavigation step={step} canAdvance={canAdvance} processing={processing} onBack={() => move(-1)} onNext={() => move(1)} onSubmit={() => post('/onboarding')} />
                </section>
            </main>
            <MobileNavigation step={step} canAdvance={canAdvance} processing={processing} onBack={() => move(-1)} onNext={() => move(1)} onSubmit={() => post('/onboarding')} />
        </div>
    );
}

function MobileProgress({ step }: { step: number }) {
    return <div className="border-b border-line bg-white px-4 py-3 dark:bg-[#18181f] lg:hidden"><ol className="mx-auto grid max-w-xl grid-cols-4 gap-1.5" aria-label="Progres menyiapkan toko">{STEPS.map((item, index) => <li key={item.label}><span className={cn('block h-1 rounded-full transition-colors', index <= step ? 'bg-violet-600' : 'bg-line')} /><span className={cn('mt-1.5 block truncate text-[9px] font-bold', index === step ? 'text-fg' : 'text-muted')}>{item.short}</span></li>)}</ol></div>;
}

function OnboardingRail({ step, data, goal, template }: {
    step: number; data: { niche: string; username: string }; goal?: Goal; template?: Template;
}) {
    return <aside className="sticky top-[6.5rem] hidden h-[calc(100vh-8.5rem)] min-h-[610px] flex-col overflow-hidden rounded-[1.75rem] bg-[#17171f] p-7 text-white shadow-[0_24px_70px_rgba(17,17,25,.18)] lg:flex">
        <div className="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-violet-500/15 blur-3xl" />
        <div className="relative"><span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[.06] px-3 py-1.5 text-[10px] font-black uppercase tracking-[.16em] text-violet-200"><Sparkles className="size-3.5" /> Setup pintar</span><h1 className="mt-5 text-[1.7rem] font-black leading-[1.12] tracking-[-.04em]">Toko siap jual dimulai dari fondasi yang tepat.</h1><p className="mt-3 text-sm leading-6 text-white/50">Kami siapkan struktur awalnya. Kamu tetap memegang kendali penuh setelah masuk dashboard.</p></div>
        <ol className="relative mt-8 space-y-1" aria-label="Langkah onboarding">{STEPS.map((item, index) => {
            const complete = index < step; const active = index === step;
            return <li key={item.label} className={cn('relative flex gap-3 rounded-2xl p-3 transition-colors', active && 'bg-white/[.075]')} aria-current={active ? 'step' : undefined}><span className={cn('relative z-10 grid size-8 shrink-0 place-items-center rounded-full border text-[10px] font-black', complete ? 'border-emerald-400 bg-emerald-400 text-[#112219]' : active ? 'border-violet-400 bg-violet-500 text-white' : 'border-white/15 text-white/35')}>{complete ? <Check className="size-3.5" /> : index + 1}</span><span className="min-w-0 pt-0.5"><b className={cn('block text-xs', active || complete ? 'text-white' : 'text-white/35')}>{item.label}</b><small className={cn('mt-0.5 block text-[10px]', active ? 'text-white/50' : 'text-white/25')}>{item.description}</small></span></li>;
        })}</ol>
        <div className="relative mt-auto rounded-2xl border border-white/10 bg-white/[.045] p-4"><p className="text-[9px] font-black uppercase tracking-[.16em] text-white/35">Pilihanmu</p><div className="mt-3 space-y-2 text-xs"><SummaryLine label="Bisnis" value={goal?.label} /><SummaryLine label="Bidang" value={data.niche} /><SummaryLine label="Template" value={template?.name} /><SummaryLine label="Alamat" value={data.username ? `/${data.username}` : undefined} /></div></div>
    </aside>;
}

function SummaryLine({ label, value }: { label: string; value?: string }) {
    return <div className="flex items-center justify-between gap-3"><span className="text-white/35">{label}</span><span className={cn('max-w-[165px] truncate text-right font-bold', value ? 'text-white/80' : 'text-white/20')}>{value || 'Belum dipilih'}</span></div>;
}

function BusinessStep({ goals, niches, goal, niche, onGoal, onNiche }: {
    goals: Goal[]; niches: string[]; goal: string; niche: string; onGoal: (value: string) => void; onNiche: (value: string) => void;
}) {
    return <div><StepHeading eyebrow="Mulai dari model bisnismu" title="Apa yang ingin kamu jual?" description="Jawabanmu membantu kami mengurutkan template dan fitur yang paling relevan." />
        <div className="mt-7 grid gap-3 sm:grid-cols-2">{goals.map((item) => {
            const active = goal === item.key;
            return <button key={item.key} type="button" onClick={() => onGoal(item.key)} aria-pressed={active} className={cn('group relative flex min-h-[106px] items-start gap-4 rounded-2xl border p-4 text-left transition duration-200 sm:p-5', active ? 'border-violet-500 bg-violet-50 shadow-[0_10px_30px_rgba(124,58,237,.1)] ring-1 ring-violet-500 dark:bg-violet-500/10' : 'border-line bg-white hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-lg dark:bg-[#202028] dark:hover:border-violet-500/30')}><span className={cn('grid size-10 shrink-0 place-items-center rounded-xl transition [&>svg]:size-5', active ? 'bg-violet-600 text-white' : 'bg-surface-2 text-muted group-hover:text-violet-600')}>{GOAL_ICONS[item.key] ?? <Store />}</span><span className="min-w-0"><b className="block text-sm font-extrabold">{item.label}</b><span className="mt-1.5 block text-xs leading-5 text-muted">{item.description}</span></span>{active && <CheckCircle2 className="absolute right-3 top-3 size-4 fill-violet-600 text-white" />}</button>;
        })}</div>
        <div className="mt-8 border-t border-line pt-7"><div className="flex flex-wrap items-end justify-between gap-2"><div><h3 className="text-base font-extrabold">Bidang utamamu</h3><p className="mt-1 text-xs text-muted">Pilih yang paling dekat. Ini bisa diubah nanti.</p></div>{niche && <span className="text-[10px] font-bold text-emerald-600">Pilihan tersimpan</span>}</div><div className="mt-4 flex flex-wrap gap-2">{niches.map((item) => <button key={item} type="button" onClick={() => onNiche(item)} aria-pressed={niche === item} className={cn('rounded-full border px-3.5 py-2 text-xs font-bold transition sm:px-4', niche === item ? 'border-[#171722] bg-[#171722] text-white dark:border-white dark:bg-white dark:text-[#171722]' : 'border-line bg-surface text-muted hover:border-violet-300 hover:text-fg')}>{item}</button>)}</div></div>
    </div>;
}

function TemplateStep({ templates, selected, onSelect }: { templates: Template[]; selected: string; onSelect: (value: string) => void }) {
    return <div><StepHeading eyebrow="Toko, bukan sekadar warna" title="Pilih fondasi yang paling dekat." description="Setiap pilihan memiliki struktur produk dan alur belanja berbeda. Semua kontennya tetap bisa kamu ubah." />
        <div className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{templates.map((template, index) => {
            const active = selected === template.slug; const primary = template.theme?.primary_color ?? '#7C3AED'; const accent = template.theme?.accent_color ?? '#FB7185';
            return <button key={template.slug} type="button" onClick={() => onSelect(template.slug)} aria-pressed={active} className={cn('group overflow-hidden rounded-[1.35rem] border bg-surface text-left transition duration-300', active ? 'border-violet-500 shadow-[0_18px_45px_rgba(124,58,237,.14)] ring-2 ring-violet-500' : 'border-line hover:-translate-y-1 hover:shadow-xl')}><div className="relative overflow-hidden bg-[#f1f1f5] p-3 dark:bg-[#15151b]"><TemplateShowcasePreview slug={template.slug} primary={primary} accent={accent} display="catalog" className="aspect-[4/3] rounded-xl transition duration-500 group-hover:scale-[1.02]" /><div className="absolute left-5 top-5 flex gap-1.5">{index === 0 && <span className="rounded-full bg-[#171722] px-2.5 py-1 text-[8px] font-black uppercase tracking-wide text-white">Direkomendasikan</span>}{template.is_premium && <span className="rounded-full bg-white px-2.5 py-1 text-[8px] font-black text-violet-700 shadow-sm">Premium</span>}</div>{active && <span className="absolute bottom-5 right-5 grid size-8 place-items-center rounded-full bg-violet-600 text-white shadow-lg"><Check className="size-4" /></span>}</div><div className="p-4"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-extrabold">{template.name}</p><p className="mt-1 line-clamp-2 min-h-9 text-[11px] leading-[1.1rem] text-muted">{template.tagline}</p></div><ArrowRight className="mt-0.5 size-4 shrink-0 text-muted transition group-hover:translate-x-0.5" /></div><div className="mt-3 flex items-center justify-between border-t border-line pt-3 text-[9px] font-bold text-muted"><span>{template.use_case}</span><span>{template.blocks.length} bagian</span></div></div></button>;
        })}</div><p className="mt-5 flex items-center gap-2 text-xs text-muted"><CircleDot className="size-3.5 text-violet-500" /> Urutan rekomendasi disesuaikan dengan jawaban bisnis sebelumnya.</p>
    </div>;
}

function IdentityStep({ data, errors, template, onChange }: {
    data: { store_name: string; username: string; tagline: string; bio: string; whatsapp: string };
    errors: Record<string, string | undefined>; template?: Template;
    onChange: (key: 'store_name' | 'username' | 'tagline' | 'bio' | 'whatsapp', value: string) => void;
}) {
    const primary = template?.theme?.primary_color ?? '#7C3AED';
    return <div><StepHeading eyebrow="Buat mudah dikenali" title="Beri identitas pada tokomu." description="Gunakan nama yang ringkas dan jelaskan manfaat utama yang pembeli dapatkan." />
        <div className="mt-7 grid gap-8 xl:grid-cols-[minmax(0,1fr)_310px] xl:items-start"><div className="space-y-5">
            <Field label="Nama toko" required error={errors.store_name} hint="Nama brand atau nama profesionalmu." htmlFor="store_name"><Input id="store_name" value={data.store_name} onChange={(event) => onChange('store_name', event.target.value)} invalid={Boolean(errors.store_name)} placeholder="Contoh: RuangKarya" autoFocus /></Field>
            <Field label="Alamat toko" required error={errors.username} hint="Huruf kecil, angka, titik, garis bawah, atau strip." htmlFor="username"><div className="relative"><span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-muted">jualanyok.id/</span><Input id="username" value={data.username} onChange={(event) => onChange('username', event.target.value.toLowerCase().replace(/\s+/g, ''))} invalid={Boolean(errors.username)} className="pl-[96px]" spellCheck={false} /></div></Field>
            <Field label="Tagline" error={errors.tagline} hint={`${data.tagline.length}/160 karakter`} htmlFor="tagline"><Input id="tagline" value={data.tagline} maxLength={160} onChange={(event) => onChange('tagline', event.target.value)} placeholder="Bantu kreator bekerja lebih konsisten" /></Field>
            <Field label="Deskripsi singkat" error={errors.bio} hint={`${data.bio.length}/500 karakter`} htmlFor="bio"><Textarea id="bio" rows={4} maxLength={500} value={data.bio} onChange={(event) => onChange('bio', event.target.value)} placeholder="Ceritakan produk, layanan, dan alasan orang perlu memilihmu." /></Field>
            <Field label="WhatsApp bisnis" error={errors.whatsapp} hint="Opsional — untuk pertanyaan sebelum pembelian." htmlFor="whatsapp"><Input id="whatsapp" type="tel" value={data.whatsapp} onChange={(event) => onChange('whatsapp', event.target.value)} placeholder="08xxxxxxxxxx" /></Field>
        </div><div className="xl:sticky xl:top-28"><p className="mb-3 text-[10px] font-black uppercase tracking-[.16em] text-muted">Pratinjau identitas</p><div className="overflow-hidden rounded-[1.5rem] border border-line bg-[#f7f7fa] p-3 shadow-[0_18px_45px_rgba(30,24,50,.08)] dark:bg-[#15151b]"><div className="h-28 rounded-[1.1rem]" style={{ backgroundColor: primary }} /><div className="relative -mt-8 mx-2 rounded-[1.2rem] border border-line bg-surface p-4 shadow-md"><span className="grid size-14 place-items-center rounded-2xl border-4 border-surface text-xl font-black text-white" style={{ backgroundColor: primary }}>{(data.store_name || 'J')[0].toUpperCase()}</span><div className="mt-3 flex items-center gap-1.5"><p className="truncate font-black">{data.store_name || 'Nama tokomu'}</p><CheckCircle2 className="size-4 shrink-0 fill-violet-600 text-white" /></div><p className="mt-0.5 truncate text-[10px] font-semibold text-muted">@{data.username || 'alamat-toko'}</p><p className="mt-3 text-xs font-bold leading-5">{data.tagline || 'Tagline utama akan tampil di sini.'}</p><p className="mt-1.5 line-clamp-3 text-[10px] leading-4 text-muted">{data.bio || 'Deskripsi singkat membantu pengunjung memahami isi dan nilai tokomu.'}</p><div className="mt-4 h-8 rounded-lg" style={{ backgroundColor: primary }} /></div></div><p className="mt-3 text-center text-[10px] leading-4 text-muted">Produk ditambahkan setelah onboarding.</p></div></div>
    </div>;
}

function ReviewStep({ data, goal, template, serverError }: {
    data: { store_name: string; username: string; niche: string; tagline: string }; goal?: Goal; template?: Template; serverError?: string;
}) {
    const primary = template?.theme?.primary_color ?? '#7C3AED'; const accent = template?.theme?.accent_color ?? '#FB7185';
    return <div><StepHeading eyebrow="Satu pemeriksaan terakhir" title="Fondasi tokomu sudah siap." description="Kami membuat toko sebagai draft. Setelah produk pertama masuk, periksa pratinjau sebelum publikasi." />
        <div className="mt-7 grid gap-5 xl:grid-cols-[1.1fr_.9fr]"><div className="overflow-hidden rounded-[1.5rem] border border-line bg-surface shadow-sm"><div className="flex items-center gap-4 border-b border-line p-5 sm:p-6"><span className="grid size-12 shrink-0 place-items-center rounded-2xl text-lg font-black text-white" style={{ backgroundColor: primary }}>{data.store_name[0]?.toUpperCase() || 'J'}</span><div className="min-w-0"><div className="flex items-center gap-2"><h3 className="truncate text-lg font-black">{data.store_name}</h3><Badge tone="success">Draft aman</Badge></div><p className="mt-0.5 truncate text-xs text-muted">jualanyok.id/{data.username}</p></div></div><dl className="grid gap-px bg-line sm:grid-cols-2"><ReviewItem label="Model bisnis" value={goal?.label ?? '-'} /><ReviewItem label="Bidang" value={data.niche || '-'} /><ReviewItem label="Template" value={template?.name ?? '-'} /><ReviewItem label="Tagline" value={data.tagline || 'Belum diisi'} /></dl>{template && <div className="bg-[#f4f4f7] p-4 dark:bg-[#15151b]"><TemplateShowcasePreview slug={template.slug} primary={primary} accent={accent} display="catalog" className="aspect-[16/7] rounded-xl" /></div>}</div>
            <div className="rounded-[1.5rem] bg-[#17171f] p-5 text-white sm:p-6"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-violet-500"><Rocket className="size-5" /></span><div><p className="text-[10px] font-black uppercase tracking-[.14em] text-violet-300">Sesudah ini</p><h3 className="mt-0.5 text-base font-black">Tiga langkah menuju live</h3></div></div><ol className="mt-6 space-y-4">{[
                ['01', 'Buat produk pertama', 'Isi nama, harga, deskripsi, dan file produk.'], ['02', 'Periksa pratinjau', 'Pastikan tampilan mobile dan informasinya tepat.'], ['03', 'Publikasikan toko', 'Toko dapat diakses pembeli setelah kamu setujui.'],
            ].map(([number, title, description]) => <li key={number} className="flex gap-3"><span className="grid size-7 shrink-0 place-items-center rounded-full border border-white/15 text-[9px] font-black text-violet-300">{number}</span><span><b className="block text-xs">{title}</b><span className="mt-1 block text-[10px] leading-4 text-white/45">{description}</span></span></li>)}</ol><div className="mt-6 flex items-center gap-2 border-t border-white/10 pt-4 text-[10px] text-white/45"><ShieldCheck className="size-4 text-emerald-400" /> Tidak ada yang dipublikasikan otomatis.</div></div>
        </div>{serverError && <p className="mt-4 rounded-xl bg-rose-50 p-4 text-sm font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{serverError}</p>}
    </div>;
}

function ReviewItem({ label, value }: { label: string; value: string }) {
    return <div className="min-w-0 bg-surface p-4 sm:p-5"><dt className="text-[9px] font-black uppercase tracking-[.14em] text-muted">{label}</dt><dd className="mt-1.5 truncate text-xs font-extrabold">{value}</dd></div>;
}

function StepHeading({ eyebrow, title, description }: { eyebrow: string; title: string; description: string }) {
    return <div className="max-w-2xl"><p className="text-[10px] font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-400">{eyebrow}</p><h2 className="mt-3 text-balance text-2xl font-black leading-[1.15] tracking-[-.04em] sm:text-[2rem]">{title}</h2><p className="mt-3 max-w-xl text-sm leading-6 text-muted">{description}</p></div>;
}

interface NavigationProps { step: number; canAdvance: boolean; processing: boolean; onBack: () => void; onNext: () => void; onSubmit: () => void }

function DesktopNavigation({ step, canAdvance, processing, onBack, onNext, onSubmit }: NavigationProps) {
    return <div className="hidden items-center justify-between border-t border-line px-10 py-5 lg:flex"><Button variant="ghost" onClick={onBack} disabled={step === 0}><ArrowLeft /> Kembali</Button><div className="flex items-center gap-3"><span className="text-[10px] font-semibold text-muted">Semua dapat diubah nanti</span>{step < STEPS.length - 1 ? <Button size="lg" disabled={!canAdvance} onClick={onNext} className="rounded-xl bg-[#171722] px-7 text-white shadow-lg hover:bg-black dark:bg-white dark:text-[#171722]">Lanjutkan <ArrowRight /></Button> : <Button size="lg" loading={processing} onClick={onSubmit} className="rounded-xl bg-violet-600 px-7 text-white shadow-lg hover:bg-violet-700"><Rocket /> Buat toko & produk pertama</Button>}</div></div>;
}

function MobileNavigation({ step, canAdvance, processing, onBack, onNext, onSubmit }: NavigationProps) {
    return <div className="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-white/95 p-3 pb-[max(.75rem,env(safe-area-inset-bottom))] shadow-[0_-12px_35px_rgba(25,20,40,.08)] backdrop-blur-xl dark:bg-[#18181f]/95 lg:hidden"><div className="mx-auto flex max-w-xl items-center gap-2"><Button variant="outline" size="icon" onClick={onBack} disabled={step === 0} aria-label="Kembali"><ArrowLeft /></Button>{step < STEPS.length - 1 ? <Button block size="lg" disabled={!canAdvance} onClick={onNext} className="rounded-xl bg-[#171722] text-white hover:bg-black dark:bg-white dark:text-[#171722]">Lanjutkan <ArrowRight /></Button> : <Button block size="lg" loading={processing} onClick={onSubmit} className="rounded-xl bg-violet-600 text-white hover:bg-violet-700"><Rocket /> Buat toko</Button>}</div></div>;
}
