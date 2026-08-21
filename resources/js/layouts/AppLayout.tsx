import { Link, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { Plus } from 'lucide-react';
import { Toaster, toast } from 'sonner';
import { navItems } from '@/lib/nav';

interface AthleteSummary {
    name: string;
    initial: string;
    raceName: string | null;
    raceDaysLeft: number | null;
}

function isActive(currentPath: string, href: string): boolean {
    return href === '/' ? currentPath === '/' : currentPath.startsWith(href);
}

export function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const path = page.url.split('?')[0];
    const flash = (page.props.flash as { status?: string } | undefined)?.status;
    const athlete = page.props.athlete as AthleteSummary | null;

    useEffect(() => {
        if (flash) {
            toast.success(flash);
        }
    }, [flash]);

    const raceChip =
        athlete?.raceDaysLeft !== null && athlete?.raceDaysLeft !== undefined && athlete.raceDaysLeft >= 0
            ? `J-${athlete.raceDaysLeft}`
            : null;

    return (
        <div className="min-h-screen text-neutral-900">
            {/* Top navigation bar */}
            <header className="fixed inset-x-0 top-0 z-30 border-b border-neutral-200/80 bg-white/85 shadow-[0_1px_2px_rgba(16,16,20,0.04),0_8px_24px_-12px_rgba(16,16,20,0.12)] backdrop-blur-lg">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 md:px-8">
                    {/* Brand */}
                    <Link href="/" className="flex shrink-0 items-center gap-2.5">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-black text-white shadow-md shadow-brand-500/30">
                            C
                        </span>
                        <span className="hidden leading-none sm:block">
                            <span className="block text-[15px] font-black tracking-tight text-neutral-900">Cadence</span>
                            <span className="mt-0.5 block text-[10px] font-medium uppercase tracking-wider text-brand-500">Sub-40</span>
                        </span>
                    </Link>

                    {/* Primary nav (desktop) */}
                    <nav className="ml-2 hidden flex-1 items-center gap-1 md:flex">
                        {navItems.map(({ href, label, icon: Icon }) => {
                            const active = isActive(path, href);
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    className={`group relative flex items-center gap-2 rounded-full px-3.5 py-2 text-sm transition-all ${
                                        active
                                            ? 'bg-brand-50 font-semibold text-brand-600 shadow-sm shadow-brand-500/10 ring-1 ring-brand-200/70'
                                            : 'font-medium text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900'
                                    }`}
                                >
                                    <Icon size={17} className={active ? 'text-brand-500' : 'text-neutral-400 group-hover:text-neutral-600'} />
                                    <span className="hidden lg:inline">{label}</span>
                                </Link>
                            );
                        })}
                    </nav>

                    {/* Right cluster */}
                    <div className="ml-auto flex items-center gap-2 md:ml-0">
                        {raceChip && (
                            <span className="hidden items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm lg:flex">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-500" />
                                {raceChip}
                                {athlete?.raceName && <span className="text-neutral-400">· {athlete.raceName}</span>}
                            </span>
                        )}
                        <Link
                            href="/activites/nouvelle"
                            className="flex items-center gap-1.5 rounded-full bg-gradient-to-br from-brand-500 to-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-md shadow-brand-500/25 transition-transform hover:-translate-y-0.5 hover:shadow-lg hover:shadow-brand-500/30"
                        >
                            <Plus size={17} />
                            <span className="hidden sm:inline">Activité</span>
                        </Link>
                        <Link
                            href="/profil"
                            className="flex items-center gap-2 rounded-full border border-neutral-200 bg-white py-1 pl-1 pr-1 shadow-sm transition-colors hover:border-brand-200 hover:bg-brand-50/40 lg:pr-3"
                            title={athlete?.name ?? 'Profil'}
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-neutral-700 to-neutral-900 text-xs font-bold text-white">
                                {athlete?.initial ?? '?'}
                            </span>
                            <span className="hidden max-w-[8rem] truncate text-sm font-semibold text-neutral-700 lg:inline">
                                {athlete?.name ?? 'Profil'}
                            </span>
                        </Link>
                    </div>
                </div>
            </header>

            {/* Mobile bottom tab bar */}
            <nav
                className="fixed inset-x-0 bottom-0 z-30 flex border-t border-neutral-200 bg-white/95 backdrop-blur md:hidden"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                {navItems.map(({ href, label, icon: Icon }) => {
                    const active = isActive(path, href);
                    return (
                        <Link
                            key={href}
                            href={href}
                            className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-medium transition-colors ${
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
            <main className="px-4 pb-24 pt-24 sm:px-6 md:px-8 md:pb-14 lg:px-10">
                <div className="mx-auto max-w-6xl">{children}</div>
            </main>

            <Toaster position="bottom-right" richColors closeButton theme="light" />
        </div>
    );
}
