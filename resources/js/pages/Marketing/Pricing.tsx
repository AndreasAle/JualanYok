import { Check, CheckCircle2, Minus, ShieldCheck, Sparkles, TrendingDown, WalletCards } from 'lucide-react';
import { useState } from 'react';
import { PageCta, PageHero, Reveal } from '@/components/marketing-page';
import { Badge, ButtonLink } from '@/components/ui';
import MarketingLayout from '@/layouts/MarketingLayout';
import { cn, formatIDR, formatNumber } from '@/lib/utils';

interface Feature { key: string; label: string | null; enabled: boolean; limit: number | null; }
interface Plan {
    slug: string; name: string; tagline: string | null; price_monthly: number; price_yearly: number;
    transaction_fee_percent: number; trial_days: number; highlights: string[]; features: Feature[];
}

export default function Pricing({ plans }: { plans: Plan[] }) {
    const [yearly, setYearly] = useState(false);
    const featureKeys = Array.from(new Map(plans.flatMap((plan) => plan.features).map((feature) => [feature.key, feature.label ?? feature.key])).entries());

    return (
        <MarketingLayout title="Harga" description="Paket JualanYok yang tumbuh mengikuti tahap bisnismu, dari gratis sampai skala besar.">
            <PageHero
                eyebrow="Harga transparan"
                title={<>Mulai tanpa risiko. Upgrade saat <span className="gradient-text">jualan bertumbuh.</span></>}
                description="Tidak ada biaya setup, kontrak panjang, atau fitur inti yang sengaja dibuat membingungkan. Pilih paket berdasarkan kebutuhanmu hari ini."
            >
                <PricingHeroVisual />
            </PageHero>

            <section className="mx-auto max-w-6xl px-5 py-20 sm:px-6 lg:py-28">
                <Reveal>
                    <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                        <div className="max-w-xl"><p className="section-kicker">PILIH RITME TAGIHAN</p><h2 className="section-title">Paket yang pas untuk setiap fase.</h2></div>
                        <BillingToggle yearly={yearly} onChange={setYearly} />
                    </div>
                </Reveal>

                <div className="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {plans.map((plan, index) => <Reveal key={plan.slug} delay={(index % 4) * 70}><PlanCard plan={plan} yearly={yearly} index={index} /></Reveal>)}
                </div>

                <Reveal delay={100}>
                    <div className="mt-8 grid gap-3 rounded-[1.5rem] border border-line bg-surface-2 p-4 sm:grid-cols-3 sm:p-5">
                        <Assurance icon={<ShieldCheck />} title="Tanpa kontrak" detail="Berhenti atau ganti paket kapan saja." />
                        <Assurance icon={<WalletCards />} title="Mulai Rp0" detail="Tidak perlu kartu kredit untuk mulai." />
                        <Assurance icon={<TrendingDown />} title="Fee makin ringan" detail="Biaya transaksi turun saat upgrade." />
                    </div>
                </Reveal>
            </section>

            <Comparison plans={plans} featureKeys={featureKeys} />
            <PageCta title="Mulai gratis, buktikan dulu cocoknya." description="Bangun toko dan masukkan produk pertamamu tanpa kartu kredit. Upgrade hanya saat fiturnya benar-benar kamu butuhkan." secondaryHref="/contact" secondaryLabel="Diskusi kebutuhan" />
        </MarketingLayout>
    );
}

function PricingHeroVisual() {
    return (
        <div className="relative mx-auto min-h-[360px] max-w-lg">
            <div className="absolute inset-x-6 top-5 rounded-[1.6rem] border border-white/80 bg-white/88 p-5 shadow-[0_28px_70px_rgba(66,39,119,.18)] backdrop-blur-xl sm:inset-x-10">
                <div className="flex items-start justify-between"><div><p className="text-[9px] font-bold uppercase tracking-wide text-violet-500">Pro plan</p><p className="mt-2 text-3xl font-black tracking-tight text-neutral-900">Rp149.000<span className="text-[10px] font-semibold text-neutral-400">/bln</span></p></div><span className="grid size-10 place-items-center rounded-xl bg-violet-100 text-violet-600"><Sparkles className="size-5" /></span></div>
                <div className="mt-6 space-y-3">{['Produk tanpa batas', 'Custom domain', 'Affiliate & analytics', 'Transaction fee lebih rendah'].map((item) => <div key={item} className="flex items-center gap-2 text-[10px] font-semibold text-neutral-600"><span className="grid size-4 place-items-center rounded-full bg-emerald-100 text-emerald-600"><Check className="size-2.5" /></span>{item}</div>)}</div>
                <div className="mt-6 rounded-full bg-[#171722] py-2.5 text-center text-[10px] font-extrabold text-white">Pilih paket Pro</div>
            </div>
            <div className="jy-float absolute bottom-3 left-0 rounded-2xl border border-white/80 bg-white/90 p-3 shadow-xl backdrop-blur"><p className="text-[8px] font-bold text-neutral-400">Mulai dari</p><p className="mt-1 text-sm font-black text-neutral-900">Gratis selamanya</p></div>
            <div className="jy-float-delayed absolute bottom-11 right-0 rounded-2xl bg-[#171722] p-3 text-white shadow-xl"><p className="text-[8px] text-white/45">Tagihan tahunan</p><p className="mt-1 text-xs font-extrabold text-emerald-300">Hemat 2 bulan</p></div>
        </div>
    );
}

function BillingToggle({ yearly, onChange }: { yearly: boolean; onChange: (value: boolean) => void }) {
    return (
        <div className="inline-flex self-start rounded-full border border-line bg-surface p-1 shadow-sm">
            <button type="button" onClick={() => onChange(false)} className={cn('rounded-full px-4 py-2 text-xs font-extrabold transition', !yearly ? 'bg-[#171722] text-white' : 'text-muted')}>Bulanan</button>
            <button type="button" onClick={() => onChange(true)} className={cn('flex items-center gap-2 rounded-full px-4 py-2 text-xs font-extrabold transition', yearly ? 'bg-[#171722] text-white' : 'text-muted')}>Tahunan <span className={cn('rounded-full px-2 py-0.5 text-[8px]', yearly ? 'bg-emerald-400/20 text-emerald-300' : 'bg-emerald-100 text-emerald-700')}>Hemat</span></button>
        </div>
    );
}

function PlanCard({ plan, yearly, index }: { plan: Plan; yearly: boolean; index: number }) {
    const price = yearly ? plan.price_yearly : plan.price_monthly;
    const featured = plan.slug === 'pro';
    const tones = ['bg-[#f6e8f1]', 'bg-[#e4f4f0]', 'bg-[#e9e0ff]', 'bg-[#fff0dd]'];
    return (
        <article className={cn('relative flex h-full min-h-[500px] flex-col rounded-[1.65rem] border p-6', tones[index % tones.length], featured ? 'border-violet-400 shadow-[0_24px_60px_rgba(124,58,237,.16)]' : 'border-transparent')}>
            {featured && <span className="absolute -top-3 left-6 rounded-full bg-[#171722] px-3 py-1 text-[9px] font-extrabold uppercase tracking-wide text-white">Paling populer</span>}
            <p className="text-base font-extrabold text-neutral-900">{plan.name}</p><p className="mt-2 min-h-12 text-xs leading-6 text-neutral-600">{plan.tagline}</p>
            <div className="mt-7"><p className="text-3xl font-black tracking-[-.04em] text-neutral-900">{price === 0 ? 'Gratis' : formatIDR(price)}</p>{price > 0 && <p className="mt-1 text-[10px] font-semibold text-neutral-500">per {yearly ? 'tahun' : 'bulan'}</p>}</div>
            <div className="mt-5 rounded-xl bg-white/55 p-3"><p className="text-[9px] text-neutral-500">Biaya transaksi</p><p className="mt-1 text-sm font-extrabold text-neutral-900">{plan.transaction_fee_percent}% <span className="text-[9px] font-semibold text-neutral-400">per penjualan</span></p></div>
            {plan.trial_days > 0 && <Badge className="mt-4 self-start border border-emerald-200 bg-emerald-50 text-[9px] text-emerald-700">Coba gratis {plan.trial_days} hari</Badge>}
            <ul className="mt-6 flex-1 space-y-3">{(plan.highlights ?? []).slice(0, 6).map((item) => <li key={item} className="flex items-start gap-2 text-[11px] leading-5 text-neutral-700"><Check className="mt-0.5 size-3.5 shrink-0" />{item}</li>)}</ul>
            <ButtonLink href="/register" block className={cn('mt-7 rounded-full', featured ? 'bg-[#171722] text-white hover:bg-black' : 'border border-black/10 bg-white/65 text-neutral-900 hover:bg-white')}>{price === 0 ? 'Mulai gratis' : `Pilih ${plan.name}`}</ButtonLink>
        </article>
    );
}

function Assurance({ icon, title, detail }: { icon: React.ReactNode; title: string; detail: string }) {
    return <div className="flex items-center gap-3 rounded-xl bg-surface p-3"><span className="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-100 text-violet-600 [&>svg]:size-4">{icon}</span><div><p className="text-xs font-extrabold">{title}</p><p className="mt-0.5 text-[10px] text-muted">{detail}</p></div></div>;
}

function Comparison({ plans, featureKeys }: { plans: Plan[]; featureKeys: [string, string][] }) {
    return (
        <section className="bg-[#f5f5f6] py-20 dark:bg-subtle lg:py-24">
            <div className="mx-auto max-w-6xl px-5 sm:px-6">
                <Reveal><div className="mx-auto max-w-2xl text-center"><p className="section-kicker">PERBANDINGAN LENGKAP</p><h2 className="section-title">Lihat batas dan akses setiap paket.</h2><p className="section-body mx-auto">Geser tabel ke samping di mobile untuk melihat seluruh paket.</p></div></Reveal>
                <Reveal delay={100}>
                    <div className="mt-12 overflow-hidden rounded-[1.5rem] border border-line bg-surface shadow-soft"><div className="overflow-x-auto"><table className="w-full min-w-180 text-sm"><thead><tr className="border-b border-line bg-surface-2"><th className="sticky left-0 z-10 bg-surface-2 px-5 py-4 text-left text-[10px] font-extrabold uppercase tracking-wide text-muted">Fitur</th>{plans.map((plan) => <th key={plan.slug} className="px-4 py-4 text-center text-xs font-extrabold">{plan.name}</th>)}</tr></thead><tbody>{featureKeys.map(([key, label]) => <tr key={key} className="border-b border-line last:border-0 hover:bg-surface-2/50"><th className="sticky left-0 bg-surface px-5 py-3.5 text-left text-xs font-semibold">{label}</th>{plans.map((plan) => { const feature = plan.features.find((item) => item.key === key); return <td key={plan.slug} className="px-4 py-3.5 text-center">{!feature || !feature.enabled ? <Minus className="mx-auto size-4 text-muted/35" aria-label="Tidak tersedia" /> : feature.limit === null ? <CheckCircle2 className="mx-auto size-4 text-emerald-500" aria-label="Tersedia" /> : <span className="text-xs font-extrabold tabular-nums">{formatNumber(feature.limit)}</span>}</td>; })}</tr>)}</tbody></table></div></div>
                </Reveal>
            </div>
        </section>
    );
}
