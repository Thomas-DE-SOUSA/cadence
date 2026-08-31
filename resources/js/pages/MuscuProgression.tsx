import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Activity, CalendarCheck, Dumbbell, Layers, SlidersHorizontal, Trophy, TrendingUp } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Weekly {
    label: string;
    sessions: number;
    volumeKg: number;
}
interface MuscleVolume {
    muscle: string;
    label: string;
    sets: number;
}
interface Record {
    name: string;
    e1rm: number;
    date: string;
    recent: boolean;
}
interface Progression {
    exerciseId: string;
    name: string;
    bestE1rm: number;
    series: { date: string; e1rm: number; topWeight: number }[];
}
interface Props {
    goal: string;
    hasProfile: boolean;
    weekly: Weekly[];
    muscleVolume: MuscleVolume[];
    records: Record[];
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

function Attendance({ weekly }: { weekly: Weekly[] }) {
    const max = Math.max(...weekly.map((w) => w.sessions), 1);
    const thisWeek = weekly[weekly.length - 1];
    return (
        <Card
            title={
                <span className="inline-flex items-center gap-1.5">
                    <CalendarCheck size={15} className="text-brand-600" /> Assiduité — 8 dernières semaines
                </span>
            }
        >
            <div className="flex items-end justify-between gap-1.5" style={{ height: 96 }}>
                {weekly.map((w, i) => (
                    <div key={i} className="flex flex-1 flex-col items-center justify-end gap-1">
                        <div
                            className={`w-full rounded-t-md ${w.sessions > 0 ? 'bg-brand-500' : 'bg-neutral-100'}`}
                            style={{ height: `${Math.max((w.sessions / max) * 72, w.sessions > 0 ? 6 : 2)}px` }}
                            title={`${w.sessions} séance(s) · ${w.volumeKg.toLocaleString('fr-FR')} kg`}
                        />
                        <span className="text-[9px] text-neutral-400">{w.label}</span>
                    </div>
                ))}
            </div>
            {thisWeek && (
                <p className="mt-3 rounded-lg bg-neutral-50 px-3 py-2 text-sm text-neutral-600">
                    Cette semaine : <span className="font-semibold text-neutral-900">{thisWeek.sessions} séance{thisWeek.sessions > 1 ? 's' : ''}</span>
                    {thisWeek.volumeKg > 0 && <> · {thisWeek.volumeKg.toLocaleString('fr-FR')} kg soulevés</>}
                </p>
            )}
        </Card>
    );
}

function MuscleBalance({ muscleVolume }: { muscleVolume: MuscleVolume[] }) {
    const max = Math.max(...muscleVolume.map((m) => m.sets), 1);
    return (
        <Card
            title={
                <span className="inline-flex items-center gap-1.5">
                    <Layers size={15} className="text-brand-600" /> Volume par muscle — 4 semaines (séries)
                </span>
            }
        >
            {muscleVolume.length === 0 ? (
                <p className="text-sm text-neutral-400">Pas encore de séance faite avec des séries de travail.</p>
            ) : (
                <ul className="space-y-2">
                    {muscleVolume.map((m) => (
                        <li key={m.muscle} className="flex items-center gap-3">
                            <span className="w-28 shrink-0 truncate text-sm text-neutral-600">{m.label}</span>
                            <div className="h-3 flex-1 overflow-hidden rounded-full bg-neutral-100">
                                <div className="h-full rounded-full bg-brand-500" style={{ width: `${(m.sets / max) * 100}%` }} />
                            </div>
                            <span className="w-8 shrink-0 text-right text-sm font-bold tabular-nums text-neutral-900">{m.sets}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

function Records({ records }: { records: Record[] }) {
    return (
        <Card
            title={
                <span className="inline-flex items-center gap-1.5">
                    <Trophy size={15} className="text-brand-600" /> Records (1RM estimé)
                </span>
            }
        >
            <ul className="divide-y divide-neutral-100">
                {records.slice(0, 8).map((r) => (
                    <li key={r.name} className="flex items-center justify-between gap-3 py-2.5">
                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-neutral-700">{r.name}</span>
                        {r.recent && <span className="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-600">récent 🎉</span>}
                        <span className="w-16 shrink-0 text-right text-sm font-bold tabular-nums text-neutral-900">{r.e1rm} kg</span>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function E1rmList({ progression }: { progression: Progression[] }) {
    return (
        <Card
            title={
                <span className="inline-flex items-center gap-1.5">
                    <TrendingUp size={15} className="text-brand-600" /> Force par exercice (e1RM)
                </span>
            }
        >
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
    );
}

export default function MuscuProgression({ goal, hasProfile, weekly, muscleVolume, records, progression }: Props) {
    const empty = records.length === 0 && muscleVolume.length === 0 && weekly.every((w) => w.sessions === 0);

    // The profile's goal reorders the sections — what matters most goes first.
    const order: Record<string, ('attendance' | 'muscle' | 'records' | 'e1rm')[]> = {
        GENERAL: ['attendance', 'muscle', 'records', 'e1rm'],
        HYPERTROPHY: ['muscle', 'attendance', 'e1rm', 'records'],
        STRENGTH: ['e1rm', 'records', 'attendance', 'muscle'],
        ENDURANCE: ['muscle', 'attendance', 'e1rm', 'records'],
    };
    const sections = order[goal] ?? order.GENERAL;

    const render = (key: string) => {
        if (key === 'attendance') return <Attendance key={key} weekly={weekly} />;
        if (key === 'muscle') return <MuscleBalance key={key} muscleVolume={muscleVolume} />;
        if (key === 'records') return records.length > 0 ? <Records key={key} records={records} /> : null;
        if (key === 'e1rm') return progression.length > 0 ? <E1rmList key={key} progression={progression} /> : null;
        return null;
    };

    return (
        <>
            <Head title="Progression" />
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Progression</h1>
                    <p className="mt-1 text-sm text-neutral-500">Adaptée à ton profil{!hasProfile && ' (à configurer)'}.</p>
                </div>
                <Link
                    href="/muscu/profil"
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-semibold transition ${
                        hasProfile ? 'border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-50' : 'border-brand-300 bg-brand-50 text-brand-700'
                    }`}
                >
                    <SlidersHorizontal size={15} /> Profil
                </Link>
            </div>

            {empty ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-16 text-center">
                    <Dumbbell size={32} className="mb-3 text-neutral-400" />
                    <p className="max-w-sm text-sm text-neutral-500">
                        Fais quelques séances (marquées « fait ») : ton assiduité, ton volume par muscle et ta progression force apparaîtront ici.
                    </p>
                    {!hasProfile && (
                        <Link href="/muscu/profil" className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white">
                            <Activity size={15} /> Configurer mon profil
                        </Link>
                    )}
                </div>
            ) : (
                <div className="space-y-4">{sections.map(render)}</div>
            )}
        </>
    );
}

MuscuProgression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
