import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Rocket } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Card, Field, Input, Textarea } from '@/components/ui';
import { ThemeToggle } from '@/components/theme-toggle';
import { Logo } from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';

interface Goal {
    key: string;
    label: string;
    description: string;
}

interface Template {
    slug: string;
    name: string;
    tagline: string | null;
    use_case: string | null;
    is_premium: boolean;
    theme: Record<string, string> | null;
    blocks: string[];
}

const STEPS = ['Tujuan', 'Niche', 'Template', 'Profil', 'Selesai'];

export default function Onboarding({
    goals,
    niches,
    templates,
    suggestedUsername,
    selectedTemplate,
}: {
    goals: Goal[];
    niches: string[];
    templates: Template[];
    suggestedUsername: string;
    selectedTemplate?: string | null;
}) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        goal: '',
        niche: '',
        template: selectedTemplate ?? '',
        store_name: '',
        username: suggestedUsername,
        tagline: '',
        bio: '',
        whatsapp: '',
        socials: {} as Record<string, string>,
        publish: false as boolean,
    });

    // Plan-limit errors arrive under a key that is not part of the payload.
    const serverErrors = errors as Record<string, string | undefined>;

    const canAdvance = [
        !!data.goal,
        !!data.niche,
        !!data.template,
        data.store_name.length >= 2 && data.username.length >= 3,
        true,
    ][step];

    const submit = () => post('/onboarding');

    return (
        <div className="min-h-screen bg-subtle">
            <Head title="Bikin Toko" />

            <header className="border-b border-line bg-app">
                <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-4 sm:px-6">
                    <Logo />
                    <ThemeToggle />
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
                {/* Progress */}
                <ol className="mb-8 flex items-center gap-1.5" aria-label="Langkah onboarding">
                    {STEPS.map((label, i) => (
                        <li key={label} className="flex flex-1 flex-col gap-1.5">
                            <span
                                className={cn(
                                    'h-1.5 rounded-full transition-colors',
                                    i < step ? 'bg-[var(--success)]' : i === step ? 'gradient-brand' : 'bg-[var(--border)]',
                                )}
                                aria-hidden="true"
                            />
                            <span
                                className={cn(
                                    'hidden text-[11px] font-semibold sm:block',
                                    i === step ? 'text-fg' : 'text-muted',
                                )}
                                aria-current={i === step ? 'step' : undefined}
                            >
                                {label}
                            </span>
                        </li>
                    ))}
                </ol>

                {step === 0 && (
                    <StepShell
                        title="Kamu mau jualan apa?"
                        description="Ini cuma buat nyesuaiin rekomendasi. Nanti tetap bisa jual apa aja."
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {goals.map((goal) => (
                                <ChoiceCard
                                    key={goal.key}
                                    active={data.goal === goal.key}
                                    onClick={() => setData('goal', goal.key)}
                                    title={goal.label}
                                    description={goal.description}
                                />
                            ))}
                        </div>
                    </StepShell>
                )}

                {step === 1 && (
                    <StepShell title="Bidang kamu apa?" description="Biar kami bisa kasih contoh yang relevan.">
                        <div className="flex flex-wrap gap-2">
                            {niches.map((niche) => (
                                <button
                                    key={niche}
                                    type="button"
                                    onClick={() => setData('niche', niche)}
                                    className={cn(
                                        'rounded-full border px-4 py-2 text-sm font-semibold transition-colors',
                                        data.niche === niche
                                            ? 'border-transparent gradient-brand text-white'
                                            : 'border-line text-muted hover:bg-surface-2 hover:text-fg',
                                    )}
                                >
                                    {niche}
                                </button>
                            ))}
                        </div>
                    </StepShell>
                )}

                {step === 2 && (
                    <StepShell
                        title="Pilih tampilan awal"
                        description="Struktur block-nya beda-beda. Semua bisa kamu ubah setelah ini."
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {templates.map((template) => (
                                <button
                                    key={template.slug}
                                    type="button"
                                    onClick={() => setData('template', template.slug)}
                                    className={cn(
                                        'overflow-hidden rounded-[var(--radius-card)] border text-left transition-all',
                                        data.template === template.slug
                                            ? 'border-[var(--primary)] ring-2 ring-[var(--primary)]'
                                            : 'border-line hover:shadow-lift',
                                    )}
                                >
                                    <div
                                        className="h-16"
                                        style={{
                                            background: `linear-gradient(135deg, ${template.theme?.primary_color ?? '#7C3AED'}, ${template.theme?.accent_color ?? '#FB7185'})`,
                                        }}
                                    />
                                    <div className="bg-surface p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="font-bold">{template.name}</p>
                                            {template.is_premium && <Badge tone="brand">Premium</Badge>}
                                        </div>
                                        <p className="mt-1 text-sm text-muted">{template.tagline}</p>
                                        <p className="mt-2 text-xs text-muted">{template.blocks.length} block</p>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </StepShell>
                )}

                {step === 3 && (
                    <StepShell title="Kenalan dong" description="Ini yang bakal dilihat pengunjung tokomu.">
                        <div className="space-y-4">
                            <Field label="Nama toko" required error={errors.store_name} htmlFor="store_name">
                                <Input
                                    id="store_name"
                                    value={data.store_name}
                                    onChange={(e) => setData('store_name', e.target.value)}
                                    invalid={!!errors.store_name}
                                    placeholder="Contoh: KreatorKita"
                                    autoFocus
                                />
                            </Field>

                            <Field
                                label="Alamat toko"
                                required
                                error={errors.username}
                                hint="Ini link yang kamu bagikan ke followers."
                                htmlFor="username"
                            >
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-muted">
                                        jualanyok.id/
                                    </span>
                                    <Input
                                        id="username"
                                        value={data.username}
                                        onChange={(e) => setData('username', e.target.value.toLowerCase())}
                                        invalid={!!errors.username}
                                        className="pl-[104px]"
                                        spellCheck={false}
                                    />
                                </div>
                            </Field>

                            <Field label="Tagline" error={errors.tagline} hint="Satu kalimat singkat." htmlFor="tagline">
                                <Input
                                    id="tagline"
                                    value={data.tagline}
                                    onChange={(e) => setData('tagline', e.target.value)}
                                    placeholder="Bantu kamu jadi kreator konsisten"
                                />
                            </Field>

                            <Field label="Bio" error={errors.bio} htmlFor="bio">
                                <Textarea
                                    id="bio"
                                    rows={3}
                                    value={data.bio}
                                    onChange={(e) => setData('bio', e.target.value)}
                                    placeholder="Ceritain sedikit tentang kamu dan apa yang kamu jual."
                                />
                            </Field>

                            <Field label="Nomor WhatsApp" error={errors.whatsapp} hint="Opsional" htmlFor="whatsapp">
                                <Input
                                    id="whatsapp"
                                    type="tel"
                                    value={data.whatsapp}
                                    onChange={(e) => setData('whatsapp', e.target.value)}
                                    placeholder="08xxxxxxxxxx"
                                />
                            </Field>
                        </div>
                    </StepShell>
                )}

                {step === 4 && (
                    <StepShell title="Fondasi tokomu siap" description="Berikutnya, masukkan produk pertama lalu cek pratinjau sebelum toko dipublikasikan.">
                        <Card className="p-6">
                            <div className="flex items-start gap-4">
                                <span className="grid size-12 shrink-0 place-items-center rounded-2xl gradient-brand text-white">
                                    <Rocket className="size-6" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-lg font-extrabold">{data.store_name || 'Toko kamu'}</p>
                                    <p className="text-sm text-muted">jualanyok.id/{data.username}</p>
                                    {data.tagline && <p className="mt-2 text-sm">{data.tagline}</p>}
                                </div>
                            </div>

                            <div className="mt-6 rounded-[var(--radius-field)] bg-violet-50 p-4 text-sm text-violet-950 dark:bg-violet-500/10 dark:text-violet-100">
                                <p className="font-extrabold">Langkah berikutnya: produk pertama</p>
                                <p className="mt-1 text-xs leading-5 opacity-75">Toko tetap draft sampai kamu memeriksa pratinjau dan menekan tombol Publikasikan toko.</p>
                            </div>

                            {serverErrors.plan && <p className="mt-3 text-sm text-[var(--danger)]">{serverErrors.plan}</p>}
                        </Card>
                    </StepShell>
                )}

                {/* Navigation */}
                <div className="mt-8 flex items-center justify-between gap-3">
                    <Button
                        variant="ghost"
                        onClick={() => setStep((s) => Math.max(0, s - 1))}
                        disabled={step === 0}
                    >
                        <ArrowLeft className="size-4" />
                        Kembali
                    </Button>

                    {step < STEPS.length - 1 ? (
                        <Button
                            variant="gradient"
                            size="lg"
                            disabled={!canAdvance}
                            onClick={() => setStep((s) => s + 1)}
                        >
                            Lanjut
                            <ArrowRight className="size-4" />
                        </Button>
                    ) : (
                        <Button variant="gradient" size="lg" loading={processing} onClick={submit}>
                            <Check className="size-4" />
                            Lanjut buat produk
                        </Button>
                    )}
                </div>
            </main>
        </div>
    );
}

function StepShell({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <div className="animate-rise">
            <h1 className="text-2xl font-extrabold tracking-tight text-balance sm:text-3xl">{title}</h1>
            <p className="mt-2 text-sm text-muted">{description}</p>
            <div className="mt-6">{children}</div>
        </div>
    );
}

function ChoiceCard({
    active,
    onClick,
    title,
    description,
}: {
    active: boolean;
    onClick: () => void;
    title: string;
    description: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-[var(--radius-card)] border p-5 text-left transition-all',
                active
                    ? 'border-[var(--primary)] bg-brand-50 ring-2 ring-[var(--primary)] dark:bg-brand-900/20'
                    : 'border-line bg-surface hover:shadow-lift',
            )}
            aria-pressed={active}
        >
            <p className="font-bold">{title}</p>
            <p className="mt-1 text-sm text-muted">{description}</p>
        </button>
    );
}
