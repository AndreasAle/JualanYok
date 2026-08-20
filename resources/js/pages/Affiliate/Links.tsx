import { router, useForm } from '@inertiajs/react';
import { Check, Copy, ExternalLink, Plus, Power } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ConfirmButton, DataList, PageHeader, Pagination, type Column } from '@/components/shared';
import {
    Badge, Button, Card, CardBody, CardHeader, CardTitle, EmptyState, Field, Input, Select,
} from '@/components/ui';
import { formatIDR, formatNumber } from '@/lib/utils';
import type { Paginated } from '@/types';

interface LinkRow {
    id: number;
    code: string;
    store: string;
    product: string | null;
    campaign: string | null;
    sub_id: string | null;
    clicks: number;
    conversions: number;
    conversion_rate: number;
    revenue: number;
    is_active: boolean;
    url: string;
}

export default function AffiliateLinks({
    links,
    products,
}: {
    links: Paginated<LinkRow>;
    products: { id: number; name: string }[];
}) {
    const [copied, setCopied] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        product_id: products[0]?.id ?? '',
        campaign: '',
        sub_id: '',
    });

    const copy = async (url: string) => {
        await navigator.clipboard.writeText(url);
        setCopied(url);
        setTimeout(() => setCopied(null), 2000);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/affiliate/link', { preserveScroll: true, onSuccess: () => reset('campaign', 'sub_id') });
    };

    const columns: Column<LinkRow>[] = [
        {
            key: 'link',
            header: 'Link',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{row.product ?? row.store}</span>
                    <span className="block text-xs text-muted">
                        {row.store}
                        {row.campaign && ` · ${row.campaign}`}
                        {row.sub_id && ` · ${row.sub_id}`}
                    </span>
                    <span className="mt-1 block truncate font-mono text-[11px] text-muted">{row.url}</span>
                </span>
            ),
        },
        {
            key: 'clicks',
            header: 'Klik',
            align: 'right',
            render: (row) => <span className="font-semibold">{formatNumber(row.clicks)}</span>,
        },
        {
            key: 'conversions',
            header: 'Konversi',
            align: 'right',
            render: (row) => (
                <span>
                    <span className="block font-semibold">{formatNumber(row.conversions)}</span>
                    <span className="block text-xs text-muted">{row.conversion_rate}%</span>
                </span>
            ),
        },
        {
            key: 'revenue',
            header: 'Omzet dihasilkan',
            align: 'right',
            mobile: false,
            render: (row) => <span className="font-semibold">{formatIDR(row.revenue)}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (row.is_active ? <Badge tone="success">Aktif</Badge> : <Badge>Nonaktif</Badge>),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            render: (row) => (
                <span className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" aria-label="Salin link" onClick={() => copy(row.url)}>
                        {copied === row.url ? <Check className="size-4" /> : <Copy className="size-4" />}
                    </Button>

                    <a
                        href={row.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="grid size-10 place-items-center rounded-[var(--radius-field)] text-muted hover:bg-surface-2 hover:text-fg"
                        aria-label="Buka link"
                    >
                        <ExternalLink className="size-4" />
                    </a>

                    {row.is_active && (
                        <ConfirmButton
                            title="Nonaktifkan link ini?"
                            message="Link berhenti melacak klik baru. Komisi yang sudah ada tetap aman."
                            confirmLabel="Ya, nonaktifkan"
                            onConfirm={() => router.delete(`/affiliate/link/${row.id}`)}
                        >
                            <Button variant="ghost" size="icon" aria-label="Nonaktifkan">
                                <Power className="size-4 text-[var(--danger)]" />
                            </Button>
                        </ConfirmButton>
                    )}
                </span>
            ),
        },
    ];

    return (
        <DashboardLayout title="Link Affiliate" area="affiliate">
            <PageHeader
                title="Link Saya"
                description="Bikin varian link per kampanye biar tahu channel mana yang paling jalan."
            />

            {products.length > 0 && (
                <Card className="mb-4">
                    <CardHeader>
                        <CardTitle>Buat link kampanye</CardTitle>
                    </CardHeader>
                    <CardBody>
                        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
                            <Field label="Produk" error={errors.product_id} htmlFor="product" className="sm:col-span-2">
                                <Select
                                    id="product"
                                    value={data.product_id}
                                    onChange={(e) => setData('product_id', Number(e.target.value))}
                                >
                                    {products.map((product) => (
                                        <option key={product.id} value={product.id}>
                                            {product.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label="Campaign" error={errors.campaign} hint="Contoh: ig-story" htmlFor="campaign">
                                <Input
                                    id="campaign"
                                    value={data.campaign}
                                    onChange={(e) => setData('campaign', e.target.value)}
                                    invalid={!!errors.campaign}
                                />
                            </Field>

                            <Field label="Sub ID" error={errors.sub_id} htmlFor="subid">
                                <Input
                                    id="subid"
                                    value={data.sub_id}
                                    onChange={(e) => setData('sub_id', e.target.value)}
                                    invalid={!!errors.sub_id}
                                />
                            </Field>

                            <div className="sm:col-span-4">
                                <Button type="submit" variant="gradient" loading={processing}>
                                    <Plus className="size-4" />
                                    Buat Link
                                </Button>
                            </div>
                        </form>
                    </CardBody>
                </Card>
            )}

            <DataList
                rows={links.data}
                columns={columns}
                rowKey={(row) => row.id}
                empty={
                    <EmptyState
                        title="Belum ada link"
                        description="Gabung ke program affiliate dari marketplace buat dapat link pertamamu."
                        action={
                            <Button variant="gradient" onClick={() => router.visit('/affiliate/marketplace')}>
                                Buka Marketplace
                            </Button>
                        }
                    />
                }
            />

            <Pagination meta={links} />
        </DashboardLayout>
    );
}
