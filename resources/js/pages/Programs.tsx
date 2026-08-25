import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ChevronRight, Plus, Target } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { formatDate } from '@/features/activity/domain/format';

interface ProgramItem {
    id: string;
    name: string;
    targetRaceName: string;
    targetRaceDate: string | null;
    priority: string;
    status: string;
    objectivesCount: number;
    achievedCount: number;
    assignedCount: number;
}

interface Props {
    programs: ProgramItem[];
}

const statusLabel: Record<string, string> = {
    PLANNED: 'Planifié',
    ACTIVE: 'En cours',
    COMPLETED: 'Terminé',
    ABANDONED: 'Abandonné',
};

export default function Programs({ programs }: Props) {
    return (
        <>
            <Head title="Programme" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold tracking-tight">Programmes</h1>
                <Link
                    href="/programme/nouveau"
                    className="flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-brand-600"
                >
                    <Plus size={16} /> Nouveau
                </Link>
            </div>

            {programs.length === 0 ? (
                <div className="rounded-xl border border-dashed border-neutral-200 p-10 text-center text-neutral-500">
                    Aucun programme. Crée ton plan Odysséa (ou un autre objectif).
                </div>
            ) : (
                <ul className="space-y-2">
                    {programs.map((p) => (
                        <li key={p.id}>
                            <Link
                                href={`/programme/${p.id}`}
                                className="group flex items-center justify-between rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-sm shadow-neutral-200/50 transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md hover:shadow-neutral-200/60"
                            >
                                <div>
                                    <div className="flex items-center gap-2 font-medium text-neutral-900">
                                        {p.name}
                                        <span className="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500">
                                            {statusLabel[p.status] ?? p.status}
                                        </span>
                                    </div>
                                    <div className="text-xs text-neutral-500">
                                        {p.targetRaceName || 'Sans course'}
                                        {p.targetRaceDate ? ` · ${formatDate(p.targetRaceDate)}` : ''} · {p.assignedCount} sortie(s)
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 text-sm">
                                    <span className="flex items-center gap-1.5">
                                        <Target size={15} className="text-brand-600" />
                                        <span className="tabular-nums text-neutral-800">
                                            {p.achievedCount}/{p.objectivesCount}
                                        </span>
                                    </span>
                                    <ChevronRight size={18} className="shrink-0 text-neutral-300 transition-colors group-hover:text-brand-500" />
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

Programs.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
