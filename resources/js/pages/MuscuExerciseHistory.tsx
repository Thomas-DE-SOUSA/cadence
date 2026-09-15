import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft, Dumbbell, Link2, TrendingUp } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';

interface HistorySet {
    weightKg: number | null;
    reps: number | null;
    rpe: number | null;
    durationSeconds: number | null;
    isWarmup: boolean;
    e1rm: number;
}
interface HistoryEntry {
    date: string;
    position: number;
    totalExercises: number;
    supersetGroup: number | null;
    perSide: boolean;
    bestE1rm: number;
    sets: HistorySet[];
}
interface Props {
    exerciseId: string;
    name: string;
    entries: HistoryEntry[];
}

const supersetLabel = (g: number) => String.fromCharCode(64 + g);

function ordinal(n: number): string {
    return n === 1 ? '1ᵉʳ' : `${n}ᵉ`;
}

function formatDate(date: string): string {
    return new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

function setLabel(s: HistorySet): string {
    if (s.weightKg != null && s.reps != null) return `${s.weightKg} kg × ${s.reps}`;
    if (s.reps != null) return `${s.reps} reps`;
    if (s.durationSeconds != null) return `${s.durationSeconds}s`;
    return '—';
}

export default function MuscuExerciseHistory({ name, entries }: Props) {
    // Best e1RM across all sessions — the reference for the progression tint.
    const bestOverall = entries.reduce((m, e) => Math.max(m, e.bestE1rm), 0);

    return (
        <>
            <Head title={name || 'Historique'} />

            <div className="mx-auto max-w-2xl">
                <div className="mb-4 flex items-center gap-2">
                    <button onClick={() => window.history.back()} title="Retour" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                        <ArrowLeft size={18} />
                    </button>
                    <div className="min-w-0">
                        <h1 className="truncate text-lg font-bold text-neutral-900">{name}</h1>
                        <p className="text-xs text-neutral-500">
                            {entries.length} séance{entries.length > 1 ? 's' : ''}
                            {bestOverall > 0 && (
                                <>
                                    {' · '}
                                    <span className="font-semibold text-brand-600">record e1RM {bestOverall} kg</span>
                                </>
                            )}
                        </p>
                    </div>
                </div>

                {entries.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-neutral-300 bg-white p-8 text-center">
                        <Dumbbell size={28} className="mx-auto mb-2 text-neutral-300" />
                        <p className="text-sm font-medium text-neutral-600">Aucune séance terminée avec cet exercice.</p>
                        <p className="mt-1 text-xs text-neutral-400">L'historique apparaîtra ici une fois une séance validée.</p>
                    </div>
                ) : (
                    <div className="space-y-3 pb-10">
                        {entries.map((entry, i) => {
                            const isRecord = entry.bestE1rm > 0 && entry.bestE1rm === bestOverall;
                            return (
                                <div key={i} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/60">
                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-semibold capitalize text-neutral-800">{formatDate(entry.date)}</p>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-500">
                                                {ordinal(entry.position)} / {entry.totalExercises}
                                            </span>
                                            {entry.supersetGroup != null && (
                                                <span className="inline-flex items-center gap-0.5 rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-violet-700">
                                                    <Link2 size={11} /> {supersetLabel(entry.supersetGroup)}
                                                </span>
                                            )}
                                            {entry.bestE1rm > 0 && (
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${
                                                        isRecord ? 'bg-brand-100 text-brand-700' : 'bg-neutral-100 text-neutral-500'
                                                    }`}
                                                    title="Meilleur 1RM estimé de la séance"
                                                >
                                                    <TrendingUp size={11} /> {entry.bestE1rm} kg
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        {entry.sets.map((s, j) => (
                                            <div
                                                key={j}
                                                className={`flex items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-sm ${
                                                    s.isWarmup ? 'text-neutral-400' : 'bg-neutral-50 text-neutral-800'
                                                }`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span className="w-4 shrink-0 text-right text-xs font-medium text-neutral-400">{j + 1}</span>
                                                    <span className={s.isWarmup ? '' : 'font-semibold'}>{setLabel(s)}</span>
                                                    {entry.perSide && !s.isWarmup && <span className="text-[10px] text-neutral-400">/ côté</span>}
                                                    {s.isWarmup && <span className="text-[10px] uppercase tracking-wide">échauff.</span>}
                                                </span>
                                                <span className="flex items-center gap-2 text-xs text-neutral-400">
                                                    {s.rpe != null && <span>RPE {s.rpe}</span>}
                                                    {!s.isWarmup && s.e1rm > 0 && <span className="tabular-nums">e1RM {s.e1rm}</span>}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

MuscuExerciseHistory.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
