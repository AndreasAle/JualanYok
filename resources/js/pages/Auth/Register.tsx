import { Link, useForm } from '@inertiajs/react';
import { Check, Eye, EyeOff, Loader2, X } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { GoogleAuthButton } from '@/components/google-auth-button';
import { Button, Field, Input } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout';
import { cn } from '@/lib/utils';

type Availability = { state: 'idle' | 'checking' | 'ok' | 'taken'; reason?: string; suggestion?: string };

export default function Register({ googleConfigured, selectedTemplate }: { googleConfigured: boolean; selectedTemplate?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '', username: '', email: '', password: '', password_confirmation: '', terms: false as boolean, template: selectedTemplate ?? '',
    });
    const [availability, setAvailability] = useState<Availability>({ state: 'idle' });
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        if (data.username.length < 3) { setAvailability({ state: 'idle' }); return; }
        setAvailability({ state: 'checking' });
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch('/username/check', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify({ username: data.username }),
                });
                const result = await response.json();
                setAvailability(result.available ? { state: 'ok' } : { state: 'taken', reason: result.reason, suggestion: result.suggestion });
            } catch { setAvailability({ state: 'idle' }); }
        }, 400);
        return () => window.clearTimeout(timer);
    }, [data.username]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/register');
    };

    const inputClass = 'h-12 rounded-xl bg-[#f7f7f8] dark:bg-[#26262f]';

    return (
        <AuthLayout
            title="Daftar"
            heading="Buat akun JualanYok"
            subheading="Bikin toko pertamamu dan mulai gratis hari ini."
            footer={<>Sudah punya akun? <Link href="/login" className="font-extrabold text-violet-600 hover:underline">Masuk</Link></>}
        >
            <GoogleAuthButton label="Daftar dengan Google" configured={googleConfigured} href={`/auth/google${selectedTemplate ? `?template=${encodeURIComponent(selectedTemplate)}` : ''}`} />
            {!googleConfigured && <p className="mt-2 text-center text-[10px] text-muted">Google signup aktif setelah kredensial OAuth diisi.</p>}
            <p className="mt-3 text-center text-[9px] leading-4 text-muted">Dengan Google, kamu menyetujui <Link href="/terms" className="font-bold text-violet-600">Syarat</Link> dan <Link href="/privacy" className="font-bold text-violet-600">Kebijakan Privasi</Link>.</p>

            <div className="my-6 flex items-center gap-3"><span className="h-px flex-1 bg-[var(--border)]" /><span className="text-[10px] font-bold uppercase tracking-[.14em] text-neutral-400">atau isi data</span><span className="h-px flex-1 bg-[var(--border)]" /></div>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Nama" required error={errors.name} htmlFor="name"><Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} invalid={!!errors.name} className={inputClass} placeholder="Nama lengkap" autoComplete="name" required /></Field>

                <Field label="Username toko" required error={errors.username} hint={availability.state === 'ok' ? undefined : 'Menjadi alamat unik tokomu.'} htmlFor="username">
                    <div className="relative">
                        <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted">jualanyok.id/</span>
                        <Input id="username" value={data.username} onChange={(event) => setData('username', event.target.value.toLowerCase())} invalid={!!errors.username || availability.state === 'taken'} className={`${inputClass} pl-[101px] pr-10`} autoComplete="off" spellCheck={false} required />
                        <span className="absolute right-3 top-1/2 -translate-y-1/2">{availability.state === 'checking' && <Loader2 className="size-4 animate-spin text-muted" />}{availability.state === 'ok' && <Check className="size-4 text-emerald-500" />}{availability.state === 'taken' && <X className="size-4 text-rose-500" />}</span>
                    </div>
                    {availability.state === 'ok' && <p className="mt-1.5 text-xs font-semibold text-emerald-600">Username tersedia.</p>}
                    {availability.state === 'taken' && <p className="mt-1.5 text-xs text-rose-600">{availability.reason} {availability.suggestion && <button type="button" onClick={() => setData('username', availability.suggestion!)} className="font-bold underline">Coba {availability.suggestion}</button>}</p>}
                </Field>

                <Field label="Email" required error={errors.email} htmlFor="email"><Input id="email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} invalid={!!errors.email} className={inputClass} placeholder="nama@email.com" autoComplete="email" required /></Field>

                <Field label="Password" required error={errors.password} hint="Minimal 8 karakter, berisi huruf dan angka." htmlFor="password">
                    <div className="relative"><Input id="password" type={showPassword ? 'text' : 'password'} value={data.password} onChange={(event) => setData('password', event.target.value)} invalid={!!errors.password} className={`${inputClass} pr-11`} placeholder="Buat password" autoComplete="new-password" required /><button type="button" onClick={() => setShowPassword((value) => !value)} className="absolute right-3 top-1/2 grid size-8 -translate-y-1/2 place-items-center text-muted hover:text-fg" aria-label={showPassword ? 'Sembunyikan password' : 'Lihat password'}>{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button></div>
                </Field>

                <Field label="Ulangi password" required htmlFor="password_confirmation"><Input id="password_confirmation" type={showPassword ? 'text' : 'password'} value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} className={inputClass} placeholder="Ulangi password" autoComplete="new-password" required /></Field>

                <label className="flex items-start gap-2.5 text-xs leading-5"><input type="checkbox" checked={data.terms} onChange={(event) => setData('terms', event.target.checked)} className={cn('mt-0.5 size-4 shrink-0 rounded border-line accent-violet-600', errors.terms && 'outline outline-2 outline-rose-500')} required /><span className="text-muted">Aku setuju dengan <Link href="/terms" className="font-bold text-violet-600 hover:underline">Syarat & Ketentuan</Link> dan <Link href="/privacy" className="font-bold text-violet-600 hover:underline">Kebijakan Privasi</Link>.</span></label>
                {errors.terms && <p className="text-xs font-medium text-rose-600">{errors.terms}</p>}

                <Button type="submit" block size="lg" loading={processing} className="h-12 rounded-xl bg-gradient-to-r from-violet-600 via-fuchsia-500 to-coral-500 text-white shadow-[0_12px_26px_rgba(124,58,237,.22)] hover:brightness-105">Buat akun</Button>
            </form>
        </AuthLayout>
    );
}
