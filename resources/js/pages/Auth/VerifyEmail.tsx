import { router, useForm } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import AuthLayout from '@/layouts/AuthLayout';
import { Alert, Button } from '@/components/ui';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    return (
        <AuthLayout
            title="Verifikasi Email"
            heading="Cek email kamu dulu ya"
            subheading="Kami kirim link verifikasi ke email yang kamu daftarkan. Klik linknya buat mengaktifkan semua fitur."
            footer={
                <button
                    type="button"
                    onClick={() => router.post('/logout')}
                    className="font-bold text-[var(--primary)] hover:underline"
                >
                    Keluar
                </button>
            }
        >
            {status && (
                <div className="mb-4">
                    <Alert tone="success">{status}</Alert>
                </div>
            )}

            <div className="rounded-[var(--radius-card)] border border-line bg-surface p-6 text-center">
                <MailCheck className="mx-auto size-10 text-[var(--primary)]" />
                <p className="mt-3 text-sm text-muted">
                    Nggak nemu emailnya? Cek folder spam, atau kirim ulang linknya.
                </p>

                <Button
                    variant="gradient"
                    block
                    className="mt-5"
                    loading={processing}
                    onClick={() => post('/email/verification-notification')}
                >
                    Kirim Ulang Link
                </Button>
            </div>
        </AuthLayout>
    );
}
