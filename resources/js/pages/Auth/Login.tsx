import { Link, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { GoogleAuthButton } from '@/components/google-auth-button';
import { Alert, Button, Field, Input } from '@/components/ui';
import AuthLayout from '@/layouts/AuthLayout';
import type { PageProps } from '@/types';

export default function Login({ status, googleConfigured }: { status?: string; googleConfigured: boolean }) {
    const { flash } = usePage<PageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ login: '', password: '', remember: false as boolean });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <AuthLayout
            title="Masuk"
            heading="Masuk ke akunmu"
            subheading="Kelola toko, pesanan, dan saldo dari satu tempat."
            footer={<>Belum punya akun? <Link href="/register" className="font-extrabold text-violet-600 hover:underline">Daftar gratis</Link></>}
        >
            {status && <div className="mb-4"><Alert tone="success">{status}</Alert></div>}
            {flash.error && <div className="mb-4"><Alert tone="danger">{flash.error}</Alert></div>}

            <GoogleAuthButton label="Lanjutkan dengan Google" configured={googleConfigured} />
            {!googleConfigured && <p className="mt-2 text-center text-[10px] text-muted">Google login aktif setelah kredensial OAuth diisi.</p>}

            <div className="my-6 flex items-center gap-3"><span className="h-px flex-1 bg-[var(--border)]" /><span className="text-[10px] font-bold uppercase tracking-[.14em] text-neutral-400">atau dengan email</span><span className="h-px flex-1 bg-[var(--border)]" /></div>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email atau username" required error={errors.login} htmlFor="login">
                    <Input id="login" value={data.login} onChange={(event) => setData('login', event.target.value)} invalid={!!errors.login} className="h-12 rounded-xl bg-[#f7f7f8] dark:bg-surface-2" placeholder="nama@email.com" autoComplete="username" autoFocus required />
                </Field>

                <div>
                    <div className="mb-1.5 flex items-center justify-between"><label htmlFor="password" className="text-sm font-semibold">Password <span className="text-[var(--danger)]">*</span></label><Link href="/forgot-password" className="text-xs font-bold text-violet-600 hover:underline">Lupa password?</Link></div>
                    <div className="relative">
                        <Input id="password" type={showPassword ? 'text' : 'password'} value={data.password} onChange={(event) => setData('password', event.target.value)} invalid={!!errors.password} className="h-12 rounded-xl bg-[#f7f7f8] pr-11 dark:bg-surface-2" placeholder="Masukkan password" autoComplete="current-password" required />
                        <button type="button" onClick={() => setShowPassword((value) => !value)} className="absolute right-3 top-1/2 grid size-8 -translate-y-1/2 place-items-center text-muted hover:text-fg" aria-label={showPassword ? 'Sembunyikan password' : 'Lihat password'}>{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button>
                    </div>
                    {errors.password && <p className="mt-1.5 text-xs font-medium text-[var(--danger)]">{errors.password}</p>}
                </div>

                <label className="flex items-center gap-2 text-xs font-semibold text-muted"><input type="checkbox" checked={data.remember} onChange={(event) => setData('remember', event.target.checked)} className="size-4 rounded border-line accent-violet-600" /> Tetap masuk di perangkat ini</label>

                <Button type="submit" block size="lg" loading={processing} className="h-12 rounded-xl bg-gradient-to-r from-violet-600 via-fuchsia-500 to-coral-500 text-white shadow-[0_12px_26px_rgba(124,58,237,.22)] hover:brightness-105">Masuk</Button>
            </form>

            <p className="mt-6 text-center text-xs text-muted">Kamu pembeli? <Link href="/masuk-pembeli" className="font-extrabold text-violet-600 hover:underline">Masuk pakai kode email</Link></p>

        </AuthLayout>
    );
}
