import { useEffect } from 'react';
import { Check, Monitor, Moon, Sun, X } from 'lucide-react';
import { useTheme, type Theme } from '@/lib/theme';

/** Little UI mock shown inside each theme option (fixed colors, theme-accurate). */
function Preview({ variant }: { variant: 'light' | 'dark' | 'system' }) {
    if (variant === 'system') {
        return (
            <div className="relative h-16 overflow-hidden rounded-lg ring-1 ring-black/10">
                <div className="absolute inset-0 bg-[#f4f6f4]" style={{ clipPath: 'polygon(0 0, 58% 0, 42% 100%, 0 100%)' }} />
                <div className="absolute inset-0 bg-[#0e1014]" style={{ clipPath: 'polygon(58% 0, 100% 0, 100% 100%, 42% 100%)' }} />
                <div className="relative flex h-full items-center justify-between px-3">
                    <span className="h-2.5 w-2.5 rounded-full bg-[#1c855a]" />
                    <span className="h-2.5 w-2.5 rounded-full bg-[#3ea073]" />
                </div>
            </div>
        );
    }
    const dark = variant === 'dark';
    return (
        <div
            className={`h-16 rounded-lg p-2 ring-1 ${dark ? 'bg-[#0e1014] ring-white/10' : 'bg-[#f4f6f4] ring-black/5'}`}
        >
            <div className="mb-1.5 flex items-center gap-1">
                <span className={`h-2.5 w-2.5 rounded-sm ${dark ? 'bg-[#3ea073]' : 'bg-[#1c855a]'}`} />
                <span className={`h-1.5 w-6 rounded ${dark ? 'bg-[#2b313b]' : 'bg-white'}`} />
            </div>
            <div className={`h-2 w-full rounded ${dark ? 'bg-[#181b21]' : 'bg-white'}`} />
            <div className={`mt-1 h-2 w-2/3 rounded ${dark ? 'bg-[#3a414c]' : 'bg-neutral-300'}`} />
        </div>
    );
}

const OPTIONS: { key: Theme; label: string; icon: typeof Sun; variant: 'light' | 'dark' | 'system' }[] = [
    { key: 'light', label: 'Clair', icon: Sun, variant: 'light' },
    { key: 'dark', label: 'Sombre', icon: Moon, variant: 'dark' },
    { key: 'system', label: 'Système', icon: Monitor, variant: 'system' },
];

export function ThemePicker({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { theme, setTheme } = useTheme();

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/50 p-4 backdrop-blur-sm"
            onClick={onClose}
        >
            <div
                className="animate-pop w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-5 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h2 className="text-base font-bold text-neutral-900">Thème</h2>
                        <p className="mt-0.5 text-sm text-neutral-500">Choisis l'apparence de l'app.</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1 text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700"
                        aria-label="Fermer"
                    >
                        <X size={18} />
                    </button>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    {OPTIONS.map(({ key, label, icon: Icon, variant }) => {
                        const active = theme === key;
                        return (
                            <button
                                key={key}
                                onClick={() => setTheme(key)}
                                className={`group relative rounded-xl border p-2 text-left transition-all ${
                                    active
                                        ? 'border-brand-400 ring-2 ring-brand-200'
                                        : 'border-neutral-200 hover:border-neutral-300 hover:-translate-y-0.5'
                                }`}
                            >
                                {active && (
                                    <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white shadow">
                                        <Check size={12} strokeWidth={3} />
                                    </span>
                                )}
                                <Preview variant={variant} />
                                <p className="mt-2 flex items-center justify-center gap-1.5 text-sm font-semibold text-neutral-700">
                                    <Icon size={15} className={active ? 'text-brand-600' : 'text-neutral-400'} />
                                    {label}
                                </p>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
