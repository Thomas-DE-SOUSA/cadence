import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { navItems } from '@/lib/nav';

function isActive(currentPath: string, href: string): boolean {
    return href === '/' ? currentPath === '/' : currentPath.startsWith(href);
}

export function AppLayout({ children }: { children: ReactNode }) {
    const path = usePage().url.split('?')[0];

    return (
        <div className="min-h-screen bg-neutral-950 text-neutral-100">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 hidden w-60 flex-col border-r border-neutral-800 bg-neutral-900/40 p-4 md:flex">
                <div className="mb-8 px-2">
                    <div className="text-lg font-bold tracking-tight">Cadence</div>
                    <div className="text-xs text-neutral-500">Sub-40 · Odysséa 04/10/2026</div>
                </div>
                <nav className="flex flex-1 flex-col gap-1">
                    {navItems.map(({ href, label, icon: Icon }) => {
                        const active = isActive(path, href);
                        return (
                            <Link
                                key={href}
                                href={href}
                                className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors ${
                                    active
                                        ? 'bg-lime-400/10 font-medium text-lime-300'
                                        : 'text-neutral-400 hover:bg-neutral-800/60 hover:text-neutral-100'
                                }`}
                            >
                                <Icon size={18} />
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            </aside>

            {/* Mobile bottom tab bar */}
            <nav
                className="fixed inset-x-0 bottom-0 z-10 flex border-t border-neutral-800 bg-neutral-900/95 backdrop-blur md:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                {navItems.map(({ href, label, icon: Icon }) => {
                    const active = isActive(path, href);
                    return (
                        <Link
                            key={href}
                            href={href}
                            className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] ${
                                active ? 'text-lime-300' : 'text-neutral-500'
                            }`}
                        >
                            <Icon size={20} />
                            {label.split(' ')[0]}
                        </Link>
                    );
                })}
            </nav>

            {/* Content */}
            <main className="px-5 pb-24 pt-8 md:ml-60 md:pb-12">
                <div className="mx-auto max-w-3xl">{children}</div>
            </main>
        </div>
    );
}
