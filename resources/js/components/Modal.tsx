import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { X } from 'lucide-react';

interface Props {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    children: ReactNode;
    /** 'full' fills a tall panel (children own their layout); 'lg' is a compact, self-scrolling dialog. */
    size?: 'full' | 'lg';
}

export function Modal({ open, onClose, title, children, size = 'lg' }: Props) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    const panel = size === 'full' ? 'h-[88vh] max-w-6xl' : 'max-h-[88vh] max-w-lg';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative flex w-full ${panel} flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl`}>
                <div className="flex shrink-0 items-center justify-between border-b border-neutral-200 px-5 py-3.5">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-neutral-700">{title}</h2>
                    <button
                        onClick={onClose}
                        className="cursor-pointer text-neutral-500 transition-colors hover:text-neutral-900"
                        aria-label="Fermer"
                    >
                        <X size={18} />
                    </button>
                </div>
                <div className={size === 'full' ? 'min-h-0 flex-1' : 'overflow-y-auto p-5'}>{children}</div>
            </div>
        </div>
    );
}
