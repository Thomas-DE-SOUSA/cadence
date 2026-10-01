import type { ComponentType, ReactNode } from 'react';

type Tone = 'neutral' | 'brand' | 'violet' | 'emerald' | 'amber';

const TONE_BG: Record<Tone, string> = {
    neutral: 'bg-neutral-50 text-neutral-600',
    brand: 'bg-brand-50 text-neutral-700',
    violet: 'bg-violet-50/60 text-neutral-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber: 'bg-amber-50 text-amber-700',
};

const TONE_ICON: Record<Tone, string> = {
    neutral: 'text-neutral-400',
    brand: 'text-brand-500',
    violet: 'text-violet-500',
    emerald: 'text-emerald-500',
    amber: 'text-amber-500',
};

/**
 * A soft inline note — flat tinted background, no border. The design language's
 * callout (replaces bordered banners). Optional leading icon.
 */
export function Callout({
    icon: Icon,
    tone = 'neutral',
    className,
    children,
}: {
    icon?: ComponentType<{ size?: number; className?: string }>;
    tone?: Tone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`flex items-start gap-2.5 rounded-lg px-4 py-3 text-sm leading-relaxed ${TONE_BG[tone]}${className ? ` ${className}` : ''}`}>
            {Icon && <Icon size={17} className={`mt-0.5 shrink-0 ${TONE_ICON[tone]}`} />}
            <div className="min-w-0">{children}</div>
        </div>
    );
}
