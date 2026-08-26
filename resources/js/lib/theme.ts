import { useCallback, useEffect, useState } from 'react';

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'theme';

function prefersDark(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function storedTheme(): Theme {
    if (typeof window === 'undefined') return 'system';
    const v = window.localStorage.getItem(STORAGE_KEY);
    return v === 'light' || v === 'dark' || v === 'system' ? v : 'system';
}

/** Applies the resolved theme to <html> (adds/removes the `dark` class). */
export function applyTheme(theme: Theme): void {
    const isDark = theme === 'dark' || (theme === 'system' && prefersDark());
    document.documentElement.classList.toggle('dark', isDark);
}

/** React hook: current preference + setter, kept in sync with the OS and storage. */
export function useTheme(): { theme: Theme; setTheme: (t: Theme) => void } {
    const [theme, setThemeState] = useState<Theme>(() => storedTheme());

    const setTheme = useCallback((t: Theme) => {
        setThemeState(t);
        try {
            window.localStorage.setItem(STORAGE_KEY, t);
        } catch {
            /* storage may be unavailable */
        }
        applyTheme(t);
    }, []);

    // Follow OS changes while on "system".
    useEffect(() => {
        if (theme !== 'system') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => applyTheme('system');
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, [theme]);

    return { theme, setTheme };
}
