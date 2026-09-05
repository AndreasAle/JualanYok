import { ArrowRight, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { buildStorefrontTheme } from '@/lib/storefront-theme';

type Theme = ReturnType<typeof buildStorefrontTheme>;

/*
 * Leaving the platform.
 *
 * A buyer who pays over WhatsApp has no order, no escrow, and nothing for
 * support to refund — the protection lives in the checkout they just skipped.
 * That has to be said before they leave, in the same breath as the button that
 * takes them out, and it has to be said by us: a seller running the scam is
 * not going to warn anyone about it.
 *
 * So it is rendered client-side, always, and nothing a shop controls can hide
 * or reword it.
 */

/** The three things that stop being true the moment the conversation moves. */
export const OUTSIDE_RISKS = [
    'Pembayaran di luar JualanYok tidak punya perlindungan pesanan, escrow, maupun jalur refund.',
    'Penipuan, phising, atau barang tidak dikirim pada transaksi di luar sistem berada di luar tanggung jawab JualanYok.',
    'Jangan pernah mengirim kode OTP, PIN, atau data kartu ke siapa pun — termasuk yang mengaku admin JualanYok.',
];

/**
 * The system's first word in every conversation.
 *
 * Pinned above the thread rather than inserted as a message, so it cannot be
 * scrolled past on a long chat and cannot be deleted by either side.
 */
export function ChatSafetyNotice() {
    return (
        <div className="rounded-xl border border-amber-300/70 bg-amber-50 px-3 py-2.5 text-[0.6875rem] leading-5 text-amber-900">
            <p className="flex items-center gap-1.5 font-bold">
                <ShieldAlert className="size-3.5 shrink-0" />
                Pesan otomatis dari JualanYok
            </p>
            <p className="mt-1">
                Halo! Kalau penjual mengarahkan kamu keluar dari aplikasi dan transaksi terjadi di luar sistem,
                penipuan atau phising yang terjadi <strong>bukan tanggung jawab kami</strong> — karena kami tidak bisa
                menahan dana atau mengembalikannya. Selesaikan pembayaran lewat tombol checkout di JualanYok ya.
            </p>
        </div>
    );
}

/**
 * The gate in front of an outbound link.
 *
 * A single extra tap, deliberately: the point is not to block the buyer — some
 * genuinely want to ask over WhatsApp — but to make sure nobody arrives there
 * believing the platform is still standing behind the transaction.
 */
export function LeavingAppDialog({
    href,
    theme,
    onCancel,
    footer,
}: {
    href: string;
    theme: Theme;
    onCancel: () => void;
    footer?: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900">
                <ShieldAlert className="mt-0.5 size-5 shrink-0" />
                <div className="text-[0.8125rem] leading-6">
                    <p className="font-black">Kamu akan keluar dari JualanYok</p>
                    <ul className="mt-2 space-y-1.5">
                        {OUTSIDE_RISKS.map((risk) => (
                            <li key={risk} className="flex gap-1.5">
                                <span aria-hidden>•</span>
                                <span>{risk}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                onClick={onCancel}
                className="flex h-12 w-full items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50 px-5 text-sm font-extrabold text-emerald-800 transition hover:bg-emerald-100"
            >
                Saya mengerti, lanjut ke WhatsApp
                <ArrowRight className="size-4" />
            </a>

            <button
                type="button"
                onClick={onCancel}
                className={cn('h-11 w-full rounded-xl border text-sm font-bold', theme.line)}
            >
                Batal, saya beli lewat JualanYok saja
            </button>

            {footer}
        </div>
    );
}
