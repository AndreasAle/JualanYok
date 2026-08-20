import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Button, Card, CardBody, CardHeader, CardTitle, Field, Input } from '@/components/ui';

export default function MemberProfile({
    user,
}: {
    user: { name: string; username: string; email: string; phone: string | null };
}) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: user.name,
        phone: user.phone ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/member/profil', { preserveScroll: true });
    };

    return (
        <DashboardLayout title="Profil" area="member">
            <PageHeader title="Profil" description="Data ini dipakai buat struk dan pengiriman." />

            <form onSubmit={submit} className="max-w-lg">
                <Card>
                    <CardHeader>
                        <CardTitle>Data kamu</CardTitle>
                    </CardHeader>
                    <CardBody className="space-y-4">
                        <Field label="Nama" required error={errors.name} htmlFor="name">
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                invalid={!!errors.name}
                                required
                            />
                        </Field>

                        <Field label="Nomor WhatsApp" error={errors.phone} htmlFor="phone">
                            <Input
                                id="phone"
                                type="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                            />
                        </Field>

                        <Field label="Email" hint="Email nggak bisa diubah — dipakai buat masuk." htmlFor="email">
                            <Input id="email" value={user.email} disabled />
                        </Field>

                        <div className="flex items-center gap-3">
                            <Button type="submit" variant="gradient" loading={processing}>
                                Simpan
                            </Button>
                            {recentlySuccessful && (
                                <span className="text-sm font-semibold text-[var(--success)]">Tersimpan!</span>
                            )}
                        </div>
                    </CardBody>
                </Card>
            </form>
        </DashboardLayout>
    );
}
