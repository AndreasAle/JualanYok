import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ImageUpload } from '@/components/image-upload';
import { PageHeader } from '@/components/shared';
import {
    Alert, Badge, Button, Card, CardBody, CardDescription, CardHeader, CardTitle, Field, Input, Select,
    Switch, Textarea,
} from '@/components/ui';

export default function Settings({
    store,
    theme,
    domains,
    account,
    permissions,
}: {
    store: any;
    theme: any;
    domains: any[];
    account: any;
    permissions: { custom_domain: boolean; remove_branding: boolean };
}) {
    const [tab, setTab] = useState<'toko' | 'tampilan' | 'akun'>('toko');

    const storeForm = useForm({
        name: store.name ?? '',
        username: store.username ?? '',
        tagline: store.tagline ?? '',
        bio: store.bio ?? '',
        whatsapp: store.whatsapp ?? '',
        socials: store.socials ?? {},
        seo_title: store.seo_title ?? '',
        seo_description: store.seo_description ?? '',
        show_platform_branding: store.show_platform_branding ?? true,
        avatar: null as File | null,
        cover: null as File | null,
        remove_avatar: false as boolean,
        remove_cover: false as boolean,
    });

    const themeForm = useForm({
        primary_color: theme?.primary_color ?? '#7C3AED',
        accent_color: theme?.accent_color ?? '#FB7185',
        background_type: theme?.background_type ?? 'solid',
        background_value: theme?.background_value ?? '#FFFFFF',
        font_family: theme?.font_family ?? 'jakarta',
        button_style: theme?.button_style ?? 'rounded',
        card_style: theme?.card_style ?? 'soft',
        product_layout: theme?.product_layout ?? 'grid',
        color_scheme: theme?.color_scheme ?? 'light',
    });

    const saveStore = (e: FormEvent) => {
        e.preventDefault();

        // PHP does not parse multipart bodies on PUT, so uploads have to go out
        // as POST with a method override. Inertia builds the FormData for us
        // once a File is present in the payload.
        storeForm.transform((data) => ({ ...data, _method: 'put' }));

        storeForm.post('/dashboard/toko', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => storeForm.setData((current) => ({
                ...current,
                avatar: null,
                cover: null,
                remove_avatar: false,
                remove_cover: false,
            })),
        });
    };

    const saveTheme = (e: FormEvent) => {
        e.preventDefault();
        themeForm.put('/dashboard/toko/tema', { preserveScroll: true });
    };

    const TABS = [
        { key: 'toko', label: 'Profil Toko' },
        { key: 'tampilan', label: 'Tampilan' },
        { key: 'akun', label: 'Akun & Keamanan' },
    ] as const;

    return (
        <DashboardLayout title="Pengaturan" area="creator">
            <PageHeader title="Pengaturan" description="Atur identitas toko, tampilan, dan keamanan akun." />

            <div className="mb-4 flex gap-1 overflow-x-auto rounded-[var(--radius-field)] bg-surface-2 p-1">
                {TABS.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => setTab(item.key)}
                        className={
                            tab === item.key
                                ? 'shrink-0 rounded-[calc(var(--radius-field)-2px)] bg-surface px-4 py-2 text-sm font-semibold shadow-soft'
                                : 'shrink-0 rounded-[calc(var(--radius-field)-2px)] px-4 py-2 text-sm font-semibold text-muted'
                        }
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {tab === 'toko' && (
                <form onSubmit={saveStore} className="max-w-2xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Foto &amp; banner</CardTitle>
                            <CardDescription>
                                Foto profil tampil di kartu toko, banner jadi latar di bagian atas halaman.
                            </CardDescription>
                        </CardHeader>
                        <CardBody className="space-y-5">
                            <ImageUpload
                                label="Foto profil toko"
                                hint="Paling bagus rasio 1:1, minimal 400×400 piksel."
                                currentUrl={store.avatar_url}
                                aspect="square"
                                error={storeForm.errors.avatar}
                                onSelect={(file) => {
                                    storeForm.setData('avatar', file);
                                    if (file) storeForm.setData('remove_avatar', false);
                                }}
                                onRemove={() => storeForm.setData('remove_avatar', true)}
                            />

                            <ImageUpload
                                label="Banner toko"
                                hint="Paling bagus rasio 16:5, minimal 1600×500 piksel."
                                currentUrl={store.cover_url}
                                aspect="wide"
                                error={storeForm.errors.cover}
                                onSelect={(file) => {
                                    storeForm.setData('cover', file);
                                    if (file) storeForm.setData('remove_cover', false);
                                }}
                                onRemove={() => storeForm.setData('remove_cover', true)}
                            />

                            <p className="text-xs text-muted">
                                Kalau banner dikosongkan, bagian atas toko otomatis memakai warna tema kamu.
                            </p>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Identitas toko</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-4">
                            <Field label="Nama toko" required error={storeForm.errors.name} htmlFor="store-name">
                                <Input
                                    id="store-name"
                                    value={storeForm.data.name}
                                    onChange={(e) => storeForm.setData('name', e.target.value)}
                                    invalid={!!storeForm.errors.name}
                                />
                            </Field>

                            <Field
                                label="Alamat toko"
                                required
                                error={storeForm.errors.username}
                                hint="Mengubah ini bikin link lama nggak berlaku lagi."
                                htmlFor="store-username"
                            >
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-muted">
                                        jualanyok.id/
                                    </span>
                                    <Input
                                        id="store-username"
                                        value={storeForm.data.username}
                                        onChange={(e) => storeForm.setData('username', e.target.value.toLowerCase())}
                                        invalid={!!storeForm.errors.username}
                                        className="pl-[104px]"
                                    />
                                </div>
                            </Field>

                            <Field label="Tagline" error={storeForm.errors.tagline} htmlFor="tagline">
                                <Input
                                    id="tagline"
                                    value={storeForm.data.tagline}
                                    onChange={(e) => storeForm.setData('tagline', e.target.value)}
                                />
                            </Field>

                            <Field label="Bio" error={storeForm.errors.bio} htmlFor="bio">
                                <Textarea
                                    id="bio"
                                    rows={3}
                                    value={storeForm.data.bio}
                                    onChange={(e) => storeForm.setData('bio', e.target.value)}
                                />
                            </Field>

                            <Field label="Nomor WhatsApp" error={storeForm.errors.whatsapp} htmlFor="wa">
                                <Input
                                    id="wa"
                                    value={storeForm.data.whatsapp}
                                    onChange={(e) => storeForm.setData('whatsapp', e.target.value)}
                                />
                            </Field>

                            {['instagram', 'tiktok', 'youtube'].map((platform) => (
                                <Field
                                    key={platform}
                                    label={platform[0].toUpperCase() + platform.slice(1)}
                                    htmlFor={platform}
                                >
                                    <Input
                                        id={platform}
                                        type="url"
                                        placeholder="https://"
                                        value={storeForm.data.socials?.[platform] ?? ''}
                                        onChange={(e) =>
                                            storeForm.setData('socials', {
                                                ...storeForm.data.socials,
                                                [platform]: e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            ))}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SEO</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-4">
                            <Field label="Judul SEO" error={storeForm.errors.seo_title} htmlFor="seo-title">
                                <Input
                                    id="seo-title"
                                    value={storeForm.data.seo_title}
                                    onChange={(e) => storeForm.setData('seo_title', e.target.value)}
                                />
                            </Field>

                            <Field label="Meta description" error={storeForm.errors.seo_description} htmlFor="seo-desc">
                                <Textarea
                                    id="seo-desc"
                                    rows={2}
                                    value={storeForm.data.seo_description}
                                    onChange={(e) => storeForm.setData('seo_description', e.target.value)}
                                />
                            </Field>

                            <Switch
                                checked={!storeForm.data.show_platform_branding}
                                onChange={(v) => storeForm.setData('show_platform_branding', !v)}
                                label="Sembunyikan badge JualanYok"
                                description={
                                    permissions.remove_branding
                                        ? 'Badge di bagian bawah toko dihilangkan.'
                                        : 'Tersedia mulai paket Pro.'
                                }
                                disabled={!permissions.remove_branding}
                            />
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Custom domain</CardTitle>
                        </CardHeader>
                        <CardBody>
                            {permissions.custom_domain ? (
                                domains.length > 0 ? (
                                    <ul className="space-y-2">
                                        {domains.map((domain) => (
                                            <li
                                                key={domain.id}
                                                className="flex items-center justify-between gap-3 rounded-[var(--radius-field)] bg-surface-2 p-3"
                                            >
                                                <span className="font-mono text-sm">{domain.domain}</span>
                                                <Badge tone={domain.status === 'verified' ? 'success' : 'warning'}>
                                                    {domain.status}
                                                </Badge>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-muted">
                                        Belum ada domain terhubung. Hubungi support buat menambahkan domain.
                                    </p>
                                )
                            ) : (
                                <Alert tone="info">
                                    Custom domain tersedia mulai paket Pro.{' '}
                                    <a href="/dashboard/langganan" className="font-bold underline">
                                        Lihat paket
                                    </a>
                                </Alert>
                            )}
                        </CardBody>
                    </Card>

                    <Button type="submit" variant="gradient" size="lg" loading={storeForm.processing}>
                        Simpan Pengaturan
                    </Button>
                </form>
            )}

            {tab === 'tampilan' && (
                <form onSubmit={saveTheme} className="max-w-2xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Warna &amp; gaya</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Warna utama" htmlFor="primary">
                                    <Input
                                        id="primary"
                                        type="color"
                                        value={themeForm.data.primary_color}
                                        onChange={(e) => themeForm.setData('primary_color', e.target.value)}
                                        className="h-11 p-1"
                                    />
                                </Field>

                                <Field label="Warna aksen" htmlFor="accent">
                                    <Input
                                        id="accent"
                                        type="color"
                                        value={themeForm.data.accent_color}
                                        onChange={(e) => themeForm.setData('accent_color', e.target.value)}
                                        className="h-11 p-1"
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Jenis background" htmlFor="bg-type">
                                    <Select
                                        id="bg-type"
                                        value={themeForm.data.background_type}
                                        onChange={(e) => themeForm.setData('background_type', e.target.value)}
                                    >
                                        <option value="solid">Warna solid</option>
                                        <option value="gradient">Gradient</option>
                                        <option value="image">Gambar</option>
                                    </Select>
                                </Field>

                                <Field label="Nilai background" hint="Warna hex atau URL gambar." htmlFor="bg-value">
                                    <Input
                                        id="bg-value"
                                        value={themeForm.data.background_value}
                                        onChange={(e) => themeForm.setData('background_value', e.target.value)}
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Font" htmlFor="font">
                                    <Select
                                        id="font"
                                        value={themeForm.data.font_family}
                                        onChange={(e) => themeForm.setData('font_family', e.target.value)}
                                    >
                                        <option value="jakarta">Plus Jakarta Sans</option>
                                        <option value="inter">Inter</option>
                                        <option value="manrope">Manrope</option>
                                        <option value="dm-sans">DM Sans</option>
                                        <option value="outfit">Outfit</option>
                                        <option value="sora">Sora</option>
                                        <option value="poppins">Poppins</option>
                                        <option value="nunito">Nunito</option>
                                        <option value="space">Space Grotesk</option>
                                        <option value="playfair">Playfair Display</option>
                                        <option value="lora">Lora</option>
                                        <option value="system">System UI</option>
                                    </Select>
                                </Field>

                                <Field label="Gaya tombol" htmlFor="button-style">
                                    <Select
                                        id="button-style"
                                        value={themeForm.data.button_style}
                                        onChange={(e) => themeForm.setData('button_style', e.target.value)}
                                    >
                                        <option value="rounded">Membulat</option>
                                        <option value="pill">Kapsul</option>
                                        <option value="square">Kotak</option>
                                    </Select>
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="Gaya kartu" htmlFor="card-style">
                                    <Select
                                        id="card-style"
                                        value={themeForm.data.card_style}
                                        onChange={(e) => themeForm.setData('card_style', e.target.value)}
                                    >
                                        <option value="soft">Lembut</option>
                                        <option value="outline">Garis</option>
                                        <option value="flat">Datar</option>
                                    </Select>
                                </Field>

                                <Field label="Tata letak produk" htmlFor="layout">
                                    <Select
                                        id="layout"
                                        value={themeForm.data.product_layout}
                                        onChange={(e) => themeForm.setData('product_layout', e.target.value)}
                                    >
                                        <option value="grid">Grid</option>
                                        <option value="list">List</option>
                                    </Select>
                                </Field>

                                <Field label="Skema warna" htmlFor="scheme">
                                    <Select
                                        id="scheme"
                                        value={themeForm.data.color_scheme}
                                        onChange={(e) => themeForm.setData('color_scheme', e.target.value)}
                                    >
                                        <option value="light">Terang</option>
                                        <option value="dark">Gelap</option>
                                        <option value="auto">Ikut perangkat</option>
                                    </Select>
                                </Field>
                            </div>

                            <div
                                className="rounded-[var(--radius-card)] p-6 text-center text-white"
                                style={{
                                    background: `linear-gradient(135deg, ${themeForm.data.primary_color}, ${themeForm.data.accent_color})`,
                                }}
                            >
                                <p className="font-semibold">Preview warna</p>
                                <p className="mt-1 text-sm text-white/80">Begini kira-kira tampilannya.</p>
                            </div>
                        </CardBody>
                    </Card>

                    <Button type="submit" variant="gradient" size="lg" loading={themeForm.processing}>
                        Simpan Tampilan
                    </Button>
                </form>
            )}

            {tab === 'akun' && (
                <div className="max-w-2xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Akun kamu</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-2 text-sm">
                            <p>
                                <span className="text-muted">Nama:</span> {account.name}
                            </p>
                            <p>
                                <span className="text-muted">Username:</span> @{account.username}
                            </p>
                            <p>
                                <span className="text-muted">Email:</span> {account.email}{' '}
                                {account.email_verified ? (
                                    <Badge tone="success">Terverifikasi</Badge>
                                ) : (
                                    <Badge tone="warning">Belum verifikasi</Badge>
                                )}
                            </p>
                            {account.phone && (
                                <p>
                                    <span className="text-muted">WhatsApp:</span> {account.phone}
                                </p>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Keamanan</CardTitle>
                        </CardHeader>
                        <CardBody className="space-y-3">
                            <p className="text-sm text-muted">
                                Kalau kamu merasa akunmu diakses orang lain, keluarkan semua perangkat lain.
                            </p>

                            <LogoutOtherDevices />

                            <Button variant="outline" onClick={() => router.visit('/forgot-password')}>
                                Ganti Password
                            </Button>
                        </CardBody>
                    </Card>
                </div>
            )}
        </DashboardLayout>
    );
}

function LogoutOtherDevices() {
    const { data, setData, post, processing, errors, reset } = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/logout-other-devices', { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-[var(--radius-field)] bg-surface-2 p-4">
            <Field
                label="Password kamu"
                error={errors.password}
                hint="Buat memastikan ini benar kamu."
                htmlFor="current-password"
            >
                <Input
                    id="current-password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    invalid={!!errors.password}
                    autoComplete="current-password"
                />
            </Field>

            <Button type="submit" variant="danger" loading={processing}>
                Keluarkan Perangkat Lain
            </Button>
        </form>
    );
}
