import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button, Field, Input } from '@/components/ui';

export default function ResetPassword({ token, email }: { token: string; email?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <AuthLayout title="Password Baru" heading="Bikin password baru" subheading="Pilih password yang cuma kamu yang tahu.">
            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" required error={errors.email} htmlFor="email">
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        invalid={!!errors.email}
                        autoComplete="email"
                        required
                    />
                </Field>

                <Field
                    label="Password baru"
                    required
                    error={errors.password}
                    hint="Minimal 8 karakter, ada huruf dan angka."
                    htmlFor="password"
                >
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        invalid={!!errors.password}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />
                </Field>

                <Field label="Ulangi password baru" required htmlFor="password_confirmation">
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                    />
                </Field>

                <Button type="submit" variant="gradient" block size="lg" loading={processing}>
                    Simpan Password Baru
                </Button>
            </form>
        </AuthLayout>
    );
}
