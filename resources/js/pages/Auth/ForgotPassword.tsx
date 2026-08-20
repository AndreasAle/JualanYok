import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Alert, Button, Field, Input } from '@/components/ui';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout
            title="Lupa Password"
            heading="Reset password kamu"
            subheading="Masukkan email akunmu, nanti kami kirim link buat bikin password baru."
            footer={
                <Link href="/login" className="font-bold text-[var(--primary)] hover:underline">
                    Balik ke halaman masuk
                </Link>
            }
        >
            {status && (
                <div className="mb-4">
                    <Alert tone="success">{status}</Alert>
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" required error={errors.email} htmlFor="email">
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        invalid={!!errors.email}
                        autoComplete="email"
                        autoFocus
                        required
                    />
                </Field>

                <Button type="submit" variant="gradient" block size="lg" loading={processing}>
                    Kirim Link Reset
                </Button>
            </form>
        </AuthLayout>
    );
}
