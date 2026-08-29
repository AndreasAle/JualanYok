import { ArrowRight, MessageCircle, ShieldCheck, ShoppingBag, X } from 'lucide-react';
import type { StorefrontTheme } from '@/lib/storefront-theme';
import { cn, formatIDR } from '@/lib/utils';
import type { StorefrontProduct } from '@/types';

function whatsappUrl(phone: string, product: StorefrontProduct): string {
    let digits = phone.replace(/\D/g, '');
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;

    const fallbackUrl = typeof window !== 'undefined' ? window.location.href : '';
    const message = [
        `Halo, saya tertarik dengan ${product.name}.`,
        'Boleh konsultasi dulu sebelum saya melanjutkan pembelian?',
        product.share_url ?? fallbackUrl,
    ].join('\n');

    return `https://wa.me/${digits}?text=${encodeURIComponent(message)}`;
}

/** First, low-friction decision before checkout. Kept separate from the large
 * checkout form so a failed shipping request can never leave a blank overlay. */
export function PurchaseChoiceSheet({
    product,
    storeName,
    whatsapp,
    theme,
    onBuyDirect,
    onClose,
}: {
    product: StorefrontProduct;
    storeName: string;
    whatsapp: string | null;
    theme: StorefrontTheme;
    onBuyDirect: () => void;
    onClose: () => void;
}) {
    const hasWhatsapp = !!whatsapp?.replace(/\D/g, '');

    return (
        <div className="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/65 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="dialog" aria-modal="true" aria-labelledby="purchase-choice-title">
            <button type="button" className="absolute inset-0 cursor-default" onClick={onClose} aria-label="Tutup pilihan pembelian" />

            <section className={cn(theme.card, 'relative z-10 w-full max-w-md overflow-hidden rounded-b-none p-5 shadow-2xl sm:rounded-[1.75rem] sm:p-6')}>
                <div className="flex items-start gap-4">
                    <span className="size-16 shrink-0 overflow-hidden rounded-2xl border border-[var(--sf-line)] bg-[var(--sf-bg)]">
                        {product.thumbnail_url ? <img src={product.thumbnail_url} alt="" className="size-full object-cover" /> : <span className="grid size-full place-items-center"><ShoppingBag className="size-6 opacity-35" /></span>}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-[10px] font-black uppercase tracking-[.16em] text-[var(--sf-primary)]">Beli dari {storeName}</p>
                        <h2 id="purchase-choice-title" className="mt-1 line-clamp-2 text-lg font-black leading-snug">{product.name}</h2>
                        <p className="mt-1 text-sm font-extrabold text-[var(--sf-primary)]">{product.is_pay_what_you_want ? `Mulai ${formatIDR(product.minimum_price ?? 0)}` : formatIDR(product.price)}</p>
                    </div>
                    <button type="button" onClick={onClose} className="grid size-9 shrink-0 place-items-center rounded-xl border border-[var(--sf-line)]" aria-label="Tutup"><X className="size-4" /></button>
                </div>

                <div className="my-5 border-t border-[var(--sf-line)]" />
                <h3 className="text-xl font-black tracking-tight">Mau lanjut lewat mana?</h3>
                <p className={cn('mt-1.5 text-sm leading-6', theme.muted)}>Kalau masih ada yang ingin dipastikan, ngobrol dulu. Kalau sudah cocok, pembayaran tetap aman diproses di JualanYok.</p>

                <button type="button" onClick={onBuyDirect} className={cn(theme.btnPrimary, 'mt-5 h-13 w-full justify-between px-5 text-base shadow-md')}>
                    <span className="inline-flex items-center gap-2"><ShoppingBag className="size-5" /> Beli langsung</span>
                    <ArrowRight className="size-4" />
                </button>

                {hasWhatsapp ? (
                    <a href={whatsappUrl(whatsapp!, product)} target="_blank" rel="noopener noreferrer" className="mt-2.5 flex h-13 w-full items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50 px-5 text-base font-extrabold text-emerald-800 transition hover:bg-emerald-100">
                        <span className="inline-flex items-center gap-2"><MessageCircle className="size-5" /> Konsultasi via WhatsApp</span>
                        <ArrowRight className="size-4" />
                    </a>
                ) : (
                    <p className={cn('mt-3 rounded-xl bg-[var(--sf-bg)] px-4 py-3 text-xs leading-5', theme.muted)}>Toko ini belum mengaktifkan konsultasi WhatsApp. Kamu tetap bisa lanjut membeli langsung.</p>
                )}

                <p className={cn('mt-4 flex items-center justify-center gap-1.5 text-[11px]', theme.muted)}><ShieldCheck className="size-3.5 text-emerald-500" /> Checkout dan pembayaran tetap dilakukan di JualanYok.</p>
            </section>
        </div>
    );
}
