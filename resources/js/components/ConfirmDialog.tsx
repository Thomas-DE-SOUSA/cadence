import { useCallback, useState, type ReactNode } from 'react';
import { AlertTriangle } from 'lucide-react';

export interface ConfirmOptions {
    title: string;
    message?: string;
    confirmLabel?: string;
    /** danger = rose destructive action (default), brand = primary action. */
    tone?: 'danger' | 'brand';
}

/**
 * Promise-based confirmation, styled like the app's sheets — replaces the
 * native (ugly) window.confirm(). Usage:
 *   const { confirm, node } = useConfirm();
 *   if (await confirm({ title: '…', confirmLabel: 'Supprimer' })) { … }
 *   // …render {node} once in the tree.
 */
export function useConfirm(): { confirm: (opts: ConfirmOptions) => Promise<boolean>; node: ReactNode } {
    const [pending, setPending] = useState<{ opts: ConfirmOptions; resolve: (v: boolean) => void } | null>(null);

    const confirm = useCallback((opts: ConfirmOptions) => new Promise<boolean>((resolve) => setPending({ opts, resolve })), []);

    const settle = (v: boolean) =>
        setPending((p) => {
            p?.resolve(v);
            return null;
        });

    const brand = pending?.opts.tone === 'brand';
    const node = pending ? (
        <div className="fixed inset-0 z-[60] flex items-end justify-center bg-neutral-900/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={() => settle(false)}>
            <div className="w-full max-w-sm rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl" onClick={(e) => e.stopPropagation()}>
                <div className={`mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full ${brand ? 'bg-brand-100 text-brand-600' : 'bg-rose-100 text-rose-600'}`}>
                    <AlertTriangle size={22} />
                </div>
                <p className="text-center text-lg font-bold text-neutral-900">{pending.opts.title}</p>
                {pending.opts.message && <p className="mt-1.5 whitespace-pre-line text-center text-sm text-neutral-500">{pending.opts.message}</p>}
                <div className="mt-6 flex flex-col gap-2">
                    <button
                        type="button"
                        onClick={() => settle(true)}
                        className={`w-full rounded-xl py-3 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 ${brand ? 'bg-brand-600' : 'bg-rose-600'}`}
                    >
                        {pending.opts.confirmLabel ?? 'Confirmer'}
                    </button>
                    <button type="button" onClick={() => settle(false)} className="w-full rounded-xl py-3 text-sm font-semibold text-neutral-600 hover:bg-neutral-100">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    ) : null;

    return { confirm, node };
}
