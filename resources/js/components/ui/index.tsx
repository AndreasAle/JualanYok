import { cva, type VariantProps } from 'class-variance-authority';
import {
    forwardRef,
    type ButtonHTMLAttributes,
    type HTMLAttributes,
    type InputHTMLAttributes,
    type ReactNode,
    type SelectHTMLAttributes,
    type TextareaHTMLAttributes,
} from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/* ==========================================================================
   Button
   ========================================================================== */

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 font-semibold whitespace-nowrap transition-all duration-200 ' +
        'disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 ' +
        '[&_svg]:size-4 [&_svg]:shrink-0 active:scale-[0.98]',
    {
        variants: {
            variant: {
                primary:
                    'bg-[var(--primary)] text-[var(--primary-fg)] shadow-soft hover:brightness-110 hover:shadow-lift',
                gradient: 'gradient-brand text-white shadow-soft hover:shadow-lift hover:brightness-105',
                secondary: 'bg-surface-2 text-fg border border-line hover:bg-[var(--border)]',
                outline: 'border border-line bg-transparent text-fg hover:bg-surface-2',
                ghost: 'bg-transparent text-muted hover:bg-surface-2 hover:text-fg',
                danger: 'bg-[var(--danger)] text-white shadow-soft hover:brightness-110',
                success: 'bg-[var(--success)] text-white shadow-soft hover:brightness-110',
                link: 'bg-transparent text-[var(--primary)] underline-offset-4 hover:underline p-0 h-auto',
            },
            size: {
                sm: 'h-9 px-3 text-sm rounded-[var(--radius-field)]',
                md: 'h-11 px-5 text-sm rounded-[var(--radius-field)]',
                lg: 'h-13 px-7 text-base rounded-[var(--radius-field)]',
                icon: 'size-10 rounded-[var(--radius-field)]',
                pill: 'h-11 px-6 text-sm rounded-full',
            },
            block: { true: 'w-full', false: '' },
        },
        defaultVariants: { variant: 'primary', size: 'md', block: false },
    },
);

export interface ButtonProps
    extends ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, block, loading, children, disabled, ...props }, ref) => (
        <button
            ref={ref}
            className={cn(buttonVariants({ variant, size, block }), className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && <Spinner className="size-4" />}
            {children}
        </button>
    ),
);
Button.displayName = 'Button';

/**
 * Inertia's Link has its own event prop types that clash with anchor
 * attributes, so this accepts only the handful of pass-through props we use.
 */
export function ButtonLink({
    href,
    variant,
    size,
    block,
    className,
    children,
    ...props
}: {
    href: string;
    children: ReactNode;
    className?: string;
    title?: string;
    'aria-label'?: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    preserveScroll?: boolean;
} & VariantProps<typeof buttonVariants>) {
    return (
        <Link href={href} className={cn(buttonVariants({ variant, size, block }), className)} {...props}>
            {children}
        </Link>
    );
}

/* ==========================================================================
   Surfaces
   ========================================================================== */

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-[1.35rem] border border-black/[.065] bg-surface shadow-[0_1px_2px_rgba(16,24,40,.03),0_12px_32px_rgba(31,24,52,.055)] dark:border-white/10',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('p-5 pb-0 sm:p-6 sm:pb-0', className)} {...props} />;
}

export function CardTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
    return <h3 className={cn('text-base font-extrabold tracking-[-.015em]', className)} {...props} />;
}

export function CardDescription({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-sm text-muted mt-1', className)} {...props} />;
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('p-5 sm:p-6', className)} {...props} />;
}

export function CardFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn('p-5 sm:p-6 pt-0 flex items-center gap-3 flex-wrap', className)} {...props} />
    );
}

/* ==========================================================================
   Badge
   ========================================================================== */

const badgeVariants = cva(
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
    {
        variants: {
            tone: {
                neutral: 'bg-surface-2 text-muted border border-line',
                brand: 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200',
                success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                danger: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                info: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

export function Badge({
    className,
    tone,
    ...props
}: HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>) {
    return <span className={cn(badgeVariants({ tone }), className)} {...props} />;
}

/** Maps a domain status string to a badge tone, used across every table. */
export function statusTone(status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand' {
    const map: Record<string, ReturnType<typeof statusTone>> = {
        PAID: 'success',
        COMPLETED: 'success',
        APPROVED: 'success',
        ACTIVE: 'success',
        DELIVERED: 'success',
        FULFILLED: 'success',
        PROCESSING: 'info',
        SHIPPED: 'info',
        PENDING: 'warning',
        PENDING_PAYMENT: 'warning',
        REQUESTED: 'warning',
        UNDER_REVIEW: 'warning',
        TRIALING: 'brand',
        PAST_DUE: 'warning',
        DRAFT: 'neutral',
        UNFULFILLED: 'neutral',
        CANCELLED: 'neutral',
        EXPIRED: 'neutral',
        ARCHIVED: 'neutral',
        FAILED: 'danger',
        REJECTED: 'danger',
        REFUNDED: 'danger',
        DISPUTED: 'danger',
        SUSPENDED: 'danger',
        REVERSED: 'danger',
    };

    return map[status] ?? 'neutral';
}

/* ==========================================================================
   Form controls
   ========================================================================== */

export function Label({
    className,
    required,
    ...props
}: HTMLAttributes<HTMLLabelElement> & { htmlFor?: string; required?: boolean }) {
    return (
        <label className={cn('block text-sm font-semibold mb-1.5', className)} {...props}>
            {props.children}
            {required && <span className="text-[var(--danger)] ml-0.5">*</span>}
        </label>
    );
}

const fieldClass =
    'w-full rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm shadow-[0_1px_2px_rgba(16,24,40,.025)] ' +
    'placeholder:text-muted/70 transition-all focus:border-[var(--ring)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--ring)_15%,transparent)] ' +
    'disabled:opacity-60 disabled:cursor-not-allowed';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(
    ({ className, invalid, ...props }, ref) => (
        <input
            ref={ref}
            className={cn(fieldClass, invalid && 'border-[var(--danger)]', className)}
            aria-invalid={invalid || undefined}
            {...props}
        />
    ),
);
Input.displayName = 'Input';

export const Textarea = forwardRef<
    HTMLTextAreaElement,
    TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }
>(({ className, invalid, ...props }, ref) => (
    <textarea
        ref={ref}
        className={cn(fieldClass, 'min-h-24 resize-y', invalid && 'border-[var(--danger)]', className)}
        aria-invalid={invalid || undefined}
        {...props}
    />
));
Textarea.displayName = 'Textarea';

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
    ({ className, ...props }, ref) => (
        <select ref={ref} className={cn(fieldClass, 'pr-8 cursor-pointer', className)} {...props} />
    ),
);
Select.displayName = 'Select';

export function Field({
    label,
    error,
    hint,
    required,
    htmlFor,
    children,
    className,
}: {
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    htmlFor?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('w-full', className)}>
            {label && (
                <Label htmlFor={htmlFor} required={required}>
                    {label}
                </Label>
            )}
            {children}
            {error ? (
                <p className="mt-1.5 text-xs font-medium text-[var(--danger)]" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="mt-1.5 text-xs text-muted">{hint}</p>
            ) : null}
        </div>
    );
}

export function Switch({
    checked,
    onChange,
    label,
    description,
    disabled,
}: {
    checked: boolean;
    onChange: (value: boolean) => void;
    label: string;
    description?: string;
    disabled?: boolean;
}) {
    return (
        <label
            className={cn(
                'flex items-start gap-3 cursor-pointer select-none',
                disabled && 'opacity-60 cursor-not-allowed',
            )}
        >
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                disabled={disabled}
                onClick={() => !disabled && onChange(!checked)}
                className={cn(
                    'relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors',
                    checked ? 'bg-[var(--primary)]' : 'bg-[var(--border)]',
                )}
            >
                <span
                    className={cn(
                        'absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform',
                        checked && 'translate-x-5',
                    )}
                />
            </button>
            <span className="min-w-0">
                <span className="block text-sm font-semibold">{label}</span>
                {description && <span className="block text-xs text-muted mt-0.5">{description}</span>}
            </span>
        </label>
    );
}

/* ==========================================================================
   Feedback & state
   ========================================================================== */

export function Spinner({ className }: { className?: string }) {
    return (
        <svg className={cn('animate-spin', className)} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" className="opacity-25" />
            <path
                d="M22 12a10 10 0 0 1-10 10"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
            />
        </svg>
    );
}

export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('skeleton rounded-[var(--radius-field)]', className)} aria-hidden="true" />;
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            {icon && (
                <div className="mb-4 grid size-14 place-items-center rounded-2xl border border-line bg-surface-2 text-[var(--primary)] shadow-sm">
                    {icon}
                </div>
            )}
            <h3 className="text-base font-bold">{title}</h3>
            {description && <p className="mt-1.5 text-sm text-muted max-w-sm">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

export function Alert({
    tone = 'info',
    title,
    children,
}: {
    tone?: 'info' | 'success' | 'warning' | 'danger';
    title?: string;
    children: ReactNode;
}) {
    const tones = {
        info: 'bg-sky-50 border-sky-200 text-sky-900 dark:bg-sky-950/40 dark:border-sky-900 dark:text-sky-100',
        success:
            'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-900 dark:text-emerald-100',
        warning:
            'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/40 dark:border-amber-900 dark:text-amber-100',
        danger:
            'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-900 dark:text-rose-100',
    };

    return (
        <div className={cn('rounded-[var(--radius-field)] border px-4 py-3 text-sm', tones[tone])} role="alert">
            {title && <p className="font-bold mb-0.5">{title}</p>}
            <div>{children}</div>
        </div>
    );
}
