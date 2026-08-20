import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

export function ThemeToggle({ className }: { className?: string }) {
    const [dark, setDark] = useState(false);

    useEffect(() => {
        setDark(document.documentElement.classList.contains('dark'));
    }, []);

    const toggle = () => {
        const next = !dark;
        setDark(next);
        document.documentElement.classList.toggle('dark', next);
        try {
            localStorage.setItem('jy-theme', next ? 'dark' : 'light');
        } catch {
            // Private mode: the choice simply does not persist.
        }
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'}
            className={cn(
                'grid size-10 place-items-center rounded-[var(--radius-field)] text-muted transition-colors hover:bg-surface-2 hover:text-fg',
                className,
            )}
        >
            {dark ? <Moon className="size-5" /> : <Sun className="size-5" />}
        </button>
    );
}
