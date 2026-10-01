import type { ComponentType, CSSProperties, ReactNode } from 'react';

/**
 * A flat content section — the running/strength design language's replacement for
 * the old bordered/shadowed `Card`. A section is just a title (bold, with an
 * optional brand-tinted icon) over its content; separation between sections comes
 * from spacing/borders, not boxes. Pass `action` for a right-aligned control
 * (e.g. a selector) next to the title.
 */
export function Section({
    title,
    icon: Icon,
    action,
    className,
    style,
    children,
}: {
    title?: ReactNode;
    icon?: ComponentType<{ size?: number; className?: string }>;
    action?: ReactNode;
    className?: string;
    style?: CSSProperties;
    children: ReactNode;
}) {
    return (
        <section className={className} style={style}>
            {(title || action) && (
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    {title && (
                        <h2 className="inline-flex items-center gap-1.5 text-sm font-bold text-neutral-800">
                            {Icon && <Icon size={15} className="text-brand-600" />}
                            {title}
                        </h2>
                    )}
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}
