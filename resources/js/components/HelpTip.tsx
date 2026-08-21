import { useState } from 'react';
import { Info } from 'lucide-react';

interface Props {
    /** Plain-language explanation shown in the bubble. */
    text: string;
    /** Accessible label for the term being explained. */
    label?: string;
    /** Which side the bubble opens on. Defaults to 'top'. */
    side?: 'top' | 'bottom';
    /** Icon size in px. Defaults to 14. */
    size?: number;
}

/**
 * A small "?" info icon that reveals a plain-language explanation on hover
 * (desktop) or tap (mobile). Used to demystify jargon across the app.
 */
export function HelpTip({ text, label, side = 'top', size = 14 }: Props) {
    const [open, setOpen] = useState(false);

    const bubblePosition = side === 'top' ? 'bottom-full mb-2' : 'top-full mt-2';
    const arrow =
        side === 'top'
            ? 'top-full border-t-neutral-900 border-b-transparent'
            : 'bottom-full border-b-neutral-900 border-t-transparent';

    return (
        <span className="relative inline-flex align-middle">
            <button
                type="button"
                aria-label={label ? `Aide : ${label}` : 'Aide'}
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
                onBlur={() => setOpen(false)}
                className="inline-flex items-center justify-center rounded-full text-neutral-300 transition-colors hover:text-brand-500 focus:outline-none focus-visible:text-brand-500"
            >
                <Info size={size} />
            </button>
            {open && (
                <span
                    role="tooltip"
                    className={`absolute left-1/2 z-40 w-56 -translate-x-1/2 ${bubblePosition} rounded-lg bg-neutral-900 px-3 py-2 text-left text-xs font-normal normal-case leading-relaxed tracking-normal text-white shadow-lg shadow-neutral-900/20`}
                >
                    {text}
                    <span className={`absolute left-1/2 -translate-x-1/2 border-4 border-l-transparent border-r-transparent ${arrow}`} />
                </span>
            )}
        </span>
    );
}
