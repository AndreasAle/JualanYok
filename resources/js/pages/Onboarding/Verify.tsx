import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, MailCheck, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui';
import { Logo } from '@/layouts/MarketingLayout';
import { cn } from '@/lib/utils';

const LENGTH = 6;

/**
 * The last step of setting up a shop.
 *
 * Six separate boxes rather than one text field: a code arrives as six digits
 * and people read it back in pairs, so the boxes match what they are looking
 * at. Pasting the whole thing still works — the paste is split across them —
 * and the form submits itself on the last digit, because there is nothing else
 * to decide once six numbers are in.
 */
export default function OnboardingVerify({
    email,
    storeName,
    resendInSeconds,
    alreadySent,
}: {
    email: string;
    storeName: string | null;
    resendInSeconds: number;
    alreadySent: boolean;
}) {
    const [digits, setDigits] = useState<string[]>(Array(LENGTH).fill(''));
    const [cooldown, setCooldown] = useState(resendInSeconds);
    const boxes = useRef<(HTMLInputElement | null)[]>([]);

    const { data, setData, post, processing, errors } = useForm({ code: '' });

    // The first code is sent by opening the page, so nobody has to press a
    // button to receive the thing the page is asking them for.
    useEffect(() => {
        if (!alreadySent) {
            router.post('/onboarding/verifikasi/kirim', {}, { preserveState: true, preserveScroll: true });
            setCooldown(60);
        }
    }, []);

    useEffect(() => {
        if (cooldown <= 0) return;

        const timer = window.setInterval(() => setCooldown((current) => Math.max(0, current - 1)), 1000);

        return () => window.clearInterval(timer);
    }, [cooldown]);

    // `post` sends whatever is in the form's state, so the code is written
    // there first and the request is made from the effect that sees it land.
    const [pending, setPending] = useState(false);

    const submit = (code: string) => {
        setData('code', code);
        setPending(true);
    };

    useEffect(() => {
        if (!pending || data.code.length < LENGTH) return;

        setPending(false);
        post('/onboarding/verifikasi', { preserveScroll: true });
    }, [pending, data.code]);

    const write = (index: number, value: string) => {
        const cleaned = value.replace(/\D/g, '');

        if (cleaned === '') {
            const next = [...digits];
            next[index] = '';
            setDigits(next);

            return;
        }

        // A pasted code fills forward from wherever it landed.
        const next = [...digits];
        cleaned.split('').forEach((character, offset) => {
            if (index + offset < LENGTH) next[index + offset] = character;
        });
        setDigits(next);

        const filled = Math.min(index + cleaned.length, LENGTH - 1);
        boxes.current[filled]?.focus();

        const joined = next.join('');
        if (joined.length === LENGTH && !joined.includes('')) {
            submit(joined);
        }
    };

    const backspace = (index: number, event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Backspace' || digits[index] !== '') return;

        event.preventDefault();
        boxes.current[index - 1]?.focus();

        const next = [...digits];
        next[index - 1] = '';
        setDigits(next);
    };

    const resend = () => {
        router.post('/onboarding/verifikasi/kirim', {}, {
            preserveScroll: true,
            onSuccess: () => setCooldown(60),
        });
    };

    return (
        <div className="marketing-light min-h-screen bg-[var(--bg)]">
            <Head title="Verifikasi email" />

            <div className="mx-auto flex max-w-lg flex-col px-4 py-10 sm:py-16">
                <Logo className="mx-auto" />

                <div className="mt-8 rounded-[1.25rem] border border-line bg-surface p-6 shadow-sm sm:p-8">
                    <span className="grid size-11 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                        <MailCheck className="size-5" />
                    </span>

                    <h1 className="mt-4 text-xl font-semibold tracking-[-.02em]">Cek emailmu</h1>
                    <p className="mt-1.5 text-sm leading-6 text-muted">
                        Kami kirim 6 digit kode ke <strong className="font-medium text-fg">{email}</strong>.
                        Masukkan di bawah untuk mengaktifkan {storeName ? <strong className="font-medium text-fg">{storeName}</strong> : 'tokomu'}.
                    </p>

                    <div className="mt-6 flex justify-between gap-2" onPaste={(e) => {
                        e.preventDefault();
                        write(0, e.clipboardData.getData('text'));
                    }}>
                        {digits.map((digit, index) => (
                            <input
                                key={index}
                                ref={(element) => { boxes.current[index] = element; }}
                                value={digit}
                                onChange={(e) => write(index, e.target.value)}
                                onKeyDown={(e) => backspace(index, e)}
                                inputMode="numeric"
                                autoComplete={index === 0 ? 'one-time-code' : 'off'}
                                maxLength={LENGTH}
                                aria-label={`Digit ${index + 1}`}
                                autoFocus={index === 0}
                                className={cn(
                                    'h-13 w-full rounded-[var(--radius-field)] border text-center text-lg font-semibold tabular-nums outline-none transition',
                                    errors.code ? 'border-[var(--danger)]' : 'border-line focus:border-[var(--primary)]',
                                )}
                            />
                        ))}
                    </div>

                    {errors.code && <p className="mt-2 text-xs font-medium text-[var(--danger)]">{errors.code}</p>}

                    <Button
                        block
                        className="mt-5"
                        loading={processing}
                        disabled={digits.join('').length < LENGTH}
                        onClick={() => submit(digits.join(''))}
                    >
                        Verifikasi & lanjut
                    </Button>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                        <span>Nggak ada emailnya? Cek folder spam.</span>
                        <button
                            type="button"
                            onClick={resend}
                            disabled={cooldown > 0}
                            className="font-medium text-[var(--primary)] disabled:text-muted"
                        >
                            {cooldown > 0 ? `Kirim ulang (${cooldown}s)` : 'Kirim ulang kode'}
                        </button>
                    </div>
                </div>

                {/* Why this step exists, said once, in terms of the seller's money. */}
                <ul className="mt-6 space-y-2.5 text-[0.8125rem] text-muted">
                    {[
                        ['Struk dan link download pembeli dikirim ke email ini.', <CheckCircle2 key="a" className="size-4" />],
                        ['Notifikasi "pesanan masuk" dan pencairan saldo juga lewat sini.', <ShieldCheck key="b" className="size-4" />],
                    ].map(([text, icon], index) => (
                        <li key={index} className="flex items-start gap-2.5">
                            <span className="mt-0.5 shrink-0 text-emerald-600">{icon}</span>
                            {text}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
