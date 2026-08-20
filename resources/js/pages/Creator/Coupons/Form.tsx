import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Button, Card, CardBody, Field, Input, Select, Switch } from '@/components/ui';

export default function CouponForm({
    coupon,
    products,
}: {
    coupon: any | null;
    products: { id: number; name: string }[];
}) {
    const editing = !!coupon;

    const { data, setData, post, put, processing, errors } = useForm({
        code: coupon?.code ?? '',
        type: coupon?.type ?? 'percentage',
        value: coupon?.value ?? 10,
        max_discount: coupon?.max_discount ?? '',
        min_order_amount: coupon?.min_order_amount ?? 0,
        usage_limit: coupon?.usage_limit ?? '',
        usage_limit_per_customer: coupon?.usage_limit_per_customer ?? '',
        product_ids: coupon?.product_ids ?? [],
        starts_at: coupon?.starts_at ?? '',
        ends_at: coupon?.ends_at ?? '',
        is_active: coupon?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            put(`/dashboard/kupon/${coupon.id}`);
        } else {
            post('/dashboard/kupon');
        }
    };

    return (
        <DashboardLayout title={editing ? 'Edit Kupon' : 'Kupon Baru'} area="creator">
            <PageHeader
                title={editing ? `Edit ${coupon.code}` : 'Kupon Baru'}
                breadcrumbs={[{ label: 'Kupon', href: '/dashboard/kupon' }, { label: editing ? 'Edit' : 'Baru' }]}
            />

            <form onSubmit={submit} className="max-w-2xl">
                <Card>
                    <CardBody className="space-y-4">
                        <Field
                            label="Kode kupon"
                            required
                            error={errors.code}
                            hint="Huruf kapital, angka, - dan _."
                            htmlFor="code"
                        >
                            <Input
                                id="code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                invalid={!!errors.code}
                                className="font-mono uppercase"
                                placeholder="HEMAT20"
                                required
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Jenis diskon" required error={errors.type} htmlFor="type">
                                <Select
                                    id="type"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    <option value="percentage">Persentase (%)</option>
                                    <option value="fixed">Nominal tetap (Rp)</option>
                                </Select>
                            </Field>

                            <Field
                                label={data.type === 'percentage' ? 'Besar diskon (%)' : 'Nominal diskon'}
                                required
                                error={errors.value}
                                htmlFor="value"
                            >
                                <Input
                                    id="value"
                                    type="number"
                                    min={1}
                                    max={data.type === 'percentage' ? 100 : undefined}
                                    value={data.value}
                                    onChange={(e) => setData('value', Number(e.target.value))}
                                    invalid={!!errors.value}
                                />
                            </Field>
                        </div>

                        {data.type === 'percentage' && (
                            <Field
                                label="Maksimal diskon"
                                error={errors.max_discount}
                                hint="Batas atas nominal potongan. Kosongkan kalau nggak ada batas."
                                htmlFor="max-discount"
                            >
                                <Input
                                    id="max-discount"
                                    type="number"
                                    min={0}
                                    step={1000}
                                    value={data.max_discount}
                                    onChange={(e) => setData('max_discount', e.target.value)}
                                />
                            </Field>
                        )}

                        <Field label="Minimal belanja" error={errors.min_order_amount} htmlFor="min-order">
                            <Input
                                id="min-order"
                                type="number"
                                min={0}
                                step={1000}
                                value={data.min_order_amount}
                                onChange={(e) => setData('min_order_amount', Number(e.target.value))}
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Batas total pemakaian"
                                error={errors.usage_limit}
                                hint="Kosongkan = tanpa batas"
                                htmlFor="usage-limit"
                            >
                                <Input
                                    id="usage-limit"
                                    type="number"
                                    min={1}
                                    value={data.usage_limit}
                                    onChange={(e) => setData('usage_limit', e.target.value)}
                                />
                            </Field>

                            <Field
                                label="Batas per pembeli"
                                error={errors.usage_limit_per_customer}
                                htmlFor="usage-per"
                            >
                                <Input
                                    id="usage-per"
                                    type="number"
                                    min={1}
                                    value={data.usage_limit_per_customer}
                                    onChange={(e) => setData('usage_limit_per_customer', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Mulai berlaku" error={errors.starts_at} htmlFor="starts">
                                <Input
                                    id="starts"
                                    type="date"
                                    value={data.starts_at}
                                    onChange={(e) => setData('starts_at', e.target.value)}
                                />
                            </Field>

                            <Field label="Berakhir" error={errors.ends_at} htmlFor="ends">
                                <Input
                                    id="ends"
                                    type="date"
                                    value={data.ends_at}
                                    onChange={(e) => setData('ends_at', e.target.value)}
                                    invalid={!!errors.ends_at}
                                />
                            </Field>
                        </div>

                        <Field
                            label="Berlaku untuk produk"
                            error={errors.product_ids}
                            hint="Kosongkan biar berlaku untuk semua produk."
                            htmlFor="products"
                        >
                            <Select
                                id="products"
                                multiple
                                size={Math.min(6, Math.max(3, products.length))}
                                value={(data.product_ids ?? []).map(String)}
                                onChange={(e) =>
                                    setData(
                                        'product_ids',
                                        Array.from(e.target.selectedOptions).map((o) => Number(o.value)),
                                    )
                                }
                                className="h-auto"
                            >
                                {products.map((product) => (
                                    <option key={product.id} value={product.id}>
                                        {product.name}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Switch
                            checked={data.is_active}
                            onChange={(v) => setData('is_active', v)}
                            label="Aktifkan kupon"
                            description="Matikan buat menonaktifkan sementara tanpa menghapus."
                        />

                        <div className="flex gap-2 pt-2">
                            <Button type="submit" variant="gradient" loading={processing}>
                                {editing ? 'Simpan Perubahan' : 'Buat Kupon'}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => history.back()}>
                                Batal
                            </Button>
                        </div>
                    </CardBody>
                </Card>
            </form>
        </DashboardLayout>
    );
}
