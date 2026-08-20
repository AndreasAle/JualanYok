import { router, useForm } from '@inertiajs/react';
import { ShieldCheck, UserX } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, PageHeader } from '@/components/shared';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Field, Textarea,
} from '@/components/ui';
import { formatDate, formatIDR } from '@/lib/utils';

export default function AdminUserShow({
    user,
    canImpersonate,
}: {
    user: any;
    canImpersonate: boolean;
}) {
    const [suspending, setSuspending] = useState(false);
    const form = useForm({ reason: '' });

    const suspend = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/pengguna/${user.id}/suspend`, {
            preserveScroll: true,
            onSuccess: () => setSuspending(false),
        });
    };

    return (
        <DashboardLayout title={user.name} area="admin">
            <PageHeader
                title={user.name}
                description={`@${user.username} · ${user.email}`}
                breadcrumbs={[{ label: 'Pengguna', href: '/admin/pengguna' }, { label: user.name }]}
                actions={
                    <>
                        {user.status === 'active' ? (
                            <Button variant="outline" onClick={() => setSuspending((v) => !v)}>
                                <UserX className="size-4" />
                                Tangguhkan
                            </Button>
                        ) : (
                            <ConfirmButton
                                title="Aktifkan kembali akun ini?"
                                message="Pengguna bisa masuk dan berjualan lagi."
                                confirmLabel="Ya, aktifkan"
                                variant="primary"
                                onConfirm={() => router.post(`/admin/pengguna/${user.id}/aktifkan`)}
                            >
                                <Button variant="success">Aktifkan Kembali</Button>
                            </ConfirmButton>
                        )}

                        {canImpersonate && (
                            <ConfirmButton
                                title="Masuk sebagai pengguna ini?"
                                message="Tindakan ini tercatat di audit log dan akan menampilkan banner peringatan."
                                confirmLabel="Ya, lanjutkan"
                                variant="primary"
                                onConfirm={() => router.post(`/admin/pengguna/${user.id}/impersonate`)}
                            >
                                <Button variant="outline">
                                    <ShieldCheck className="size-4" />
                                    Impersonate
                                </Button>
                            </ConfirmButton>
                        )}
                    </>
                }
            />

            {user.status !== 'active' && (
                <div className="mb-4">
                    <Alert tone="danger" title="Akun ditangguhkan">
                        {user.suspension_reason ?? 'Tanpa alasan tercatat.'}
                    </Alert>
                </div>
            )}

            {suspending && (
                <Card className="mb-4 p-5">
                    <form onSubmit={suspend} className="space-y-3">
                        <Field
                            label="Alasan penangguhan"
                            required
                            error={form.errors.reason}
                            hint="Tercatat di audit log dan ditampilkan ke pengguna."
                            htmlFor="reason"
                        >
                            <Textarea
                                id="reason"
                                rows={3}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                required
                            />
                        </Field>

                        <div className="flex gap-2">
                            <Button type="submit" variant="danger" loading={form.processing}>
                                Tangguhkan Akun
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setSuspending(false)}>
                                Batal
                            </Button>
                        </div>
                    </form>
                </Card>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Detail akun</CardTitle>
                    </CardHeader>
                    <CardBody className="space-y-2 text-sm">
                        <p>
                            <span className="text-muted">Email:</span> {user.email}
                        </p>
                        {user.phone && (
                            <p>
                                <span className="text-muted">Telepon:</span> {user.phone}
                            </p>
                        )}
                        <p>
                            <span className="text-muted">Bergabung:</span> {formatDate(user.created_at, true)}
                        </p>
                        <p>
                            <span className="text-muted">Login terakhir:</span>{' '}
                            {user.last_login_at ? formatDate(user.last_login_at, true) : '—'}
                        </p>
                        <p className="flex flex-wrap items-center gap-1.5">
                            <span className="text-muted">Peran:</span>
                            {user.roles.map((role: string) => (
                                <Badge key={role}>{role}</Badge>
                            ))}
                        </p>
                    </CardBody>
                </Card>

                {user.wallet && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Saldo</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-2 text-sm">
                            <Row label="Tersedia" value={formatIDR(user.wallet.available)} />
                            <Row label="Tertahan" value={formatIDR(user.wallet.pending)} />
                            <Row label="Sedang ditarik" value={formatIDR(user.wallet.held)} />
                            <Row label="Sudah ditarik" value={formatIDR(user.wallet.withdrawn)} />

                            {user.wallet.is_frozen && (
                                <Alert tone="warning">Saldo pengguna ini sedang dibekukan.</Alert>
                            )}
                        </CardBody>
                    </Card>
                )}

                {user.stores.length > 0 && (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Toko</CardTitle>
                        </CardHeader>
                        <CardBody>
                            <ul className="space-y-2">
                                {user.stores.map((store: any) => (
                                    <li
                                        key={store.username}
                                        className="flex items-center justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3"
                                    >
                                        <span className="min-w-0">
                                            <span className="block font-semibold">{store.name}</span>
                                            <span className="block text-xs text-muted">/{store.username}</span>
                                        </span>
                                        <span className="flex shrink-0 gap-1">
                                            {store.is_published ? (
                                                <Badge tone="success">Live</Badge>
                                            ) : (
                                                <Badge>Draft</Badge>
                                            )}
                                            {store.status !== 'active' && <Badge tone="danger">{store.status}</Badge>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardBody>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted">{label}</span>
            <span className="font-semibold tabular-nums">{value}</span>
        </div>
    );
}
