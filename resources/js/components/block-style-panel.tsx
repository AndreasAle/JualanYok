import { AlignCenter, AlignLeft, AlignRight, Sparkles, Wand2 } from 'lucide-react';
import { useState } from 'react';
import type { BlockStyleTokens } from '@/lib/block-style';
import { cn } from '@/lib/utils';

/**
 * Design controls for one block.
 *
 * A fixed vocabulary rather than a CSS field: colour comes from the storefront
 * theme, so a styled block always belongs to the palette, and no combination
 * can produce a broken page.
 */

const BACKGROUNDS: [string, string, string][] = [
    ['none', 'Polos', 'bg-transparent border border-line'],
    ['subtle', 'Lembut', 'bg-violet-50 dark:bg-violet-500/10'],
    ['outline', 'Garis', 'bg-transparent border-2 border-line'],
    ['primary', 'Utama', 'bg-violet-600'],
    ['accent', 'Aksen', 'bg-fuchsia-500'],
    ['dark', 'Gelap', 'bg-[#12131a]'],
    ['gradient', 'Gradien', 'bg-gradient-to-br from-violet-600 via-fuchsia-500 to-orange-400'],
];

const ANIMATIONS: [string, string][] = [
    ['none', 'Tanpa animasi'],
    ['fade', 'Muncul perlahan'],
    ['slide-up', 'Naik dari bawah'],
    ['slide-left', 'Geser dari kanan'],
    ['slide-right', 'Geser dari kiri'],
    ['zoom', 'Membesar'],
    ['blur', 'Dari buram'],
];

export function BlockStylePanel({
    value,
    onChange,
}: {
    value: BlockStyleTokens;
    onChange: (patch: BlockStyleTokens) => void;
}) {
    const [open, setOpen] = useState(false);

    const hasStyle = Object.entries(value).some(
        ([key, v]) => v && v !== 'none' && !(key === 'radius' && v === 'lg') && !(key === 'align' && v === 'left'),
    );

    const set = (patch: BlockStyleTokens) => onChange({ ...value, ...patch });
    const showsSurface = (value.background ?? 'none') !== 'none';

    return (
        <div className="rounded-xl border border-line">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between gap-3 p-3.5 text-left"
            >
                <span className="flex items-center gap-2 text-sm font-bold">
                    <Wand2 className="size-4 text-violet-600" />
                    Tampilan & animasi
                </span>
                <span className="flex items-center gap-2">
                    {hasStyle && (
                        <span className="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-black text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                            Aktif
                        </span>
                    )}
                    <span className="text-xs font-bold text-muted">{open ? 'Tutup' : 'Atur'}</span>
                </span>
            </button>

            {open && (
                <div className="space-y-4 border-t border-line p-3.5">
                    <Field label="Latar belakang">
                        <div className="flex flex-wrap gap-2">
                            {BACKGROUNDS.map(([key, label, swatch]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => set({ background: key })}
                                    title={label}
                                    aria-label={label}
                                    aria-pressed={(value.background ?? 'none') === key}
                                    className={cn(
                                        'size-9 rounded-lg transition',
                                        swatch,
                                        (value.background ?? 'none') === key
                                            ? 'ring-2 ring-violet-500 ring-offset-2 ring-offset-[var(--surface)]'
                                            : 'hover:scale-105',
                                    )}
                                />
                            ))}
                        </div>
                    </Field>

                    {/* Padding, radius and shadow only read as anything once a
                        surface is drawn, so they stay hidden until there is one. */}
                    {showsSurface && (
                        <>
                            <Choice
                                label="Ruang dalam"
                                value={value.padding ?? 'md'}
                                options={[['none', 'Rapat'], ['sm', 'Kecil'], ['md', 'Sedang'], ['lg', 'Lega'], ['xl', 'Luas']]}
                                onChange={(padding) => set({ padding })}
                            />
                            <Choice
                                label="Sudut"
                                value={value.radius ?? 'lg'}
                                options={[['none', 'Siku'], ['sm', 'Kecil'], ['md', 'Sedang'], ['lg', 'Bulat'], ['xl', 'Sangat bulat']]}
                                onChange={(radius) => set({ radius })}
                            />
                            <Choice
                                label="Bayangan"
                                value={value.shadow ?? 'none'}
                                options={[['none', 'Tanpa'], ['soft', 'Halus'], ['lift', 'Terangkat'], ['glow', 'Menyala']]}
                                onChange={(shadow) => set({ shadow })}
                            />
                        </>
                    )}

                    <Field label="Perataan teks">
                        <div className="flex gap-1 rounded-lg bg-surface-2 p-1">
                            {([
                                ['left', <AlignLeft key="l" className="size-4" />, 'Kiri'],
                                ['center', <AlignCenter key="c" className="size-4" />, 'Tengah'],
                                ['right', <AlignRight key="r" className="size-4" />, 'Kanan'],
                            ] as const).map(([key, icon, label]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => set({ align: key })}
                                    aria-label={label}
                                    aria-pressed={(value.align ?? 'left') === key}
                                    className={cn(
                                        'flex flex-1 items-center justify-center rounded-md py-2 transition',
                                        (value.align ?? 'left') === key ? 'bg-surface shadow-sm' : 'text-muted',
                                    )}
                                >
                                    {icon}
                                </button>
                            ))}
                        </div>
                    </Field>

                    <Choice
                        label="Lebar isi"
                        value={value.width ?? 'normal'}
                        options={[['normal', 'Normal'], ['narrow', 'Sempit'], ['wide', 'Lebar']]}
                        onChange={(width) => set({ width })}
                    />

                    <div className="rounded-lg bg-surface-2 p-3">
                        <p className="mb-2 flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-violet-600">
                            <Sparkles className="size-3.5" />
                            Animasi saat discroll
                        </p>

                        <select
                            value={value.animation ?? 'none'}
                            onChange={(event) => set({ animation: event.target.value })}
                            className="h-10 w-full rounded-lg border border-line bg-surface px-3 text-sm font-semibold"
                            aria-label="Animasi saat discroll"
                        >
                            {ANIMATIONS.map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>

                        {(value.animation ?? 'none') !== 'none' && (
                            <>
                                <Choice
                                    className="mt-3"
                                    label="Jeda mulai"
                                    value={value.animation_delay ?? '0'}
                                    options={[['0', 'Langsung'], ['100', '0,1s'], ['200', '0,2s'], ['300', '0,3s'], ['500', '0,5s']]}
                                    onChange={(animation_delay) => set({ animation_delay })}
                                />
                                <p className="mt-2 text-[11px] leading-4 text-muted">
                                    Animasi dimatikan otomatis untuk pengunjung yang memilih mode hemat gerak di
                                    perangkatnya.
                                </p>
                            </>
                        )}
                    </div>

                    {hasStyle && (
                        <button
                            type="button"
                            onClick={() => onChange({})}
                            className="text-xs font-bold text-muted underline hover:text-fg"
                        >
                            Kembalikan ke bawaan
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="mb-1.5 text-xs font-bold text-muted">{label}</p>
            {children}
        </div>
    );
}

function Choice({
    label,
    value,
    options,
    onChange,
    className,
}: {
    label: string;
    value: string;
    options: [string, string][] | readonly (readonly [string, string])[];
    onChange: (value: string) => void;
    className?: string;
}) {
    return (
        <div className={className}>
            <p className="mb-1.5 text-xs font-bold text-muted">{label}</p>
            <div className="flex flex-wrap gap-1.5">
                {options.map(([key, text]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => onChange(key)}
                        aria-pressed={value === key}
                        className={cn(
                            'rounded-lg px-2.5 py-1.5 text-xs font-bold transition',
                            value === key
                                ? 'bg-violet-600 text-white'
                                : 'bg-surface-2 text-muted hover:text-fg',
                        )}
                    >
                        {text}
                    </button>
                ))}
            </div>
        </div>
    );
}
