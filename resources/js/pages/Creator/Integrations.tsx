import { router, useForm } from '@inertiajs/react';
import { Plug, Send, Trash2, Webhook } from 'lucide-react';
import type { FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, PageHeader } from '@/components/shared';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, EmptyState, Field, Input,
} from '@/components/ui';

export default function Integrations({
    pixels,
    endpoints,
    deliveries,
    availableEvents,
    permissions,
}: {
    pixels: Record<string, string>;
    endpoints: any[];
    deliveries: any[];
    availableEvents: string[];
    permissions: { pixels: boolean; webhooks: boolean };
}) {
    const pixelForm = useForm({
        meta_pixel_id: pixels.meta_pixel_id ?? '',
        tiktok_pixel_id: pixels.tiktok_pixel_id ?? '',
        ga4_id: pixels.ga4_id ?? '',
        gtm_id: pixels.gtm_id ?? '',
    });

    const webhookForm = useForm({
        url: '',
        events: ['order.paid'] as string[],
    });

    const savePixels = (e: FormEvent) => {
        e.preventDefault();
        pixelForm.put('/dashboard/integrasi/pixels', { preserveScroll: true });
    };

    const addWebhook = (e: FormEvent) => {
        e.preventDefault();
        webhookForm.post('/dashboard/integrasi/webhooks', {
            preserveScroll: true,
            onSuccess: () => webhookForm.reset(),
        });
    };

    return (
        <DashboardLayout title="Integrasi" area="creator">
            <PageHeader
                title="Integrasi"
                description="Sambungkan tokomu ke tool marketing dan sistem lain."
            />

            <div className="grid gap-4 lg:grid-cols-2">
                {/* Pixels */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pixel &amp; analytics</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {!permissions.pixels ? (
                            <Alert tone="info">
                                Integrasi pixel tersedia mulai paket Creator.{' '}
                                <a href="/dashboard/langganan" className="font-bold underline">
                                    Lihat paket
                                </a>
                            </Alert>
                        ) : (
                            <form onSubmit={savePixels} className="space-y-4">
                                <Alert tone="info">
                                    Masukkan ID-nya saja, bukan potongan script. Kami yang pasang tag-nya dengan aman.
                                </Alert>

                                <Field
                                    label="Meta Pixel ID"
                                    error={pixelForm.errors.meta_pixel_id}
                                    hint="Contoh: 123456789012345"
                                    htmlFor="meta"
                                >
                                    <Input
                                        id="meta"
                                        value={pixelForm.data.meta_pixel_id}
                                        onChange={(e) => pixelForm.setData('meta_pixel_id', e.target.value)}
                                        invalid={!!pixelForm.errors.meta_pixel_id}
                                    />
                                </Field>

                                <Field label="TikTok Pixel ID" error={pixelForm.errors.tiktok_pixel_id} htmlFor="tiktok">
                                    <Input
                                        id="tiktok"
                                        value={pixelForm.data.tiktok_pixel_id}
                                        onChange={(e) => pixelForm.setData('tiktok_pixel_id', e.target.value)}
                                        invalid={!!pixelForm.errors.tiktok_pixel_id}
                                    />
                                </Field>

                                <Field
                                    label="Google Analytics 4"
                                    error={pixelForm.errors.ga4_id}
                                    hint="Format G-XXXXXXX"
                                    htmlFor="ga4"
                                >
                                    <Input
                                        id="ga4"
                                        value={pixelForm.data.ga4_id}
                                        onChange={(e) => pixelForm.setData('ga4_id', e.target.value.toUpperCase())}
                                        invalid={!!pixelForm.errors.ga4_id}
                                    />
                                </Field>

                                <Field
                                    label="Google Tag Manager"
                                    error={pixelForm.errors.gtm_id}
                                    hint="Format GTM-XXXXXX"
                                    htmlFor="gtm"
                                >
                                    <Input
                                        id="gtm"
                                        value={pixelForm.data.gtm_id}
                                        onChange={(e) => pixelForm.setData('gtm_id', e.target.value.toUpperCase())}
                                        invalid={!!pixelForm.errors.gtm_id}
                                    />
                                </Field>

                                <Button type="submit" variant="gradient" loading={pixelForm.processing}>
                                    Simpan Pixel
                                </Button>
                            </form>
                        )}
                    </CardBody>
                </Card>

                {/* Webhooks */}
                <Card>
                    <CardHeader>
                        <CardTitle>Webhook</CardTitle>
                    </CardHeader>
                    <CardBody>
                        {!permissions.webhooks ? (
                            <Alert tone="info">
                                Webhook tersedia mulai paket Pro.{' '}
                                <a href="/dashboard/langganan" className="font-bold underline">
                                    Lihat paket
                                </a>
                            </Alert>
                        ) : (
                            <>
                                {endpoints.length === 0 ? (
                                    <EmptyState
                                        icon={<Webhook className="size-6" />}
                                        title="Belum ada webhook"
                                        description="Kirim event pesanan ke sistem lain secara realtime."
                                    />
                                ) : (
                                    <ul className="mb-4 space-y-2">
                                        {endpoints.map((endpoint) => (
                                            <li
                                                key={endpoint.id}
                                                className="rounded-[var(--radius-field)] bg-surface-2 p-3"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="truncate font-mono text-xs">{endpoint.url}</p>
                                                        <div className="mt-1.5 flex flex-wrap gap-1">
                                                            {endpoint.events.map((event: string) => (
                                                                <Badge key={event}>{event}</Badge>
                                                            ))}
                                                        </div>
                                                        {endpoint.failure_count > 0 && (
                                                            <p className="mt-1.5 text-xs text-[var(--danger)]">
                                                                {endpoint.failure_count} kegagalan berturut-turut
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="flex shrink-0 gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Kirim test"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/dashboard/integrasi/webhooks/${endpoint.id}/test`,
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <Send className="size-4" />
                                                        </Button>

                                                        <ConfirmButton
                                                            title="Hapus webhook ini?"
                                                            message="Event nggak akan dikirim lagi ke URL ini."
                                                            confirmLabel="Ya, hapus"
                                                            onConfirm={() =>
                                                                router.delete(
                                                                    `/dashboard/integrasi/webhooks/${endpoint.id}`,
                                                                )
                                                            }
                                                        >
                                                            <Button variant="ghost" size="icon" aria-label="Hapus">
                                                                <Trash2 className="size-4 text-[var(--danger)]" />
                                                            </Button>
                                                        </ConfirmButton>
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <form onSubmit={addWebhook} className="space-y-3 border-t border-line pt-4">
                                    <Field
                                        label="URL endpoint"
                                        required
                                        error={webhookForm.errors.url}
                                        hint="Harus HTTPS."
                                        htmlFor="hook-url"
                                    >
                                        <Input
                                            id="hook-url"
                                            type="url"
                                            placeholder="https://app-kamu.com/webhook"
                                            value={webhookForm.data.url}
                                            onChange={(e) => webhookForm.setData('url', e.target.value)}
                                            invalid={!!webhookForm.errors.url}
                                        />
                                    </Field>

                                    <Field label="Event" error={webhookForm.errors.events}>
                                        <div className="flex flex-wrap gap-2">
                                            {availableEvents.map((event) => {
                                                const active = webhookForm.data.events.includes(event);

                                                return (
                                                    <button
                                                        key={event}
                                                        type="button"
                                                        onClick={() =>
                                                            webhookForm.setData(
                                                                'events',
                                                                active
                                                                    ? webhookForm.data.events.filter((e) => e !== event)
                                                                    : [...webhookForm.data.events, event],
                                                            )
                                                        }
                                                        className={
                                                            active
                                                                ? 'rounded-full border border-transparent gradient-brand px-3 py-1.5 text-xs font-semibold text-white'
                                                                : 'rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-muted'
                                                        }
                                                        aria-pressed={active}
                                                    >
                                                        {event}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </Field>

                                    <Button type="submit" variant="gradient" loading={webhookForm.processing}>
                                        <Plug className="size-4" />
                                        Tambah Webhook
                                    </Button>
                                </form>
                            </>
                        )}
                    </CardBody>
                </Card>
            </div>

            {deliveries.length > 0 && (
                <Card className="mt-4">
                    <CardHeader>
                        <CardTitle>Log pengiriman terakhir</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <ul className="divide-y divide-[var(--border)]">
                            {deliveries.map((delivery) => (
                                <li key={delivery.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                    <span className="min-w-0">
                                        <span className="block font-mono text-xs">{delivery.event}</span>
                                        <span className="block text-xs text-muted">
                                            percobaan {delivery.attempt} · {delivery.created_at}
                                        </span>
                                    </span>
                                    <Badge tone={delivery.status === 'success' ? 'success' : 'danger'}>
                                        {delivery.response_status ?? delivery.status}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </CardBody>
                </Card>
            )}
        </DashboardLayout>
    );
}
