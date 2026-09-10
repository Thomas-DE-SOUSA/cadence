import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Activity, CalendarCheck, Dumbbell, Layers, SlidersHorizontal, TrendingUp } from 'lucide-react';
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
interface ProgPoint {
    date: string;
    e1rm: number;
    topWeight: number;
    topReps: number;
    position: number;
    totalExercises: number;
}
interface ExerciseProg {
    exerciseId: string;
    name: string;
    bestE1rm: number;
    series: ProgPoint[];
}
interface Props {
    goal: string;
    hasProfile: boolean;
    weekly: Weekly[];
    muscleVolume: MuscleVolume[];
    progression: ExerciseProg[];
}

function shortDate(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}
const fmtKg = (n: number) => (Number.isInteger(n) ? String(n) : n.toFixed(1).replace('.', ','));

/** The position the exercise most often sat at (ties → earliest position). */
function modePosition(series: ProgPoint[]): number {
    const counts: Record<number, number> = {};
    for (const p of series) counts[p.position] = (counts[p.position] ?? 0) + 1;
    let best = series[0]?.position ?? 1;
    let bestN = 0;
    for (const key of Object.keys(counts)) {
        const pos = Number(key);
        const n = counts[pos];
        if (n > bestN || (n === bestN && pos < best)) {
            best = pos;
            bestN = n;
        }
    }
    return best;
}

/** e1RM sparkline. Distortion is negligible at mobile widths (viewBox ≈ container). */
function Spark({ points }: { points: ProgPoint[] }) {
    const W = 300;
    const H = 60;
    const pad = 6;
    if (points.length === 0) return null;
    const vals = points.map((p) => p.e1rm);
    const min = Math.min(...vals);
    const max = Math.max(...vals);
    const span = Math.max(max - min, 1);
    const n = points.length;
    const x = (i: number) => (n === 1 ? W / 2 : pad + (i / (n - 1)) * (W - 2 * pad));
    const y = (v: number) => H - pad - ((v - min) / span) * (H - 2 * pad);
    const line = points.map((p, i) => `${x(i).toFixed(1)},${y(p.e1rm).toFixed(1)}`).join(' ');
    const area = `${pad},${H - pad} ${line} ${x(n - 1).toFixed(1)},${H - pad}`;
    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" style={{ height: 60 }} preserveAspectRatio="none" aria-hidden>
            {n > 1 && <polygon points={area} fill="rgb(242 103 34 / 0.10)" />}
            {n > 1 && <polyline points={line} fill="none" stroke="rgb(242 103 34)" strokeWidth={2} vectorEffect="non-scaling-stroke" />}
            {points.map((p, i) => (
                <circle key={i} cx={x(i)} cy={y(p.e1rm)} r={i === n - 1 ? 3 : 2} fill="rgb(242 103 34)" />
            ))}
        </svg>
    );
}

function ExerciseCard({ ex, samePos }: { ex: ExerciseProg; samePos: boolean }) {
    const mode = modePosition(ex.series);
    const filtered = ex.series.filter((p) => p.position === mode);
    const pts = samePos && filtered.length > 0 ? filtered : ex.series;
    const cur = pts[pts.length - 1];
    const first = pts[0];
    const delta = cur && first ? cur.e1rm - first.e1rm : 0;
    const recent = [...pts].slice(-5).reverse();

    return (
        <div className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/60">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate font-bold text-neutral-900">{ex.name}</p>
                    <p className="text-xs text-neutral-400">e1RM estimé · position habituelle&nbsp;{mode}ᵉ</p>
                </div>
                <div className="shrink-0 text-right">
                    <p className="text-lg font-black leading-none text-neutral-900">
                        {cur?.e1rm ?? ex.bestE1rm}
                        <span className="text-xs font-semibold text-neutral-400"> kg</span>
                    </p>
                    {delta !== 0 && (
                        <p className={`mt-0.5 text-xs font-semibold ${delta > 0 ? 'text-brand-600' : 'text-neutral-400'}`}>
                            {delta > 0 ? '+' : ''}
                            {delta} kg
                        </p>
                    )}
                </div>
            </div>

            <div className="mt-2">
                <Spark points={pts} />
            </div>

            <ul className="mt-2 space-y-1">
                {recent.map((p, i) => (
                    <li key={i} className="flex items-center gap-2 text-xs">
                        <span className="w-12 shrink-0 text-neutral-400">{shortDate(p.date)}</span>
                        <span className="flex-1 font-medium text-neutral-700">
                            {fmtKg(p.topWeight)} kg × {p.topReps}
                        </span>
                        <span
                            className={`shrink-0 rounded px-1.5 py-0.5 font-semibold ${
                                p.position === mode ? 'bg-neutral-100 text-neutral-500' : 'bg-amber-100 text-amber-700'
                            }`}
                            title={p.position === mode ? 'Position habituelle' : 'Position différente → comparaison à nuancer (fatigue)'}
                        >
                            {p.position}ᵉ/{p.totalExercises}
                        </span>
                        <span className="w-10 shrink-0 text-right font-bold tabular-nums text-neutral-900">{p.e1rm}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ByExercise({ progression }: { progression: ExerciseProg[] }) {
    const [samePos, setSamePos] = useState(false);
    if (progression.length === 0) return null;

    return (
        <section>
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 className="inline-flex items-center gap-1.5 text-[13px] font-semibold uppercase tracking-wide text-neutral-500">
                    <TrendingUp size={15} className="text-brand-600" /> Progression par exercice
                </h2>
                <button
                    onClick={() => setSamePos((v) => !v)}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors ${
                        samePos ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-500'
                    }`}
                >
                    À position égale {samePos ? '✓' : ''}
                </button>
            </div>
            <p className="mb-3 text-xs text-neutral-400">
                L'e1RM combine poids <span className="font-semibold">et</span> reps sur un seul nombre comparable. « À position égale » ne garde que les séances où
                l'exo était à sa position habituelle (fatigue comparable) ; un badge <span className="font-semibold text-amber-700">orange</span> signale une position
                différente.
            </p>
            <div className="space-y-3">
                {progression.map((ex) => (
                    <ExerciseCard key={ex.exerciseId} ex={ex} samePos={samePos} />
                ))}
            </div>
        </section>
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

export default function MuscuProgression({ goal, hasProfile, weekly, muscleVolume, progression }: Props) {
    const empty = progression.length === 0 && muscleVolume.length === 0 && weekly.every((w) => w.sessions === 0);

    // The profile's goal decides which of the attendance/muscle blocks leads.
    const muscleFirst = goal === 'HYPERTROPHY' || goal === 'ENDURANCE';
    const secondary = muscleFirst ? ['muscle', 'attendance'] : ['attendance', 'muscle'];

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
                    <p className="mt-1 text-sm text-neutral-500">
                        Tes charges par exercice, ta régularité et l'équilibre de ton volume{!hasProfile && ' (profil à configurer)'}.
                    </p>
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
                        Fais quelques séances (marquées « fait ») : tes charges par exercice, ton assiduité et ton volume par muscle apparaîtront ici.
                    </p>
                    {!hasProfile && (
                        <Link href="/muscu/profil" className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white">
                            <Activity size={15} /> Configurer mon profil
                        </Link>
                    )}
                </div>
            ) : (
                <div className="space-y-4">
                    <ByExercise progression={progression} />
                    {secondary.map(render)}
                </div>
            )}
        </>
    );
}

MuscuProgression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
