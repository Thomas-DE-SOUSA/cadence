import type { CSSProperties, ReactNode } from 'react';

/**
 * A row of headline figures framed by a top/bottom hairline (no box). Put `Stat`
 * children inside; pass grid columns via `className` (e.g. "grid-cols-4").
 */
export function StatRow({ className, style, children }: { className?: string; style?: CSSProperties; children: ReactNode }) {
    return (
        <div className={`grid border-y border-neutral-100${className ? ` ${className}` : ''}`} style={style}>
            {children}
        </div>
    );
}

/** One figure: a bold value over a small muted label (which may carry an icon / help tip). */
export function Stat({ value, label }: { value: ReactNode; label: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-1 px-2 py-4 text-center">
            <p className="text-xl font-extrabold leading-none tabular-nums text-neutral-900 sm:text-2xl">{value}</p>
            <p className="flex items-center gap-1 text-[11px] font-medium text-neutral-400">{label}</p>
        </div>
    );
}
