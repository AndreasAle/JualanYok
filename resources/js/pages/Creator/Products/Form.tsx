import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Boxes, ExternalLink, Link2, ShoppingBag, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { ImageUpload, ProductGalleryUpload } from '@/components/image-upload';
import { ProductFiles, type ProductFileItem, type UploadLimits } from '@/components/product-files';
import { ConfirmButton, PageHeader } from '@/components/shared';
import {
    Alert, Button, Card, CardBody, CardHeader, CardTitle, Field, Input, Select, Switch, Textarea,
} from '@/components/ui';
import { detectMarketplace, marketplaceCta } from '@/lib/marketplace';
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
    uploadLimits,
}: {
    product: any | null;
    types: TypeOption[];
    categories: { id: number; name: string }[];
    firstProduct?: boolean;
    uploadLimits?: UploadLimits;
}) {
    const editing = !!product;
    const [tab, setTab] = useState<'umum' | 'harga' | 'file' | 'seo' | 'lanjutan'>('umum');

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
        is_marketplace_listed: product?.is_marketplace_listed ?? false,
        marketplace_category_id: product?.marketplace_category_id ?? product?.product_category_id ?? '',
        sku: product?.sku ?? '',
        weight_gram: product?.weight_gram ?? 500,
        length_cm: product?.length_cm ?? 20,
        width_cm: product?.width_cm ?? 15,
        height_cm: product?.height_cm ?? 10,
        shipping_category: product?.shipping_category ?? 'others',
        is_fragile: product?.is_fragile ?? false,
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
        initial_stock: 0,
        thumbnail: null as File | null,
        gallery: [] as File[],
        removed_media_ids: [] as number[],
    });

    // Laravel may return keys outside the form payload (e.g. plan limits).
    const serverErrors = errors as Record<string, string | undefined>;

    const currentType = types.find((t) => t.value === data.type);
    const isExternal = data.type === 'EXTERNAL';
    const marketplace = detectMarketplace(data.external_url);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            // Multipart bodies are not parsed on PUT, so updates with an upload
            // go out as POST with a method override.
            transform((current) => ({ ...current, _method: 'put' }));
            post(`/dashboard/produk/${product.id}`, {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    setData('thumbnail', null);
                    setData('gallery', []);
                    setData('removed_media_ids', []);
                },
            });
        } else {
            post('/dashboard/produk', { forceFormData: true });
        }
    };

    // Files are the deliverable for digital products, so the tab only appears
    // for that type — and only once the product exists to attach them to.
    const showFilesTab = editing && data.type === 'DIGITAL';

    const TABS = [
        { key: 'umum', label: 'Umum' },
        ...(!isExternal ? ([{ key: 'harga', label: 'Harga & Stok' }] as const) : []),
        ...(showFilesTab
            ? ([{ key: 'file', label: `File${product.files?.length ? ` (${product.files.length})` : ''}` }] as const)
            : []),
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
                <div data-tour="product-price" className="mb-4 flex gap-1 overflow-x-auto rounded-[var(--radius-field)] bg-surface-2 p-1">
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
                                    <Field label="Jenis produk" required error={errors.type} htmlFor="type" data-tour="product-type">
                                        <Select
                                            id="type"
                                            value={data.type}
                                            onChange={(e) => {
                                                const type = e.target.value;

                                                if (type === 'EXTERNAL') {
                                                    setData({
                                                        ...data,
                                                        type,
                                                        price: 0,
                                                        compare_at_price: '',
                                                        is_pay_what_you_want: false,
                                                        minimum_price: '',
                                                        affiliate_enabled: false,
                                                    });
                                                    return;
                                                }

                                                setData('type', type);
                                            }}
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

                                    {isExternal && (
                                        <div className="overflow-hidden rounded-2xl border border-orange-200 bg-orange-50/70 dark:border-orange-400/20 dark:bg-orange-400/[.06]">
                                            <div className="flex items-start gap-3 border-b border-orange-200/70 p-4 dark:border-orange-400/15">
                                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#EE4D2D] text-white">
                                                    <ShoppingBag className="size-5" />
                                                </span>
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-semibold">Produk dari marketplace</p>
                                                        <span className="rounded-full bg-white px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#EE4D2D] shadow-sm dark:bg-white/10">Tanpa checkout</span>
                                                    </div>
                                                    <p className="mt-1 text-xs leading-5 text-muted">Cocok untuk link affiliate atau produk milikmu di Shopee, Tokopedia, TikTok Shop, dan marketplace lain.</p>
                                                </div>
                                            </div>
                                            <div className="space-y-3 p-4">
                                                <Field
                                                    label="Link produk atau link affiliate"
                                                    required
                                                    error={errors.external_url}
                                                    hint="Saat diklik, pengunjung langsung diarahkan ke marketplace."
                                                    htmlFor="external"
                                                >
                                                    <div className="relative">
                                                        <Link2 className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
                                                        <Input
                                                            id="external"
                                                            type="url"
                                                            value={data.external_url}
                                                            onChange={(e) => setData('external_url', e.target.value)}
                                                            className="pl-10"
                                                            placeholder="https://shopee.co.id/... atau https://s.shopee.co.id/..."
                                                            required
                                                        />
                                                    </div>
                                                </Field>

                                                {data.external_url && (
                                                    <div className="flex items-center justify-between gap-3 rounded-xl border border-line bg-surface p-3">
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <span className="grid size-9 shrink-0 place-items-center rounded-lg text-[11px] font-bold text-white" style={{ backgroundColor: marketplace.color }}>{marketplace.shortName}</span>
                                                            <div className="min-w-0"><p className="text-xs font-semibold">{marketplace.name} terdeteksi</p><p className="truncate text-[11px] text-muted">Tombol toko: {marketplaceCta(marketplace.name)}</p></div>
                                                        </div>
                                                        <BadgeCheck className="size-5 shrink-0 text-emerald-500" />
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <Field label="Nama produk" required error={errors.name} htmlFor="name">
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            invalid={!!errors.name}
                                            placeholder={isExternal ? 'Contoh: Blouse Linen Wanita Premium' : 'Contoh: E-book Content Plan 30 Hari'}
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

                                    {currentType?.needs_shipping && !editing && (
                                        <Field label="Stok awal" required error={serverErrors.initial_stock} hint="Jumlah unit yang siap dijual saat produk dibuat." htmlFor="initial-stock">
                                            <Input id="initial-stock" type="number" min={0} value={data.initial_stock} onChange={(e) => setData('initial_stock', Number(e.target.value))} />
                                        </Field>
                                    )}

                                    {currentType?.needs_shipping && editing && (
                                        <StockEditor productId={product.id} inventories={product.inventory ?? []} />
                                    )}
                                </CardBody>
                            </Card>
                        )}

                        {tab === 'file' && showFilesTab && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>File yang diterima pembeli</CardTitle>
                                </CardHeader>
                                <CardBody>
                                    <ProductFiles
                                        productId={product.id}
                                        files={(product.files ?? []) as ProductFileItem[]}
                                        limits={uploadLimits ?? { mimes: ['pdf', 'zip'], max_kb: 204800 }}
                                        isDeliverable={!!product.is_deliverable}
                                        error={serverErrors.file}
                                    />
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

                                    {!isExternal && (
                                        <Switch
                                            checked={data.affiliate_enabled}
                                            onChange={(v) => setData('affiliate_enabled', v)}
                                            label="Izinkan affiliate JualanYok"
                                            description="Orang lain bisa mempromosikan produk internal ini dan mendapat komisi."
                                        />
                                    )}

                                    {currentType?.needs_shipping && (
                                        <div className="space-y-4 rounded-2xl border border-line bg-surface-2 p-4">
                                            <div>
                                                <p className="text-sm font-semibold">Data paket untuk ongkir</p>
                                                <p className="mt-1 text-xs text-muted">Dipakai kurir menghitung tarif nyata. Isi ukuran setelah produk dibungkus.</p>
                                            </div>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field label="Berat paket (gram)" required error={serverErrors.weight_gram} htmlFor="weight-gram">
                                                    <Input id="weight-gram" type="number" min={1} value={data.weight_gram} onChange={(e) => setData('weight_gram', Number(e.target.value))} />
                                                </Field>
                                                <Field label="Kategori pengiriman" error={serverErrors.shipping_category} htmlFor="shipping-category">
                                                    <Select id="shipping-category" value={data.shipping_category} onChange={(e) => setData('shipping_category', e.target.value)}>
                                                        <option value="fashion">Fashion</option><option value="food">Makanan</option><option value="electronics">Elektronik</option><option value="health_beauty">Kesehatan &amp; kecantikan</option><option value="documents">Dokumen</option><option value="others">Lainnya</option>
                                                    </Select>
                                                </Field>
                                            </div>
                                            <div className="grid grid-cols-3 gap-3">
                                                <Field label="Panjang (cm)" required error={serverErrors.length_cm} htmlFor="length-cm"><Input id="length-cm" type="number" min={1} value={data.length_cm} onChange={(e) => setData('length_cm', Number(e.target.value))} /></Field>
                                                <Field label="Lebar (cm)" required error={serverErrors.width_cm} htmlFor="width-cm"><Input id="width-cm" type="number" min={1} value={data.width_cm} onChange={(e) => setData('width_cm', Number(e.target.value))} /></Field>
                                                <Field label="Tinggi (cm)" required error={serverErrors.height_cm} htmlFor="height-cm"><Input id="height-cm" type="number" min={1} value={data.height_cm} onChange={(e) => setData('height_cm', Number(e.target.value))} /></Field>
                                            </div>
                                            <Switch checked={data.is_fragile} onChange={(v) => setData('is_fragile', v)} label="Barang mudah pecah" description="Tandai agar proses packing dan penanganan lebih hati-hati." />
                                        </div>
                                    )}

                                    <div className="rounded-2xl border border-violet-200 bg-violet-50/45 p-4 sm:p-5">
                                        <div className="flex items-start justify-between gap-4">
                                            <div><p className="flex items-center gap-2 text-sm font-semibold"><Boxes className="size-4 text-violet-600" /> Distribusi marketplace</p><p className="mt-1 max-w-xl text-xs leading-5 text-muted">Storefront-mu tetap bekerja seperti biasa. Aktifkan ini kalau produk juga ingin ditemukan dari halaman Jelajahi JualanYok.</p></div>
                                            <Switch checked={data.is_marketplace_listed} onChange={(value) => setData('is_marketplace_listed', value)} label="Ajukan" />
                                        </div>

                                        {data.is_marketplace_listed && <div className="mt-5 space-y-4 border-t border-violet-100 pt-4">
                                            <Field label="Kategori marketplace" required error={serverErrors.marketplace_category_id} htmlFor="marketplace-category">
                                                <Select id="marketplace-category" value={data.marketplace_category_id} onChange={(e) => setData('marketplace_category_id', e.target.value)}>
                                                    <option value="">Pilih kategori paling relevan</option>
                                                    {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                                                </Select>
                                            </Field>
                                            {editing && <div className="rounded-xl border border-line bg-white p-3 text-xs leading-5"><p className="font-semibold">Status: {product.marketplace_status_label}</p>{product.marketplace_status === 'PENDING_REVIEW' && <p className="mt-1 text-muted">Tim JualanYok sedang memeriksa kualitas, keamanan, dan kesesuaian listing.</p>}{product.rejection_reason && <p className="mt-2 rounded-lg bg-rose-50 p-2 text-rose-700"><b>Alasan:</b> {product.rejection_reason}</p>}</div>}
                                            <div className="rounded-xl bg-white p-3"><p className="text-[11px] font-semibold uppercase tracking-wider text-violet-600">Preview pencarian</p><p className="mt-2 line-clamp-1 text-sm font-semibold">{data.name || 'Nama produkmu'}</p><p className="mt-1 line-clamp-2 text-[11px] leading-5 text-muted">{data.short_description || 'Deskripsi singkat akan membantu calon pembeli memahami produkmu.'}</p><p className="mt-2 text-sm font-bold">{isExternal ? 'Cek harga terbaru' : formatIDR(Number(data.price) || 0)}</p></div>
                                        </div>}
                                    </div>

                                    <Field label="Visibilitas storefront" error={errors.visibility} htmlFor="visibility">
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
                                {showFilesTab && !product.is_deliverable && (
                                    <Alert tone="warning" title="Belum ada file">
                                        <span className="text-sm">
                                            Produk disembunyikan dari toko sampai kamu mengunggah file.{' '}
                                            <button
                                                type="button"
                                                onClick={() => setTab('file')}
                                                className="font-bold underline"
                                            >
                                                Buka tab File
                                            </button>
                                        </span>
                                    </Alert>
                                )}

                                <Field label="Status" error={errors.status} htmlFor="status" data-tour="product-publish">
                                    <Select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                    >
                                        <option value="DRAFT">Draft — belum tampil</option>
                                        <option value="ACTIVE">{isExternal ? 'Aktif — tampil di etalase' : 'Aktif — bisa dibeli'}</option>
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
                                <ProductGalleryUpload
                                    existing={product?.media ?? []}
                                    files={data.gallery}
                                    removedIds={data.removed_media_ids}
                                    error={serverErrors.gallery ?? serverErrors['gallery.0']}
                                    onFilesChange={(files) => setData('gallery', files)}
                                    onRemovedIdsChange={(ids) => setData('removed_media_ids', ids)}
                                />
                            </CardBody>
                        </Card>

                        {isExternal ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tujuan pembelian</CardTitle>
                                </CardHeader>
                                <CardBody>
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-11 shrink-0 place-items-center rounded-xl text-xs font-bold text-white" style={{ backgroundColor: marketplace.color }}>{marketplace.shortName}</span>
                                        <div className="min-w-0"><p className="text-sm font-semibold">{marketplaceCta(marketplace.name)}</p><p className="mt-0.5 text-xs text-muted">Harga mengikuti marketplace.</p></div>
                                    </div>
                                    <div className="mt-4 rounded-xl bg-surface-2 p-3 text-xs leading-5 text-muted">
                                        <p className="flex items-center gap-2 font-bold text-fg"><Sparkles className="size-4 text-violet-500" /> Tidak perlu mengisi harga dan stok.</p>
                                        <p className="mt-1">JualanYok berfungsi sebagai etalase dan mencatat klik keluar, bukan memproses pembayaran.</p>
                                    </div>
                                </CardBody>
                            </Card>
                        ) : (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Preview harga</CardTitle>
                                </CardHeader>
                                <CardBody>
                                    <p className="text-2xl font-semibold">
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
                        )}

                        {editing && currentType && !isExternal && (
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

type InventoryItem = {
    id: number;
    variant_id: number | null;
    variant_name?: string | null;
    sku?: string | null;
    quantity: number;
    reserved: number;
    available: number;
    low_stock_threshold: number;
};

function StockEditor({ productId, inventories }: { productId: number; inventories: InventoryItem[] }) {
    if (inventories.length === 0) {
        return <Alert tone="warning" title="Inventori belum tersedia">Simpan perubahan produk sekali lagi untuk membuat inventori.</Alert>;
    }

    return (
        <section className="space-y-3 rounded-2xl border border-line bg-surface-2 p-4">
            <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-700"><Boxes className="size-5" /></span>
                <div>
                    <p className="text-sm font-semibold">Inventori produk</p>
                    <p className="mt-0.5 text-xs text-muted">Setiap perubahan dicatat. Unit dalam checkout tetap dikunci agar tidak terjual dua kali.</p>
                </div>
            </div>
            <div className="space-y-3">
                {inventories.map((inventory) => (
                    <StockRow key={inventory.id} productId={productId} inventory={inventory} />
                ))}
            </div>
        </section>
    );
}

function StockRow({ productId, inventory }: { productId: number; inventory: InventoryItem }) {
    const [quantity, setQuantity] = useState(inventory.quantity);
    const [threshold, setThreshold] = useState(inventory.low_stock_threshold);
    const [reason, setReason] = useState('restock');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setQuantity(inventory.quantity);
        setThreshold(inventory.low_stock_threshold);
    }, [inventory.quantity, inventory.low_stock_threshold]);

    const save = () => {
        setError('');
        router.patch(`/dashboard/produk/${productId}/stok`, {
            inventory_id: inventory.id,
            quantity,
            low_stock_threshold: threshold,
            reason,
            note,
        }, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: () => setNote(''),
            onError: (errors) => setError(String(errors.quantity ?? errors.inventory_id ?? errors.reason ?? 'Stok gagal diperbarui.')),
        });
    };

    return (
        <div className="rounded-xl border border-line bg-surface p-4">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-sm font-bold">{inventory.variant_name || 'Stok utama'}</p>
                    {inventory.sku && <p className="text-xs text-muted">SKU {inventory.sku}</p>}
                </div>
                <div className="flex gap-2 text-center text-xs">
                    <span className="rounded-lg bg-emerald-50 px-3 py-1.5 font-semibold text-emerald-700">{inventory.available} tersedia</span>
                    {inventory.reserved > 0 && <span className="rounded-lg bg-amber-50 px-3 py-1.5 font-semibold text-amber-700">{inventory.reserved} dikunci</span>}
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Total stok" required>
                    <Input type="number" min={inventory.reserved} value={quantity} onChange={(e) => setQuantity(Number(e.target.value))} />
                </Field>
                <Field label="Peringatan stok rendah">
                    <Input type="number" min={0} value={threshold} onChange={(e) => setThreshold(Number(e.target.value))} />
                </Field>
                <Field label="Alasan perubahan">
                    <Select value={reason} onChange={(e) => setReason(e.target.value)}>
                        <option value="restock">Stok masuk / restock</option>
                        <option value="return">Barang dikembalikan</option>
                        <option value="damaged">Rusak atau hilang</option>
                        <option value="correction">Koreksi stok opname</option>
                    </Select>
                </Field>
                <Field label="Catatan" hint="Opsional, untuk jejak tim.">
                    <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Contoh: stok gudang 26 Agustus" />
                </Field>
            </div>
            {error && <p className="mt-3 text-xs font-semibold text-rose-600">{error}</p>}
            <button type="button" onClick={save} disabled={busy || quantity < inventory.reserved} className="mt-4 inline-flex h-10 items-center justify-center rounded-xl bg-[#171722] px-4 text-sm font-bold text-white transition hover:bg-black disabled:opacity-50">
                {busy ? 'Menyimpan...' : 'Perbarui stok'}
            </button>
        </div>
    );
}
