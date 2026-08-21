import { Link, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { Toaster, toast } from 'sonner';
import { navItems } from '@/lib/nav';

function isActive(currentPath: string, href: string): boolean {
    return href === '/' ? currentPath === '/' : currentPath.startsWith(href);
}

export function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const flash = (page.props.flash as { status?: string } | undefined)?.status;

    useEffect(() => {
        if (flash) {
            toast.success(flash);
        }
    }, [flash]);

    return (
        <div className="min-h-screen text-neutral-900">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 hidden w-60 flex-col border-r border-neutral-200 bg-white p-4 md:flex">
                <div className="mb-8 flex items-center gap-2 px-2">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-sm font-black text-white">
                        C
                    </span>
                    <div>
                        <div className="text-base font-bold leading-none tracking-tight text-neutral-900">Cadence</div>
                        <div className="mt-1 text-[11px] text-neutral-400">Sub-40 · Odysséa 04/10</div>
                    </div>
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
                                        ? 'bg-brand-50 font-semibold text-brand-600'
                                        : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900'
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
                className="fixed inset-x-0 bottom-0 z-10 flex border-t border-neutral-200 bg-white/95 backdrop-blur md:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                {navItems.map(({ href, label, icon: Icon }) => {
                    const active = isActive(path, href);
                    return (
                        <Link
                            key={href}
                            href={href}
                            className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] ${
                                active ? 'text-brand-600' : 'text-neutral-400'
                            }`}
                        >
                            <Icon size={20} />
                            {label.split(' ')[0]}
                        </Link>
                    );
                })}
            </nav>

            {/* Content */}
            <main className="px-5 pb-24 pt-8 md:ml-60 md:px-10 md:pb-12 lg:px-12">
                <div className="mx-auto max-w-6xl">{children}</div>
            </main>

            <Toaster position="bottom-right" richColors closeButton theme="light" />
        </div>
    );
}
