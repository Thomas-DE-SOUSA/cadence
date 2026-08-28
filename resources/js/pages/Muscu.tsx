import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Dumbbell, Layers, Plus, TrendingUp } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface SessionSummary {
    id: string;
    date: string;
    title: string;
    exerciseCount: number;
    totalSets: number;
    volumeKg: number;
    durationSeconds: number | null;
}

interface Progression {
    exerciseId: string;
    name: string;
    bestE1rm: number;
    series: { date: string; e1rm: number; topWeight: number }[];
}

interface Props {
    sessions: SessionSummary[];
    progression: Progression[];
    catalogCount: number;
}

function fmtDate(iso: string): string {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
}

function fmtDuration(s: number | null): string | null {
    if (!s) return null;
    const m = Math.round(s / 60);
    return m >= 60 ? `${Math.floor(m / 60)}h${String(m % 60).padStart(2, '0')}` : `${m} min`;
}

/** Tiny e1RM sparkline. */
function Spark({ series }: { series: { e1rm: number }[] }) {
    if (series.length < 2) return null;
    const vals = series.map((p) => p.e1rm);
    const min = Math.min(...vals);
    const max = Math.max(...vals);
    const span = Math.max(max - min, 1);
    const W = 80;
    const H = 24;
    const path = series
        .map((p, i) => `${i === 0 ? 'M' : 'L'} ${((i / (series.length - 1)) * W).toFixed(1)} ${(H - ((p.e1rm - min) / span) * H).toFixed(1)}`)
        .join(' ');
    return (
        <svg width={W} height={H} className="overflow-visible">
            <path d={path} fill="none" stroke="#1c855a" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={W} cy={H - ((series[series.length - 1].e1rm - min) / span) * H} r={2.5} fill="#1c855a" />
        </svg>
    );
}

export default function Muscu({ sessions, progression, catalogCount }: Props) {
    return (
        <>
            <Head title="Muscu" />
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Muscu</h1>
                    <p className="mt-1 text-sm text-neutral-500">Tes séances, tes charges et ta progression force.</p>
                </div>
                <Link
                    href="/muscu/nouveau"
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
                >
                    <Plus size={16} /> Séance
                </Link>
            </div>

            {sessions.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-16 text-center">
                    <Dumbbell size={32} className="mb-3 text-neutral-400" />
                    <p className="max-w-sm text-sm text-neutral-500">
                        {catalogCount} exercices déjà dans la bibliothèque (barre, haltères, machines, poulies…). Lance ta première séance : tu suivras
                        tes poids, tes reps et ta progression force.
                    </p>
                    <Link
                        href="/muscu/nouveau"
                        className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
                    >
                        <Plus size={16} /> Nouvelle séance
                    </Link>
                </div>
            ) : (
                <div className="space-y-4">
                    {progression.length > 0 && (
                        <Card
                            title={
                                <span className="inline-flex items-center gap-1.5">
                                    <TrendingUp size={15} className="text-brand-600" /> Progression force (e1RM estimé)
                                </span>
                            }
                        >
                            <ul className="divide-y divide-neutral-100">
                                {progression.slice(0, 6).map((p) => (
                                    <li key={p.exerciseId} className="flex items-center justify-between gap-3 py-2.5">
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-neutral-700">{p.name}</span>
                                        <Spark series={p.series} />
                                        <span className="w-16 shrink-0 text-right text-sm font-bold tabular-nums text-neutral-900">{p.bestE1rm} kg</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    <Card title="Séances récentes">
                        <ul className="divide-y divide-neutral-100">
                            {sessions.map((s) => {
                                const dur = fmtDuration(s.durationSeconds);
                                return (
                                    <li key={s.id}>
                                        <Link href={`/muscu/${s.id}/modifier`} className="-mx-2 flex items-center gap-3 rounded-lg px-2 py-3 transition hover:bg-neutral-50">
                                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                                <Dumbbell size={16} />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-neutral-800">{s.title || 'Séance'}</p>
                                                <p className="text-xs text-neutral-400">{fmtDate(s.date)}</p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-3 text-xs text-neutral-500">
                                                <span className="inline-flex items-center gap-1">
                                                    <Layers size={12} /> {s.totalSets} séries
                                                </span>
                                                {s.volumeKg > 0 && <span className="tabular-nums">{s.volumeKg.toLocaleString('fr-FR')} kg</span>}
                                                {dur && <span className="tabular-nums">{dur}</span>}
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                </div>
            )}
        </>
    );
}

Muscu.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
