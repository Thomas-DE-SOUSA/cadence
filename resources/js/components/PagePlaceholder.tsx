import type { LucideIcon } from 'lucide-react';

interface Props {
    title: string;
    description: string;
    icon: LucideIcon;
}

export function PagePlaceholder({ title, description, icon: Icon }: Props) {
    return (
        <div>
            <h1 className="mb-6 text-xl font-bold tracking-tight">{title}</h1>
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-800 px-6 py-16 text-center">
                <Icon size={32} className="mb-3 text-neutral-600" />
                <p className="max-w-sm text-sm text-neutral-500">{description}</p>
                <span className="mt-4 rounded-full bg-neutral-800/60 px-3 py-1 text-xs text-neutral-400">
                    Bientôt disponible
                </span>
            </div>
        </div>
    );
}
