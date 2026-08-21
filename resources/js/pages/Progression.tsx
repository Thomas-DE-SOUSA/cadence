import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { Activity, Flag, Gauge, Sparkles, Target, TrendingUp, Trophy } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { PagePlaceholder } from '@/components/PagePlaceholder';
import { useCountUp } from '@/lib/useCountUp';
import { formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface RecordRow {
    label: string;
    distanceMeters: number;
    durationSeconds: number;
    paceSecondsPerKm: number;
    activityId: string;
    occurredAt: string;
}

interface SeriesPoint {
    date: string;
    seconds: number;
    pace: number;
}

interface Series {
    distanceMeters: number;
    label: string;
    targetSeconds: number | null;
    points: SeriesPoint[];
}

interface Goal {
    label: string;
    raceName: string | null;
    raceDate: string | null;
    daysLeft: number | null;
    weeksLeft: number | null;
    distanceMeters: number;
    distanceLabel: string;
    targetSeconds: number;
    currentSeconds: number | null;
    currentActivityId: string | null;
    achieved: boolean;
    gapSeconds: number | null;
    progressPct: number;
}

interface Projection {
    fromLabel: string;
    fromDistanceMeters: number;
    toLabel: string;
    toDistanceMeters: number;
    predictedSeconds: number;
    beatsTarget: boolean;
}

interface Props {
    goal: Goal | null;
    records: RecordRow[];
    series: Series[];
    focusDistance: number;
    projection: Projection | null;
    vdot: number | null;
    stats: { runs: number; totalDistanceMeters: number };
}

function shortDate(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

function GoalDigits({ pct }: { pct: number }) {
    return <>{Math.round(useCountUp(pct))}</>;
}

/** Compact progression curve: best effort per run over time, with the goal line. */
function ProgressionChart({ series }: { series: Series }) {
    const { points, targetSeconds } = series;
    const W = 800;
    const H = 240;
    const padX = 44;
    const padTop = 28;
    const padBottom = 34;
    const plotW = W - padX * 2;
    const plotH = H - padTop - padBottom;

    const values = points.map((p) => p.seconds);
    if (targetSeconds) values.push(targetSeconds);
    const minV = Math.min(...values);
    const maxV = Math.max(...values);
    const span = Math.max(maxV - minV, 1);

    // Smaller time = better = higher on the chart.
    const y = (sec: number) => padTop + ((sec - minV) / span) * plotH;
    const x = (i: number) => (points.length === 1 ? padX + plotW / 2 : padX + (i / (points.length - 1)) * plotW);

    const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.seconds).toFixed(1)}`).join(' ');
    const targetY = targetSeconds ? y(targetSeconds) : null;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" className="h-56 w-full">
            {targetY !== null && (
                <>
                    <line x1={padX} y1={targetY} x2={W - padX} y2={targetY} stroke="#10b981" strokeWidth={2} strokeDasharray="6 5" />
                    <text x={W - padX} y={targetY - 7} textAnchor="end" className="fill-emerald-600 text-[15px] font-semibold">
                        Objectif {formatDuration(targetSeconds!)}
                    </text>
                </>
            )}
            {points.length > 1 && (
                <path d={linePath} fill="none" stroke="#fc4c02" strokeWidth={3} strokeLinejoin="round" strokeLinecap="round" />
            )}
            {points.map((p, i) => (
                <g key={p.date + i}>
                    <circle cx={x(i)} cy={y(p.seconds)} r={5} fill="#fc4c02" stroke="#fff" strokeWidth={2} />
                    <text x={x(i)} y={y(p.seconds) - 12} textAnchor="middle" className="fill-neutral-800 text-[15px] font-bold">
                        {formatDuration(p.seconds)}
                    </text>
                    <text x={x(i)} y={H - 12} textAnchor="middle" className="fill-neutral-400 text-[13px]">
                        {shortDate(p.date)}
                    </text>
                </g>
            ))}
        </svg>
    );
}

function StatTile({ icon: Icon, tint, value, label }: { icon: ComponentType<{ size?: number }>; tint: string; value: string; label: string }) {
    return (
        <Card className="flex items-center gap-3">
            <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${tint}`}>
                <Icon size={19} />
            </span>
            <div>
                <p className="text-xl font-bold leading-none tabular-nums text-neutral-900">{value}</p>
                <p className="mt-1 text-[11px] uppercase tracking-wide text-neutral-400">{label}</p>
            </div>
        </Card>
    );
}

const medalTint = ['bg-amber-100 text-amber-600', 'bg-slate-100 text-slate-500', 'bg-orange-100 text-orange-500'];

export default function Progression({ goal, records, series, focusDistance, projection, vdot, stats }: Props) {
    const [selected, setSelected] = useState(focusDistance);
    const chart = series.find((s) => s.distanceMeters === selected) ?? series[0];

    if (records.length === 0 && goal === null) {
        return (
            <>
                <Head title="Progression" />
                <PagePlaceholder
                    title="Progression"
                    description="Enregistre tes premières sorties : tes records, tes courbes de progression et le suivi de l'objectif s'afficheront ici."
                    icon={TrendingUp}
                />
            </>
        );
    }

    return (
        <>
            <Head title="Progression" />
            <h1 className="mb-6 text-2xl font-bold tracking-tight text-neutral-900">Progression</h1>

            {/* Goal tracker */}
            {goal && (
                <div className="animate-fade-up mb-5 overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/50 p-5 shadow-sm shadow-neutral-200/60">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span
                                className={`flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg ${
                                    goal.achieved
                                        ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/30'
                                        : 'bg-gradient-to-br from-brand-400 to-brand-600 shadow-brand-500/30'
                                }`}
                            >
                                <Target size={24} />
                            </span>
                            <div>
                                <p className="text-lg font-bold leading-tight text-neutral-900">{goal.label}</p>
                                <p className="text-sm text-neutral-500">
                                    {goal.currentSeconds === null
                                        ? `Pas encore de ${goal.distanceLabel} enregistré`
                                        : goal.achieved
                                          ? `Objectif atteint — ${formatDuration(goal.currentSeconds)} 🎉`
                                          : `Actuel ${formatDuration(goal.currentSeconds)} · encore ${formatDuration(goal.gapSeconds ?? 0)} à gagner`}
                                </p>
                            </div>
                        </div>
                        {goal.daysLeft !== null && (
                            <div className="rounded-xl border border-neutral-200 bg-white px-4 py-2 text-right">
                                <p className="text-2xl font-black leading-none tabular-nums text-brand-500">J-{Math.max(0, goal.daysLeft)}</p>
                                <p className="mt-1 text-[11px] text-neutral-500">
                                    {goal.raceName ?? 'Course'} · {goal.weeksLeft} sem.
                                </p>
                            </div>
                        )}
                    </div>

                    {goal.currentSeconds !== null && (
                        <div className="mt-4">
                            <div className="mb-1.5 flex items-center justify-between text-xs font-medium text-neutral-500">
                                <span>{formatDuration(goal.currentSeconds)}</span>
                                <span className="tabular-nums">
                                    <GoalDigits pct={goal.progressPct} />%
                                </span>
                                <span className="text-emerald-600">Objectif {formatDuration(goal.targetSeconds)}</span>
                            </div>
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div
                                    className={`animate-bar h-full rounded-full ${goal.achieved ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand-400 to-brand-600'}`}
                                    style={{ width: `${goal.progressPct}%` }}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Stat tiles */}
            <div className="animate-fade-up mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4" style={{ animationDelay: '60ms' }}>
                <StatTile icon={Gauge} tint="bg-brand-100 text-brand-600" value={vdot !== null ? `${vdot}` : '—'} label="VDOT estimé" />
                <StatTile icon={Trophy} tint="bg-amber-100 text-amber-600" value={`${records.length}`} label="Records" />
                <StatTile icon={Activity} tint="bg-sky-100 text-sky-600" value={`${stats.runs}`} label="Sorties" />
                <StatTile icon={Flag} tint="bg-emerald-100 text-emerald-600" value={`${formatKilometers(stats.totalDistanceMeters)} km`} label="Distance totale" />
            </div>

            {/* Projection */}
            {projection && (
                <div className="animate-fade-up mb-5" style={{ animationDelay: '90ms' }}>
                    <Card>
                        <div className="flex items-center gap-3">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                                <Sparkles size={19} />
                            </span>
                            <p className="text-sm text-neutral-600">
                                Projection Riegel : sur la base de ton <strong className="text-neutral-900">{projection.fromLabel}</strong>, un{' '}
                                <strong className="text-neutral-900">{projection.toLabel}</strong> est estimé à{' '}
                                <strong className="text-brand-600">{formatDuration(projection.predictedSeconds)}</strong>.{' '}
                                {projection.beatsTarget ? (
                                    <span className="font-semibold text-emerald-600">L'objectif est à portée 🔥</span>
                                ) : (
                                    <span className="text-neutral-500">Continue, tu t'en rapproches.</span>
                                )}
                            </p>
                        </div>
                    </Card>
                </div>
            )}

            {/* Progression chart */}
            {chart && (
                <div className="animate-fade-up mb-5" style={{ animationDelay: '120ms' }}>
                    <Card title="Courbe de progression">
                        {series.length > 1 && (
                            <div className="mb-3 flex flex-wrap gap-2">
                                {series.map((s) => (
                                    <button
                                        key={s.distanceMeters}
                                        onClick={() => setSelected(s.distanceMeters)}
                                        className={`rounded-full px-3 py-1 text-xs font-semibold transition-colors ${
                                            s.distanceMeters === chart.distanceMeters
                                                ? 'bg-brand-500 text-white'
                                                : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
                                        }`}
                                    >
                                        {s.label}
                                    </button>
                                ))}
                            </div>
                        )}
                        <ProgressionChart series={chart} />
                        {chart.points.length < 2 && (
                            <p className="mt-2 text-center text-xs text-neutral-400">
                                Une seule mesure pour l'instant — la courbe se dessinera au fil de tes sorties.
                            </p>
                        )}
                    </Card>
                </div>
            )}

            {/* Records */}
            <div className="animate-fade-up" style={{ animationDelay: '150ms' }}>
                <Card title="Records personnels">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {records.map((r, i) => (
                            <Link
                                key={r.distanceMeters}
                                href={`/activites/${r.activityId}`}
                                className="group rounded-xl border border-neutral-200 bg-white p-3 transition-all hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md hover:shadow-neutral-200/60"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold uppercase tracking-wide text-neutral-400">{r.label}</span>
                                    <span className={`flex h-6 w-6 items-center justify-center rounded-full ${medalTint[Math.min(i, 2)]}`}>
                                        <Trophy size={13} />
                                    </span>
                                </div>
                                <p className="mt-1.5 text-2xl font-black tabular-nums text-neutral-900">{formatDuration(r.durationSeconds)}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">
                                    {formatPace(r.paceSecondsPerKm)} · {shortDate(r.occurredAt)}
                                </p>
                            </Link>
                        ))}
                    </div>
                    {records.length === 0 && <p className="text-sm text-neutral-400">Aucun record pour l'instant.</p>}
                </Card>
            </div>
        </>
    );
}

Progression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
