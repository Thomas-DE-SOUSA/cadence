import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Activity, CalendarCheck, Dumbbell, Layers, SlidersHorizontal } from 'lucide-react';
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
interface Props {
    goal: string;
    hasProfile: boolean;
    weekly: Weekly[];
    muscleVolume: MuscleVolume[];
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

export default function MuscuProgression({ goal, hasProfile, weekly, muscleVolume }: Props) {
    const empty = muscleVolume.length === 0 && weekly.every((w) => w.sessions === 0);

    // The profile's goal decides which of the two blocks leads.
    const muscleFirst = goal === 'HYPERTROPHY' || goal === 'ENDURANCE';
    const sections = muscleFirst ? ['muscle', 'attendance'] : ['attendance', 'muscle'];

    const render = (key: string) => {
        if (key === 'attendance') return <Attendance key={key} weekly={weekly} />;
        if (key === 'muscle') return <MuscleBalance key={key} muscleVolume={muscleVolume} />;
        return null;
    };

    return (
        <>
            <Head title="Progression" />
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Progression</h1>
                    <p className="mt-1 text-sm text-neutral-500">Ta régularité et l'équilibre de ton volume{!hasProfile && ' (profil à configurer)'}.</p>
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
                        Fais quelques séances (marquées « fait ») : ton assiduité et ton volume par muscle apparaîtront ici.
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
