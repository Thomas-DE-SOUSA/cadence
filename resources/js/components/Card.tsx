import type { ReactNode } from 'react';

interface Props {
    title?: ReactNode;
    className?: string;
    children: ReactNode;
}

export function Card({ title, className, children }: Props) {
    return (
        <section className={`rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm shadow-neutral-200/60${className ? ` ${className}` : ''}`}>
            {title && (
                <h2 className="mb-4 text-[13px] font-semibold uppercase tracking-wide text-neutral-500">{title}</h2>
            )}
            {children}
        </section>
    );
}
