import type { ReactNode } from 'react';

export function GuestLayout({ children }: { children: ReactNode }) {
    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-neutral-50 via-white to-brand-50/60 px-4 py-10">
            <div className="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand-200/40 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-brand-100/50 blur-3xl" />

            <div className="relative w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 shadow-lg shadow-brand-500/30">
                        <svg viewBox="134 102 244 306" className="h-7 w-auto" aria-hidden="true">
                            <g fill="#ffffff">
                                <rect x="150" y="300" width="46" height="90" rx="23" />
                                <rect x="233" y="230" width="46" height="160" rx="23" />
                                <rect x="316" y="150" width="46" height="240" rx="23" />
                            </g>
                            <g fill="#8ff0bd">
                                <circle cx="173" cy="285" r="17" />
                                <circle cx="256" cy="215" r="17" />
                                <circle cx="339" cy="135" r="17" />
                            </g>
                        </svg>
                    </span>
                    <span className="text-2xl font-black tracking-tight text-neutral-900">Cadence</span>
                </div>

                <div className="rounded-3xl border border-neutral-200 bg-white/90 p-6 shadow-xl shadow-neutral-300/40 backdrop-blur sm:p-7">{children}</div>
            </div>
        </div>
    );
}
