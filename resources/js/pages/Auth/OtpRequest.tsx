import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/AuthLayout';
import { Button, Field, Input } from '@/components/ui';

export default function OtpRequest({ email = '', orderNumber = null }: { email?: string; orderNumber?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({ email });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/masuk-pembeli');
    };

    return (
        <AuthLayout
            title={orderNumber ? 'Akses Pesanan' : 'Masuk Pembeli'}
            heading={orderNumber ? 'Buka pesananmu' : 'Masuk buat lihat pembelianmu'}
            subheading={
                orderNumber
                    ? `Kami kirim kode aman untuk membuka pesanan ${orderNumber}. Setelah masuk, kamu langsung kembali ke detail pesanan.`
                    : 'Pakai email yang kamu isi waktu checkout. Nggak perlu password.'
            }
            footer={
                <>
                    Kamu penjual?{' '}
                    <Link href="/login" className="font-bold text-[var(--primary)] hover:underline">
                        Masuk pakai password
                    </Link>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <Field
                    label="Email"
                    required
                    error={errors.email}
                    hint="Kami kirim kode 6 digit yang berlaku 10 menit."
                    htmlFor="email"
                >
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
                    Kirim Kode
                </Button>
            </form>
        </AuthLayout>
    );
}
