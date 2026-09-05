import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Each courier's own colour, so a list of eight services is scannable.
 *
 * These are the couriers' recognised brand hues, used as the ground for their
 * initials. They are not the logos — the marks themselves belong to the
 * couriers, and are dropped into `public/images/couriers/<code>.png` when the
 * licensed files are to hand. Until then a buyer still gets a stable, coloured
 * anchor per courier instead of eight identical rows of text.
 */
const BRAND: Record<string, { color: string; short: string }> = {
    jne: { color: '#D0021B', short: 'JNE' },
    tiki: { color: '#004A97', short: 'TIKI' },
    pos: { color: '#EA5B0C', short: 'POS' },
    jnt: { color: '#D9261C', short: 'J&T' },
    'j&t': { color: '#D9261C', short: 'J&T' },
    sicepat: { color: '#E5322D', short: 'SC' },
    anteraja: { color: '#E63329', short: 'AA' },
    ninja: { color: '#D6212B', short: 'NJ' },
    wahana: { color: '#0B4EA2', short: 'WHN' },
    lion: { color: '#C8102E', short: 'LION' },
    sap: { color: '#1B3A8C', short: 'SAP' },
    idexpress: { color: '#E4002B', short: 'ID' },
    rex: { color: '#F26522', short: 'REX' },
    sentral: { color: '#B01F24', short: 'SCG' },
    royal: { color: '#8B1A1A', short: 'RE' },
    gojek: { color: '#00AA13', short: 'GO' },
    grab: { color: '#00B14F', short: 'GRB' },
    lalamove: { color: '#F16521', short: 'LLM' },
    borzo: { color: '#7B2FF7', short: 'BRZ' },
    paxel: { color: '#00A3AD', short: 'PXL' },
    deliveree: { color: '#0D7DBF', short: 'DLV' },
};

/** A neutral slate for a courier we have no colour for, never a blank square. */
const FALLBACK = { color: '#475569', short: '' };

export function CourierBadge({
    code,
    name,
    className,
}: {
    /** Courier company code from the rate, e.g. "jne". */
    code: string | null;
    name: string;
    className?: string;
}) {
    const key = (code ?? name).toLowerCase().replace(/[^a-z&]/g, '');
    const brand = BRAND[key] ?? FALLBACK;
    const [logoFailed, setLogoFailed] = useState(false);

    const label = brand.short || name.slice(0, 3).toUpperCase();

    return (
        <span
            className={cn(
                'grid size-9 shrink-0 place-items-center overflow-hidden rounded-lg',
                className,
            )}
            style={logoFailed ? { background: brand.color } : undefined}
            aria-hidden="true"
        >
            {logoFailed ? (
                <span className="px-0.5 text-[0.5625rem] font-bold leading-none tracking-tight text-white">
                    {label}
                </span>
            ) : (
                /*
                 * The real mark when the file is there, the monogram when it is
                 * not. Swapping on error rather than checking first means adding
                 * a logo is only ever dropping a file in — no list to update,
                 * and nothing breaks while the folder is still empty.
                 */
                <img
                    src={`/images/couriers/${key}.png`}
                    alt=""
                    loading="lazy"
                    onError={() => setLogoFailed(true)}
                    className="size-full object-contain"
                />
            )}
        </span>
    );
}
