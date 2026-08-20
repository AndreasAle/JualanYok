import { router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ImageUpload } from '@/components/image-upload';
import { ConfirmButton, PageHeader } from '@/components/shared';
import {
    Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Select, Switch, Textarea,
} from '@/components/ui';
import { formatIDR } from '@/lib/utils';

interface TypeOption {
    value: string;
    label: string;
    needs_shipping: boolean;
    auto_fulfilled: boolean;
}

export default function ProductForm({
    product,
    types,
    categories,
    firstProduct = false,
}: {
    product: any | null;
    types: TypeOption[];
    categories: { id: number; name: string }[];
    firstProduct?: boolean;
}) {
    const editing = !!product;
    const [tab, setTab] = useState<'umum' | 'harga' | 'seo' | 'lanjutan'>('umum');

    const { data, setData, post, put, transform, processing, errors, isDirty } = useForm({
        type: product?.type ?? 'DIGITAL',
        name: product?.name ?? '',
        short_description: product?.short_description ?? '',
        description: product?.description ?? '',
        price: product?.price ?? 0,
        compare_at_price: product?.compare_at_price ?? '',
        is_pay_what_you_want: product?.is_pay_what_you_want ?? false,
        minimum_price: product?.minimum_price ?? '',
        status: product?.status ?? (firstProduct ? 'ACTIVE' : 'DRAFT'),
        visibility: product?.visibility ?? 'public',
        product_category_id: product?.product_category_id ?? '',
        sku: product?.sku ?? '',
        tags: product?.tags ?? [],
        min_quantity: product?.min_quantity ?? 1,
        max_quantity: product?.max_quantity ?? '',
        sales_limit: product?.sales_limit ?? '',
        sale_starts_at: product?.sale_starts_at?.slice(0, 16) ?? '',
        sale_ends_at: product?.sale_ends_at?.slice(0, 16) ?? '',
        seo_title: product?.seo_title ?? '',
        seo_description: product?.seo_description ?? '',
        checkout_message: product?.checkout_message ?? '',
        post_purchase_message: product?.post_purchase_message ?? '',
        terms: product?.terms ?? '',
        affiliate_enabled: product?.affiliate_enabled ?? false,
        external_url: product?.external_url ?? '',
        custom_fields: product?.custom_fields ?? [],
        thumbnail: null as File | null,
    });

    // Laravel may return keys outside the form payload (e.g. plan limits).
    const serverErrors = errors as Record<string, string | undefined>;

    const currentType = types.find((t) => t.value === data.type);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            // Multipart bodies are not parsed on PUT, so updates with an upload
            // go out as POST with a method override.
            transform((current) => ({ ...current, _method: 'put' }));
            post(`/dashboard/produk/${product.id}`, {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => setData('thumbnail', null),
            });
        } else {
            post('/dashboard/produk', { forceFormData: true });
        }
    };

    const TABS = [
        { key: 'umum', label: 'Umum' },
        { key: 'harga', label: 'Harga & Stok' },
        { key: 'seo', label: 'SEO & Pesan' },
        { key: 'lanjutan', label: 'Lanjutan' },
    ] as const;

    return (
        <DashboardLayout title={editing ? 'Edit Produk' : 'Produk Baru'} area="creator">
            <PageHeader
                title={editing ? data.name || 'Edit Produk' : firstProduct ? 'Buat produk pertama' : 'Produk Baru'}
                breadcrumbs={[
                    { label: 'Produk', href: '/dashboard/produk' },
                    { label: editing ? 'Edit' : 'Baru' },
                ]}
                actions={
                    editing && (
                        <>
                            <a
                                href={product.public_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex h-11 items-center gap-2 rounded-[var(--radius-field)] border border-line px-4 text-sm font-semibold hover:bg-surface-2"
                            >
                                <ExternalLink className="size-4" />
                                Lihat
                            </a>

                            <ConfirmButton
                                title="Hapus produk ini?"
                                message="Produk akan disembunyikan dari toko. Riwayat pesanan yang sudah ada tetap aman."
                                confirmLabel="Ya, hapus"
                                onConfirm={() => router.delete(`/dashboard/produk/${product.id}`)}
                            >
                                <Button variant="outline">
                                    <Trash2 className="size-4 text-[var(--danger)]" />
                                    Hapus
                                </Button>
                            </ConfirmButton>
                        </>
                    )
                }
            />

            {firstProduct && <Alert tone="info" title="Langkah 2 dari 3 — isi produk pertamamu"><span className="text-sm">Setelah disimpan, kamu langsung masuk ke pratinjau toko untuk langkah publikasi.</span></Alert>}

            {serverErrors.plan && (
                <div className="mb-4">
                    <Alert tone="warning" title="Batas paket tercapai">
                        {serverErrors.plan}
                    </Alert>
                </div>
            )}

            <form onSubmit={submit}>
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

                <div className="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                    <div className="space-y-4">
                        {tab === 'umum' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Informasi produk</CardTitle>
                                </CardHeader>
                                <CardBody className="space-y-4">
                                    <Field label="Jenis produk" required error={errors.type} htmlFor="type">
                                        <Select
                                            id="type"
                                            value={data.type}
                                            onChange={(e) => setData('type', e.target.value)}
                                            disabled={editing}
                                        >
                                            {types.map((type) => (
                                                <option key={type.value} value={type.value}>
                                                    {type.label}
                                                </option>
                                            ))}
                                        </Select>
                                        {editing && (
                                            <p className="mt-1.5 text-xs text-muted">
                                                Jenis produk nggak bisa diganti setelah dibuat.
                                            </p>
                                        )}
                                    </Field>

                                    <Field label="Nama produk" required error={errors.name} htmlFor="name">
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            invalid={!!errors.name}
                                            placeholder="Contoh: E-book Content Plan 30 Hari"
                                            required
                                        />
                                    </Field>

                                    <Field
                                        label="Deskripsi singkat"
                                        error={errors.short_description}
                                        hint="Muncul di kartu produk. Maksimal 500 karakter."
                                        htmlFor="short"
                                    >
                                        <Textarea
                                            id="short"
                                            rows={2}
                                            value={data.short_description}
                                            onChange={(e) => setData('short_description', e.target.value)}
                                        />
                                    </Field>

                                    <Field label="Deskripsi lengkap" error={errors.description} htmlFor="description">
                                        <Textarea
                                            id="description"
                                            rows={8}
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Jelaskan isi produk, siapa yang cocok beli, dan apa yang mereka dapat."
                                        />
                                    </Field>

                                    <Field label="Kategori" error={errors.product_category_id} htmlFor="category">
                                        <Select
                                            id="category"
                                            value={data.product_category_id}
                                            onChange={(e) => setData('product_category_id', e.target.value)}
                                        >
                                            <option value="">— tanpa kategori —</option>
                                            {categories.map((category) => (
                                                <option key={category.id} value={category.id}>
                                                    {category.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>

                                    {data.type === 'EXTERNAL' && (
                                        <Field
                                            label="URL produk"
                                            required
                                            error={errors.external_url}
                                            hint="Pembeli diarahkan ke link ini."
                                            htmlFor="external"
                                        >
                                            <Input
                                                id="external"
                                                type="url"
                                                value={data.external_url}
                                                onChange={(e) => setData('external_url', e.target.value)}
                                            />
                                        </Field>
                                    )}
                                </CardBody>
                            </Card>
                        )}

                        {tab === 'harga' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Harga &amp; ketersediaan</CardTitle>
                                </CardHeader>
                                <CardBody className="space-y-4">
                                    <Switch
                                        checked={data.is_pay_what_you_want}
                                        onChange={(v) => setData('is_pay_what_you_want', v)}
                                        label="Bayar seikhlasnya"
                                        description="Pembeli menentukan sendiri harganya, dengan batas minimum."
                                    />

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            label={data.is_pay_what_you_want ? 'Harga saran' : 'Harga'}
                                            required
                                            error={errors.price}
                                            htmlFor="price"
                                        >
                                            <Input
                                                id="price"
                                                type="number"
                                                min={0}
                                                step={1000}
                                                value={data.price}
                                                onChange={(e) => setData('price', Number(e.target.value))}
                                                invalid={!!errors.price}
                                            />
                                        </Field>

                                        {data.is_pay_what_you_want ? (
                                            <Field label="Harga minimum" error={errors.minimum_price} htmlFor="minimum">
                                                <Input
                                                    id="minimum"
                                                    type="number"
                                                    min={0}
                                                    step={1000}
                                                    value={data.minimum_price}
                                                    onChange={(e) => setData('minimum_price', e.target.value)}
                                                />
                                            </Field>
                                        ) : (
                                            <Field
                                                label="Harga coret"
                                                error={errors.compare_at_price}
                                                hint="Buat nunjukin diskon."
                                                htmlFor="compare"
                                            >
                                                <Input
                                                    id="compare"
                                                    type="number"
                                                    min={0}
                                                    step={1000}
                                                    value={data.compare_at_price}
                                                    onChange={(e) => setData('compare_at_price', e.target.value)}
                                                    invalid={!!errors.compare_at_price}
                                                />
                                            </Field>
                                        )}
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Minimal beli" error={errors.min_quantity} htmlFor="min-qty">
                                            <Input
                                                id="min-qty"
                                                type="number"
                                                min={1}
                                                value={data.min_quantity}
                                                onChange={(e) => setData('min_quantity', Number(e.target.value))}
                                            />
                                        </Field>

                                        <Field label="Maksimal beli" error={errors.max_quantity} hint="Kosongkan = bebas" htmlFor="max-qty">
                                            <Input
                                                id="max-qty"
                                                type="number"
                                                min={1}
                                                value={data.max_quantity}
                                                onChange={(e) => setData('max_quantity', e.target.value)}
                                            />
                                        </Field>
                                    </div>

                                    <Field
                                        label="Batas total penjualan"
                                        error={errors.sales_limit}
                                        hint="Produk otomatis berhenti dijual setelah angka ini tercapai."
                                        htmlFor="sales-limit"
                                    >
                                        <Input
                                            id="sales-limit"
                                            type="number"
                                            min={1}
                                            value={data.sales_limit}
                                            onChange={(e) => setData('sales_limit', e.target.value)}
                                        />
                                    </Field>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Mulai dijual" error={errors.sale_starts_at} htmlFor="sale-start">
                                            <Input
                                                id="sale-start"
                                                type="datetime-local"
                                                value={data.sale_starts_at}
                                                onChange={(e) => setData('sale_starts_at', e.target.value)}
                                            />
                                        </Field>

                                        <Field label="Berhenti dijual" error={errors.sale_ends_at} htmlFor="sale-end">
                                            <Input
                                                id="sale-end"
                                                type="datetime-local"
                                                value={data.sale_ends_at}
                                                onChange={(e) => setData('sale_ends_at', e.target.value)}
                                                invalid={!!errors.sale_ends_at}
                                            />
                                        </Field>
                                    </div>

                                    {currentType?.needs_shipping && editing && (
                                        <Alert tone="info" title="Stok produk fisik">
                                            Stok tercatat di{' '}
                                            <strong>{product?.inventory?.[0]?.available ?? 0}</strong> unit tersedia.
                                            Stok dikunci saat checkout supaya nggak kejual dobel.
                                        </Alert>
                                    )}
                                </CardBody>
                            </Card>
                        )}

                        {tab === 'seo' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>SEO &amp; pesan ke pembeli</CardTitle>
                                </CardHeader>
                                <CardBody className="space-y-4">
                                    <Field label="Judul SEO" error={errors.seo_title} htmlFor="seo-title">
                                        <Input
                                            id="seo-title"
                                            value={data.seo_title}
                                            onChange={(e) => setData('seo_title', e.target.value)}
                                            placeholder={data.name}
                                        />
                                    </Field>

                                    <Field label="Meta description" error={errors.seo_description} htmlFor="seo-desc">
                                        <Textarea
                                            id="seo-desc"
                                            rows={2}
                                            value={data.seo_description}
                                            onChange={(e) => setData('seo_description', e.target.value)}
                                        />
                                    </Field>

                                    <Field
                                        label="Pesan di halaman checkout"
                                        error={errors.checkout_message}
                                        hint="Muncul sebelum pembeli bayar."
                                        htmlFor="checkout-msg"
                                    >
                                        <Textarea
                                            id="checkout-msg"
                                            rows={2}
                                            value={data.checkout_message}
                                            onChange={(e) => setData('checkout_message', e.target.value)}
                                        />
                                    </Field>

                                    <Field
                                        label="Pesan setelah pembelian"
                                        error={errors.post_purchase_message}
                                        hint="Dikirim di struk dan ditampilkan di halaman pembelian."
                                        htmlFor="post-msg"
                                    >
                                        <Textarea
                                            id="post-msg"
                                            rows={3}
                                            value={data.post_purchase_message}
                                            onChange={(e) => setData('post_purchase_message', e.target.value)}
                                        />
                                    </Field>

                                    <Field label="Syarat khusus produk" error={errors.terms} htmlFor="terms">
                                        <Textarea
                                            id="terms"
                                            rows={3}
                                            value={data.terms}
                                            onChange={(e) => setData('terms', e.target.value)}
                                        />
                                    </Field>
                                </CardBody>
                            </Card>
                        )}

                        {tab === 'lanjutan' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Pengaturan lanjutan</CardTitle>
                                </CardHeader>
                                <CardBody className="space-y-4">
                                    <Field label="SKU" error={errors.sku} htmlFor="sku">
                                        <Input
                                            id="sku"
                                            value={data.sku}
                                            onChange={(e) => setData('sku', e.target.value)}
                                        />
                                    </Field>

                                    <Switch
                                        checked={data.affiliate_enabled}
                                        onChange={(v) => setData('affiliate_enabled', v)}
                                        label="Izinkan affiliate"
                                        description="Orang lain bisa promosiin produk ini dan dapat komisi."
                                    />

                                    <Field label="Visibilitas" error={errors.visibility} htmlFor="visibility">
                                        <Select
                                            id="visibility"
                                            value={data.visibility}
                                            onChange={(e) => setData('visibility', e.target.value)}
                                        >
                                            <option value="public">Publik — tampil di toko</option>
                                            <option value="unlisted">Unlisted — cuma lewat link</option>
                                            <option value="private">Private — nggak bisa dibeli</option>
                                        </Select>
                                    </Field>
                                </CardBody>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                        <Card>
                            <CardHeader>
                                <CardTitle>Publikasi</CardTitle>
                            </CardHeader>
                            <CardBody className="space-y-4">
                                <Field label="Status" error={errors.status} htmlFor="status">
                                    <Select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                    >
                                        <option value="DRAFT">Draft — belum tampil</option>
                                        <option value="ACTIVE">Aktif — bisa dibeli</option>
                                        <option value="ARCHIVED">Arsip</option>
                                    </Select>
                                </Field>

                                <Button type="submit" variant="gradient" block size="lg" loading={processing}>
                                    {editing ? 'Simpan Perubahan' : 'Buat Produk'}
                                </Button>

                                {editing && isDirty && (
                                    <p className="text-center text-xs text-[var(--warning)]">
                                        Ada perubahan yang belum disimpan.
                                    </p>
                                )}
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Gambar produk</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <ImageUpload
                                    label="Thumbnail"
                                    hint="Rasio 1:1 paling rapi di katalog. Maksimal 4 MB."
                                    currentUrl={product?.thumbnail_url}
                                    aspect="square"
                                    error={serverErrors.thumbnail}
                                    onSelect={(file) => setData('thumbnail', file)}
                                />
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Preview harga</CardTitle>
                            </CardHeader>
                            <CardBody>
                                <p className="text-2xl font-extrabold">
                                    {data.is_pay_what_you_want
                                        ? `Mulai ${formatIDR(Number(data.minimum_price) || 0)}`
                                        : formatIDR(Number(data.price) || 0)}
                                </p>
                                {data.compare_at_price && (
                                    <p className="text-sm text-muted line-through">
                                        {formatIDR(Number(data.compare_at_price))}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-muted">
                                    Biaya platform dipotong dari nominal ini saat pembayaran masuk.
                                </p>
                            </CardBody>
                        </Card>

                        {editing && currentType && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Pengiriman</CardTitle>
                                </CardHeader>
                                <CardBody>
                                    <p className="text-sm text-muted">
                                        {currentType.auto_fulfilled
                                            ? 'Produk ini dikirim otomatis begitu pembayaran lunas.'
                                            : currentType.needs_shipping
                                              ? 'Kamu perlu input nomor resi setelah barang dikirim.'
                                              : 'Perlu tindakan manual dari kamu setelah pembayaran.'}
                                    </p>
                                </CardBody>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </DashboardLayout>
    );
}
