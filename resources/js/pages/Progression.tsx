import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { Activity, ChevronRight, Flag, Gauge, Sparkles, Target, TrendingUp, Trophy } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { HelpTip } from '@/components/HelpTip';
import { PagePlaceholder } from '@/components/PagePlaceholder';
import { useCountUp } from '@/lib/useCountUp';
import { formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

const VDOT_HELP =
    'Un score de forme unique (façon VO₂max) calculé à partir de ton meilleur effort. Plus il est haut, plus tu es rapide. Il sert de base à toutes tes allures.';
const PROGRESS_HELP = 'Proximité de ton objectif : temps cible ÷ temps actuel. À 100 %, l’objectif est atteint.';
const RIEGEL_HELP =
    'Formule de Riegel : elle estime ton temps sur une distance à partir d’une performance connue (on ralentit un peu quand la distance augmente). Indicatif.';
const CURVE_HELP =
    'Ton meilleur temps sur cette distance, sortie après sortie. Plus le point est bas, plus tu es rapide. La ligne dorée est ton objectif.';
const RECORDS_HELP =
    'Ton meilleur temps sur chaque distance, calculé à partir de tes portions les plus rapides — pas seulement d’une course entière.';

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

/** Formats a Y-M-D from an ISO string without timezone drift (local midnight). */
function shortDate(iso: string): string {
    const parts = iso.slice(0, 10).split('-');
    const d =
        parts.length === 3
            ? new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
            : new Date(iso);
    return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

function GoalDigits({ pct }: { pct: number }) {
    return <>{Math.round(useCountUp(pct))}</>;
}

/**
 * Best-effort-per-run curve over time, with the goal line. Sized in real pixels
 * from the measured container width, so it fills the width with no distortion.
 */
function ProgressionChart({ series }: { series: Series }) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(760);

    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return;
        const ro = new ResizeObserver((entries) => {
            const w = entries[0]?.contentRect.width;
            if (w) setWidth(w);
        });
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const { points, targetSeconds } = series;
    const H = 260;
    const padL = 16;
    const padR = 16;
    const padTop = 34;
    const padBottom = 30;

    // Each point keeps a minimum breathing room; if the whole series is wider
    // than the card, the chart scrolls horizontally (into the past).
    const containerW = Math.max(width, 300);
    const minStep = 84;
    const neededW = padL + padR + Math.max(points.length - 1, 1) * minStep;
    const W = Math.max(containerW, neededW);
    const scrollable = W > containerW + 1;

    const plotW = W - padL - padR;
    const plotH = H - padTop - padBottom;

    const values = points.map((p) => p.seconds);
    if (targetSeconds) values.push(targetSeconds);
    const minV = Math.min(...values);
    const maxV = Math.max(...values);
    const span = Math.max(maxV - minV, 1);

    // Smaller time = better = higher on the chart. 8% headroom top & bottom.
    const y = (sec: number) => padTop + (0.08 + (0.84 * (sec - minV)) / span) * plotH;
    const x = (i: number) => (points.length === 1 ? padL + plotW / 2 : padL + (i / (points.length - 1)) * plotW);

    // Keep the latest run in view by default (scroll to the far right).
    useEffect(() => {
        const el = wrapRef.current;
        if (el) el.scrollLeft = el.scrollWidth;
    }, [W, series.distanceMeters, points.length]);

    const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.seconds).toFixed(1)}`).join(' ');
    const areaPath =
        points.length > 1
            ? `${linePath} L ${x(points.length - 1).toFixed(1)} ${(H - padBottom).toFixed(1)} L ${x(0).toFixed(1)} ${(H - padBottom).toFixed(1)} Z`
            : '';
    const targetY = targetSeconds ? y(targetSeconds) : null;

    return (
        <>
            <div ref={wrapRef} className="w-full overflow-x-auto">
                <svg width={W} height={H} viewBox={`0 0 ${W} ${H}`} className="block">
                    <defs>
                        <linearGradient id="prog-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stopColor="#1c855a" stopOpacity="0.16" />
                            <stop offset="1" stopColor="#1c855a" stopOpacity="0" />
                        </linearGradient>
                    </defs>

                    {/* baseline */}
                    <line x1={padL} y1={H - padBottom} x2={W - padR} y2={H - padBottom} stroke="#f1f1f2" strokeWidth={1} />

                    {targetY !== null && (
                        <>
                            <line x1={padL} y1={targetY} x2={W - padR} y2={targetY} stroke="#f59e0b" strokeWidth={1.5} strokeDasharray="5 5" />
                            <text x={W - padR} y={targetY - 6} textAnchor="end" fontSize={12} className="fill-amber-500 font-semibold">
                                Objectif {formatDuration(targetSeconds!)}
                            </text>
                        </>
                    )}

                    {areaPath && <path d={areaPath} fill="url(#prog-area)" />}
                    {points.length > 1 && (
                        <path d={linePath} fill="none" stroke="#1c855a" strokeWidth={2.5} strokeLinejoin="round" strokeLinecap="round" />
                    )}

                    {points.map((p, i) => (
                        <g key={p.date + i}>
                            <circle cx={x(i)} cy={y(p.seconds)} r={4.5} fill="#1c855a" stroke="#fff" strokeWidth={2} />
                            <text x={x(i)} y={y(p.seconds) - 12} textAnchor="middle" fontSize={13} className="fill-neutral-800 font-bold">
                                {formatDuration(p.seconds)}
                            </text>
                            <text x={x(i)} y={H - 10} textAnchor="middle" fontSize={12} className="fill-neutral-400">
                                {shortDate(p.date)}
                            </text>
                        </g>
                    ))}
                </svg>
            </div>
            {scrollable && (
                <p className="mt-1 text-center text-[11px] text-neutral-400">← fais défiler pour parcourir l'historique →</p>
            )}
        </>
    );
}

function StatCell({
    icon: Icon,
    tint,
    value,
    label,
    help,
}: {
    icon: ComponentType<{ size?: number; className?: string }>;
    tint: string;
    value: string;
    label: string;
    help?: string;
}) {
    return (
        <div className="flex flex-col items-center gap-1 px-2 py-4 text-center">
            <p className="text-xl font-extrabold leading-none tabular-nums text-neutral-900 sm:text-2xl">{value}</p>
            <p className="flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-neutral-400">
                <Icon size={11} className={tint} />
                {label}
                {help && <HelpTip label={label} text={help} size={12} />}
            </p>
        </div>
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
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Progression</h1>
                <p className="mt-1 text-sm text-neutral-500">Records, courbe et suivi de ton objectif.</p>
            </div>

            {/* Goal tracker */}
            {goal && (
                <div className="animate-fade-up mb-4 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/40 p-5 shadow-sm shadow-neutral-200/60">
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex min-w-0 items-center gap-3">
                            <span
                                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-md ${
                                    goal.achieved
                                        ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/25'
                                        : 'bg-gradient-to-br from-brand-400 to-brand-600 shadow-brand-500/25'
                                }`}
                            >
                                <Target size={22} />
                            </span>
                            <div className="min-w-0">
                                <p className="truncate font-bold leading-tight text-neutral-900">{goal.label}</p>
                                <p className="mt-0.5 text-sm text-neutral-500">
                                    {goal.currentSeconds === null
                                        ? `Pas encore de ${goal.distanceLabel} enregistré`
                                        : goal.achieved
                                          ? `Objectif atteint 🎉`
                                          : (
                                              <>
                                                  <span className="inline-flex items-center gap-1 font-semibold text-brand-600">
                                                      <GoalDigits pct={goal.progressPct} />%
                                                      <HelpTip label="Progression vers l’objectif" text={PROGRESS_HELP} size={13} />
                                                  </span>{' '}
                                                  · encore {formatDuration(goal.gapSeconds ?? 0)}
                                              </>
                                          )}
                                </p>
                            </div>
                        </div>
                        {goal.daysLeft !== null && (
                            <div className="shrink-0 rounded-xl bg-white px-3 py-1.5 text-center shadow-sm ring-1 ring-neutral-200">
                                <p className="text-lg font-black leading-none tabular-nums text-brand-600">J-{Math.max(0, goal.daysLeft)}</p>
                                <p className="mt-0.5 text-[10px] text-neutral-400">{goal.weeksLeft} sem.</p>
                            </div>
                        )}
                    </div>

                    {goal.currentSeconds !== null && (
                        <div className="mt-4">
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div
                                    className={`animate-bar h-full rounded-full ${goal.achieved ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand-400 to-brand-600'}`}
                                    style={{ width: `${goal.progressPct}%` }}
                                />
                            </div>
                            <div className="mt-1.5 flex items-center justify-between text-xs font-medium tabular-nums">
                                <span className="text-neutral-500">Actuel {formatDuration(goal.currentSeconds)}</span>
                                <span className="text-emerald-600">Objectif {formatDuration(goal.targetSeconds)}</span>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Stats — one-line strip in a single card */}
            <div
                className="animate-fade-up mb-4 grid grid-cols-4 divide-x divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm shadow-neutral-200/60"
                style={{ animationDelay: '60ms' }}
            >
                <StatCell icon={Gauge} tint="text-brand-500" value={vdot !== null ? `${vdot}` : '—'} label="VDOT" help={VDOT_HELP} />
                <StatCell icon={Trophy} tint="text-amber-500" value={`${records.length}`} label="Records" />
                <StatCell icon={Activity} tint="text-sky-500" value={`${stats.runs}`} label="Sorties" />
                <StatCell icon={Flag} tint="text-emerald-500" value={`${formatKilometers(stats.totalDistanceMeters)}`} label="Km" />
            </div>

            {/* Projection — slim banner */}
            {projection && (
                <div
                    className="animate-fade-up mb-4 flex items-start gap-2.5 rounded-xl border border-violet-200 bg-violet-50/60 px-4 py-3"
                    style={{ animationDelay: '90ms' }}
                >
                    <Sparkles size={17} className="mt-0.5 shrink-0 text-violet-500" />
                    <p className="text-sm leading-relaxed text-neutral-700">
                        <span className="inline-flex items-center gap-1 font-semibold text-neutral-900">
                            Projection Riegel
                            <HelpTip label="Projection Riegel" text={RIEGEL_HELP} size={13} />
                        </span>{' '}
                        : d'après ton <strong className="text-neutral-900">{projection.fromLabel}</strong>, un{' '}
                        <strong className="text-neutral-900">{projection.toLabel}</strong> est estimé à{' '}
                        <strong className="text-brand-600">{formatDuration(projection.predictedSeconds)}</strong>.{' '}
                        {projection.beatsTarget ? (
                            <span className="font-semibold text-emerald-600">L'objectif est à portée 🔥</span>
                        ) : (
                            <span className="text-neutral-500">Continue, tu t'en rapproches.</span>
                        )}
                    </p>
                </div>
            )}

            {/* Progression chart */}
            {chart && (
                <div className="animate-fade-up mb-4" style={{ animationDelay: '120ms' }}>
                    <Card
                        title={
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="inline-flex items-center gap-1.5 normal-case">
                                    Courbe de progression
                                    <HelpTip label="Courbe de progression" text={CURVE_HELP} />
                                </span>
                                {series.length > 1 && (
                                    <div className="flex flex-wrap gap-1.5">
                                        {series.map((s) => (
                                            <button
                                                key={s.distanceMeters}
                                                onClick={() => setSelected(s.distanceMeters)}
                                                className={`rounded-full px-2.5 py-1 text-xs font-semibold transition-colors ${
                                                    s.distanceMeters === chart.distanceMeters
                                                        ? 'bg-brand-500 text-white'
                                                        : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                                                }`}
                                            >
                                                {s.label}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        }
                    >
                        <ProgressionChart series={chart} />
                        {chart.points.length < 2 && (
                            <p className="mt-1 text-center text-xs text-neutral-400">
                                Une seule mesure pour l'instant — la courbe se dessinera au fil de tes sorties.
                            </p>
                        )}
                    </Card>
                </div>
            )}

            {/* Records */}
            {records.length > 0 && (
                <div className="animate-fade-up" style={{ animationDelay: '150ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Records personnels
                                <HelpTip label="Records personnels" text={RECORDS_HELP} />
                            </span>
                        }
                    >
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
                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-neutral-500">
                                        <span className="tabular-nums">{formatPace(r.paceSecondsPerKm)}</span>
                                        <span className="text-neutral-300">·</span>
                                        <span>{shortDate(r.occurredAt)}</span>
                                        <ChevronRight size={13} className="ml-auto text-neutral-300 transition-colors group-hover:text-brand-500" />
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </Card>
                </div>
            )}
        </>
    );
}

Progression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
