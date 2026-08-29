import { Check, Share2 } from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { cn } from '@/lib/utils';

export function ShareProductButton({
    url,
    title,
    label = false,
    className,
}: {
    url: string;
    title: string;
    label?: boolean;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);

    const share = async (event: MouseEvent<HTMLButtonElement>) => {
        event.preventDefault();
        event.stopPropagation();

        const absoluteUrl = new URL(url, window.location.origin).toString();

        try {
            if (navigator.share) {
                await navigator.share({ title, text: `Lihat ${title} di JualanYok`, url: absoluteUrl });
                return;
            }

            await navigator.clipboard.writeText(absoluteUrl);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        } catch (error) {
            // Closing the native share sheet is not an application error.
            if (error instanceof DOMException && error.name === 'AbortError') return;

            const fallback = document.createElement('textarea');
            fallback.value = absoluteUrl;
            fallback.style.position = 'fixed';
            fallback.style.opacity = '0';
            document.body.appendChild(fallback);
            fallback.select();
            document.execCommand('copy');
            fallback.remove();
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        }
    };

    return (
        <button
            type="button"
            onClick={share}
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-xl border border-[var(--sf-line)] bg-[var(--sf-card)] font-bold transition hover:border-[var(--sf-primary)] hover:text-[var(--sf-primary)]',
                label ? 'h-11 px-4 text-sm' : 'size-9',
                className,
            )}
            aria-label={`Bagikan ${title}`}
            title={copied ? 'Link tersalin' : 'Bagikan produk'}
        >
            {copied ? <Check className="size-4" /> : <Share2 className="size-4" />}
            {label && <span>{copied ? 'Link tersalin' : 'Bagikan'}</span>}
        </button>
    );
}
