export function GoogleAuthButton({ label, configured, href = '/auth/google' }: { label: string; configured: boolean; href?: string }) {
    const className = 'flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-line bg-white px-4 text-sm font-bold text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-50 dark:bg-[#26262f] dark:text-white dark:hover:bg-[#30303a]';

    if (!configured) {
        return (
            <button type="button" disabled className={`${className} cursor-not-allowed opacity-60`} title="Google OAuth belum dikonfigurasi">
                <GoogleIcon /> {label}
            </button>
        );
    }

    return <a href={href} className={className}><GoogleIcon /> {label}</a>;
}

function GoogleIcon() {
    return (
        <svg viewBox="0 0 24 24" className="size-5" aria-hidden="true">
            <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z" />
            <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.36l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z" />
            <path fill="#FBBC05" d="M6.39 13.93A6.02 6.02 0 0 1 6.08 12c0-.67.12-1.32.31-1.93V7.45H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.55l3.35-2.62Z" />
            <path fill="#EA4335" d="M12 5.94c1.47 0 2.79.51 3.83 1.5L18.7 4.56A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.96 5.45l3.35 2.62C7.18 7.7 9.39 5.94 12 5.94Z" />
        </svg>
    );
}
