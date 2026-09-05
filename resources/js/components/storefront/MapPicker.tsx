import { Crosshair, Loader2, MapPin, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/** Centre of Java, so an unset map opens somewhere recognisable. */
const FALLBACK: [number, number] = [-6.2, 106.816];

const LEAFLET_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
const LEAFLET_JS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js';

declare global {
    interface Window {
        L?: any;
    }
}

/** Loads Leaflet once, no matter how many times the sheet is opened. */
let leafletReady: Promise<any> | null = null;

function loadLeaflet(): Promise<any> {
    if (window.L) return Promise.resolve(window.L);
    if (leafletReady) return leafletReady;

    leafletReady = new Promise((resolve, reject) => {
        if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
            const style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = LEAFLET_CSS;
            document.head.appendChild(style);
        }

        const script = document.createElement('script');
        script.src = LEAFLET_JS;
        script.async = true;
        script.onload = () => resolve(window.L);
        script.onerror = () => reject(new Error('Peta gagal dimuat.'));
        document.head.appendChild(script);
    });

    return leafletReady;
}

/**
 * The address pin.
 *
 * Indonesian addresses are written, not surveyed — "belakang masjid, cat hijau"
 * is a real address and a courier still has to find it. A dropped pin is the
 * part a rider can actually navigate to, and for instant couriers it is not
 * optional: Gojek, Grab and Lalamove cannot price a job from a district name,
 * so without a coordinate those services never appear as an option at all.
 *
 * It stays optional. A buyer who ignores the map still gets regular courier
 * rates from the district they picked, so nothing here can block a checkout —
 * including the map itself failing to load.
 */
export function MapPicker({
    latitude,
    longitude,
    hint,
    onChange,
    storeUsername,
    className,
}: {
    latitude: number | null;
    longitude: number | null;
    /** District and city already chosen, used to open the map near them. */
    hint?: string;
    onChange: (position: { latitude: number; longitude: number } | null) => void;
    storeUsername: string;
    className?: string;
}) {
    const container = useRef<HTMLDivElement | null>(null);
    const map = useRef<any>(null);
    const marker = useRef<any>(null);
    const observer = useRef<ResizeObserver | null>(null);
    const visibility = useRef<IntersectionObserver | null>(null);
    const timers = useRef<number[]>([]);
    const hintApplied = useRef<string | null>(null);

    const [status, setStatus] = useState<'loading' | 'ready' | 'failed'>('loading');
    const [query, setQuery] = useState('');
    const [busy, setBusy] = useState(false);
    const [label, setLabel] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [tilesDrawn, setTilesDrawn] = useState(false);

    const describe = async (lat: number, lon: number) => {
        try {
            const response = await fetch(`/${storeUsername}/peta/balik?lat=${lat}&lon=${lon}`, {
                headers: { Accept: 'application/json' },
            });
            const body = await response.json();
            setLabel(body.label ?? null);
        } catch {
            setLabel(null);
        }
    };

    const place = (lat: number, lon: number, zoom = 16) => {
        if (!map.current || !window.L) return;

        marker.current.setLatLng([lat, lon]);
        map.current.setView([lat, lon], zoom);
        onChange({ latitude: Number(lat.toFixed(6)), longitude: Number(lon.toFixed(6)) });
        void describe(lat, lon);
    };

    useEffect(() => {
        let cancelled = false;

        loadLeaflet()
            .then((L) => {
                if (cancelled || !container.current || map.current) return;

                const start: [number, number] =
                    latitude !== null && longitude !== null ? [latitude, longitude] : FALLBACK;

                map.current = L.map(container.current, { attributionControl: true }).setView(
                    start,
                    latitude !== null ? 16 : 11,
                );

                const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map.current);

                // Tiles are the one part served from outside this app. If a
                // network blocks them the map is a grey box, so say so instead
                // of leaving the buyer staring at nothing.
                tiles.on('tileload', () => setTilesDrawn(true));
                tiles.on('tileerror', () => setNotice(
                    'Peta nggak bisa dimuat dari jaringan ini. Lewati aja — ongkir reguler tetap jalan.',
                ));

                // A blank box with no explanation is the worst outcome, so if
                // nothing has drawn by now, say so rather than let it sit there.
                timers.current.push(window.setTimeout(() => {
                    setTilesDrawn((drawn) => {
                        if (! drawn) {
                            setNotice('Peta lambat dimuat. Kamu bisa lanjut tanpa pin — ongkir reguler tetap jalan.');
                        }

                        return drawn;
                    });
                }, 9000));

                marker.current = L.marker(start, { draggable: true }).addTo(map.current);

                /*
                 * Leaflet measures its container once, at creation, and only
                 * requests the tiles that fit what it measured. Inside a sheet
                 * that is still animating open the container is briefly zero
                 * or half width, so the map "loads" and then sits there empty —
                 * controls and attribution drawn, not a single tile fetched.
                 *
                 * Re-measuring on the next frame fixes the common case, and the
                 * observer covers the rest: the sheet finishing its animation,
                 * the address section growing when a district is chosen, or the
                 * phone being turned.
                 */
                const remeasure = () => map.current?.invalidateSize({ animate: false });

                /*
                 * Keep re-measuring until the container reports a real width.
                 *
                 * One re-measure is not enough. A sheet settles over several
                 * frames, and until it does Leaflet's cached size is zero — at
                 * which point it computes an empty tile range and requests
                 * nothing at all. The controls and attribution are positioned
                 * by CSS, so they appear anyway: the map looks loaded and is
                 * simply blank. This retries at a human-invisible interval and
                 * stops the moment there is a width to work with.
                 */
                let tries = 0;
                const settle = window.setInterval(() => {
                    remeasure();

                    if ((map.current?.getSize?.().x ?? 0) > 0 || ++tries > 25) {
                        window.clearInterval(settle);
                    }
                }, 200);

                timers.current.push(settle);
                requestAnimationFrame(remeasure);
                timers.current.push(window.setTimeout(remeasure, 350));

                if ('ResizeObserver' in window) {
                    observer.current = new ResizeObserver(remeasure);
                    observer.current.observe(container.current);
                }

                // A sheet can mount its content below the fold, or while it is
                // still transparent. Neither changes the container's size, so
                // the resize observer never fires — but the map is just as
                // blank, and it stays blank until something re-measures it.
                if ('IntersectionObserver' in window) {
                    visibility.current = new IntersectionObserver((entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) remeasure();
                    });
                    visibility.current.observe(container.current);
                }

                marker.current.on('dragend', () => {
                    const { lat, lng } = marker.current.getLatLng();
                    onChange({ latitude: Number(lat.toFixed(6)), longitude: Number(lng.toFixed(6)) });
                    void describe(lat, lng);
                });

                // Tapping the map is faster than dragging on a phone.
                map.current.on('click', (event: any) => place(event.latlng.lat, event.latlng.lng, map.current.getZoom()));

                setStatus('ready');
            })
            .catch(() => !cancelled && setStatus('failed'));

        return () => {
            cancelled = true;
            observer.current?.disconnect();
            visibility.current?.disconnect();
            timers.current.forEach((timer) => window.clearTimeout(timer));
            timers.current.forEach((timer) => window.clearInterval(timer));
        };
    }, []);

    // When the buyer picks their district, move the map there — but only once
    // per district, so it never yanks the view out from under a placed pin.
    useEffect(() => {
        if (status !== 'ready' || !hint || hint === hintApplied.current || latitude !== null) {
            return;
        }

        hintApplied.current = hint;
        void jump(hint, false);
    }, [status, hint]);

    const jump = async (text: string, drop = true) => {
        if (!text.trim()) return;

        setBusy(true);
        setNotice(null);

        try {
            const response = await fetch(`/${storeUsername}/peta/cari?q=${encodeURIComponent(text)}`, {
                headers: { Accept: 'application/json' },
            });
            const body = await response.json();
            const first = body.results?.[0];

            if (!first) {
                setNotice('Alamat itu nggak ketemu di peta. Geser pin-nya manual ya.');

                return;
            }

            if (drop) {
                place(first.latitude, first.longitude);
            } else {
                // Centring on a district is not the buyer saying "here".
                map.current?.setView([first.latitude, first.longitude], 14);
                marker.current?.setLatLng([first.latitude, first.longitude]);
            }
        } catch {
            setNotice('Pencarian peta lagi bermasalah. Geser pin-nya manual ya.');
        } finally {
            setBusy(false);
        }
    };

    const locateMe = () => {
        if (!navigator.geolocation) {
            setNotice('Browser kamu nggak mendukung deteksi lokasi.');

            return;
        }

        setBusy(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                place(position.coords.latitude, position.coords.longitude, 17);
                setBusy(false);
            },
            () => {
                setNotice('Izin lokasi ditolak. Geser pin-nya manual ya.');
                setBusy(false);
            },
            { enableHighAccuracy: true, timeout: 8000 },
        );
    };

    if (status === 'failed') {
        // Never a blocker: the order can still be placed from the district.
        return null;
    }

    return (
        <div className={className}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-[0.8125rem] font-medium">Pin point alamat <span className="font-normal opacity-60">(opsional)</span></p>
                <button
                    type="button"
                    onClick={locateMe}
                    className="inline-flex items-center gap-1 text-xs font-medium text-[var(--sf-primary)]"
                >
                    <Crosshair className="size-3.5" /> Lokasi saya
                </button>
            </div>

            <p className="mt-0.5 text-xs opacity-60">
                {hint
                    ? 'Geser pin ke titik rumahmu. Kurir instan seperti GoSend dan GrabExpress butuh titik ini untuk bisa muncul.'
                    : 'Pilih kecamatan dulu supaya peta langsung lompat ke wilayahmu — atau cari dan geser pin-nya sekarang.'}
            </p>

            <div className="mt-2 flex gap-2">
                <label className="relative flex-1">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 opacity-50" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                void jump(query);
                            }
                        }}
                        placeholder="Cari nama jalan atau patokan"
                        className="h-9 w-full rounded-lg border border-[var(--sf-line)] pl-8 pr-3 text-[0.8125rem] outline-none"
                    />
                </label>
                <button
                    type="button"
                    onClick={() => void jump(query)}
                    disabled={busy}
                    className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-[var(--sf-line)] px-3 text-xs font-medium disabled:opacity-50"
                >
                    {busy ? <Loader2 className="size-3.5 animate-spin" /> : <MapPin className="size-3.5" />}
                    Cari
                </button>
            </div>

            <div className="relative mt-2">
                <div
                    ref={container}
                    className="h-52 w-full overflow-hidden rounded-lg border border-[var(--sf-line)]"
                />

                {!tilesDrawn && (
                    <span className="pointer-events-none absolute inset-0 grid place-items-center rounded-lg bg-[color-mix(in_oklab,var(--sf-fg)_5%,transparent)] text-xs opacity-80">
                        <span className="inline-flex items-center gap-1.5">
                            <Loader2 className="size-3.5 animate-spin" /> Memuat peta…
                        </span>
                    </span>
                )}
            </div>

            {notice && <p className="mt-1.5 text-xs text-amber-600">{notice}</p>}

            {latitude !== null && longitude !== null && (
                <div className="mt-1.5 flex flex-wrap items-start gap-x-2 gap-y-1 text-xs">
                    <span className="font-medium text-emerald-600">Titik tersimpan</span>
                    {label && <span className="min-w-0 flex-1 opacity-60">{label}</span>}
                    <button
                        type="button"
                        onClick={() => {
                            onChange(null);
                            setLabel(null);
                        }}
                        className="underline opacity-60"
                    >
                        Hapus pin
                    </button>
                </div>
            )}
        </div>
    );
}
