import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TrendingUp } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Progression {
    exerciseId: string;
    name: string;
    bestE1rm: number;
    series: { date: string; e1rm: number; topWeight: number }[];
}
interface Props {
    progression: Progression[];
}

function Spark({ series }: { series: { e1rm: number }[] }) {
    if (series.length < 2) return null;
    const vals = series.map((p) => p.e1rm);
    const min = Math.min(...vals);
    const max = Math.max(...vals);
    const span = Math.max(max - min, 1);
    const W = 96;
    const H = 28;
    const path = series.map((p, i) => `${i === 0 ? 'M' : 'L'} ${((i / (series.length - 1)) * W).toFixed(1)} ${(H - ((p.e1rm - min) / span) * H).toFixed(1)}`).join(' ');
    return (
        <svg width={W} height={H} className="overflow-visible">
            <path d={path} fill="none" stroke="#f26722" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={W} cy={H - ((series[series.length - 1].e1rm - min) / span) * H} r={2.5} fill="#f26722" />
        </svg>
    );
}

export default function MuscuProgression({ progression }: Props) {
    return (
        <>
            <Head title="Progression force" />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Progression</h1>
                <p className="mt-1 text-sm text-neutral-500">Ton 1RM estimé (e1RM) par exercice, sur tes séances faites.</p>
            </div>

            {progression.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-16 text-center">
                    <TrendingUp size={32} className="mb-3 text-neutral-400" />
                    <p className="max-w-sm text-sm text-neutral-500">Fais quelques séances avec des charges : ta progression force apparaîtra ici, exercice par exercice.</p>
                </div>
            ) : (
                <Card title="e1RM estimé par exercice">
                    <ul className="divide-y divide-neutral-100">
                        {progression.map((p) => (
                            <li key={p.exerciseId} className="flex items-center justify-between gap-3 py-3">
                                <span className="min-w-0 flex-1 truncate text-sm font-medium text-neutral-700">{p.name}</span>
                                <Spark series={p.series} />
                                <span className="w-16 shrink-0 text-right text-sm font-bold tabular-nums text-neutral-900">{p.bestE1rm} kg</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </>
    );
}

MuscuProgression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
