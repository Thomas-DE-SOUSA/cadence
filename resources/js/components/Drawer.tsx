import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { X } from 'lucide-react';

interface Props {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    children: ReactNode;
}

export function Drawer({ open, onClose, title, children }: Props) {
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

    return (
        <div className="fixed inset-0 z-50">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col border-l border-neutral-800 bg-neutral-950 shadow-2xl">
                <div className="flex items-center justify-between border-b border-neutral-800 px-5 py-4">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-neutral-300">{title}</h2>
                    <button
                        onClick={onClose}
                        className="cursor-pointer text-neutral-500 transition-colors hover:text-neutral-100"
                        aria-label="Fermer"
                    >
                        <X size={18} />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto px-5 py-5">{children}</div>
            </div>
        </div>
    );
}
