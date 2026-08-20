import type { ReactNode } from 'react';

interface Props {
    title?: string;
    children: ReactNode;
}

export function Card({ title, children }: Props) {
    return (
        <section className="rounded-xl border border-neutral-800 bg-neutral-900/40 p-6">
            {title && (
                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400">{title}</h2>
            )}
            {children}
        </section>
    );
}
