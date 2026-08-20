import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button, Field, Input } from '@/components/ui';

export default function OtpVerify({ email }: { email?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: email ?? '',
        code: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/masuk-pembeli/verifikasi');
    };

    return (
        <AuthLayout
            title="Verifikasi Kode"
            heading="Masukkan kodenya"
            subheading={email ? `Kode sudah kami kirim ke ${email}.` : 'Masukkan kode yang kami kirim ke emailmu.'}
            footer={
                <Link href="/masuk-pembeli" className="font-bold text-[var(--primary)] hover:underline">
                    Kirim ulang kode
                </Link>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                {!email && (
                    <Field label="Email" required error={errors.email} htmlFor="email">
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            invalid={!!errors.email}
                            required
                        />
                    </Field>
                )}

                <Field label="Kode 6 digit" required error={errors.code} htmlFor="code">
                    <Input
                        id="code"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        maxLength={6}
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                        invalid={!!errors.code}
                        className="text-center text-2xl font-bold tracking-[0.5em]"
                        placeholder="000000"
                        autoFocus
                        required
                    />
                </Field>

                <Button type="submit" variant="gradient" block size="lg" loading={processing}>
                    Masuk
                </Button>
            </form>
        </AuthLayout>
    );
}
