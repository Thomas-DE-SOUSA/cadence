import { Head, Link } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import {
    CalendarCheck,
    ChevronRight,
    Flame,
    Footprints,
    Gauge,
    Lock,
    Medal,
    Moon,
    Mountain,
    MoveUpRight,
    Route,
    Sparkles,
    Target,
    Trophy,
} from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { HelpTip } from '@/components/HelpTip';
import { RouteMap } from '@/features/activity/components/RouteMap';
import { useCountUp } from '@/lib/useCountUp';
import { formatDate, formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface MedalItem {
    label: string;
    rank: number;
    distanceMeters: number;
}

interface ActivityRow {
    id: string;
    occurredAt: string;
    source: string;
    distanceMeters: number;
    movingSeconds: number;
    averagePaceSecondsPerKm: number;
    elevationGainMeters: number;
    medals: MedalItem[];
    track: [number, number][] | null;
}

interface RecordItem {
    label: string;
    distanceMeters: number;
    durationSeconds: number;
    paceSecondsPerKm: number;
    activityId: string;
}

interface Achievement {
    id: string;
    title: string;
    description: string;
    icon: string;
    unlocked: boolean;
}

interface GoalWidget {
    label: string;
    distanceMeters: number;
    targetSeconds: number;
    currentSeconds: number | null;
    achieved: boolean;
    progressPct: number;
    gapSeconds: number | null;
    raceName: string | null;
    daysLeft: number | null;
}

interface Props {
    stats: { totalActivities: number; totalDistanceMeters: number; thisWeekMeters: number; lastActivityDate: string | null };
    streak: { weeks: number; days: { label: string; date: string; active: boolean; today: boolean; rest: boolean }[] };
    records: RecordItem[];
    achievements: Achievement[];
    activities: ActivityRow[];
    athlete: { name: string; initial: string; age: number | null } | null;
    vdot: number | null;
    goal: GoalWidget | null;
}

const ACHIEVEMENT_ICONS: Record<string, ComponentType<{ size?: number; className?: string }>> = {
    footprints: Footprints,
    route: Route,
    trophy: Trophy,
    gauge: Gauge,
    mountain: Mountain,
    calendar: CalendarCheck,
    target: Target,
};

const MEDAL_STYLES: Record<number, { cls: string; label: string }> = {
    1: { cls: 'bg-amber-50 text-amber-700 border-amber-200', label: 'Record' },
    2: { cls: 'bg-neutral-100 text-neutral-600 border-neutral-300', label: '2e' },
    3: { cls: 'bg-orange-50 text-orange-700 border-orange-200', label: '3e' },
};

function StreakDigits({ weeks }: { weeks: number }) {
    return <>{Math.round(useCountUp(weeks))}</>;
}

function MiniStat({ icon: Icon, tint, value, label }: { icon: ComponentType<{ size?: number }>; tint: string; value: string; label: string }) {
    return (
        <div className="rounded-xl border border-neutral-100 bg-neutral-50/70 p-3">
            <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${tint}`}>
                <Icon size={16} />
            </span>
            <p className="mt-2 text-lg font-extrabold leading-none tabular-nums text-neutral-900">{value}</p>
            <p className="mt-1 text-[10px] font-medium uppercase tracking-wide text-neutral-400">{label}</p>
        </div>
    );
}

export default function History({ stats, streak, records, achievements, activities, athlete, vdot, goal }: Props) {
    const unlocked = achievements.filter((a) => a.unlocked).length;
    const todayRest = streak.days.some((d) => d.today && d.rest && !d.active);
    const name = athlete?.name ?? 'Athlète';

    return (
        <>
            <Head title="Tableau de bord" />

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                {/* Left — athlete panel */}
                <aside className="animate-fade-up space-y-4 lg:col-span-3 lg:sticky lg:top-24 lg:self-start">
                    <div className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm shadow-neutral-200/60">
                        <div className="flex items-center gap-3 bg-gradient-to-br from-brand-500 to-brand-600 p-4 text-white">
                            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl font-black backdrop-blur">
                                {athlete?.initial ?? '?'}
                            </span>
                            <div className="min-w-0">
                                <p className="truncate text-lg font-extrabold leading-tight">{name}</p>
                                <p className="text-xs text-white/80">{athlete?.age !== null && athlete?.age !== undefined ? `${athlete.age} ans · coureur` : 'Coureur'}</p>
                            </div>
                        </div>
                        <div className="p-4">
                            <div className="grid grid-cols-2 gap-2.5">
                                <MiniStat icon={Gauge} tint="bg-brand-100 text-brand-600" value={vdot !== null ? `${vdot}` : '—'} label="VDOT" />
                                <MiniStat icon={MoveUpRight} tint="bg-emerald-100 text-emerald-600" value={`${formatKilometers(stats.thisWeekMeters)}`} label="Cette sem. (km)" />
                                <MiniStat icon={Mountain} tint="bg-sky-100 text-sky-600" value={`${formatKilometers(stats.totalDistanceMeters)}`} label="Total (km)" />
                                <MiniStat icon={CalendarCheck} tint="bg-violet-100 text-violet-600" value={`${stats.totalActivities}`} label="Sorties" />
                            </div>
                            <Link
                                href="/profil"
                                className="mt-3 flex items-center justify-center gap-1 rounded-lg border border-neutral-200 py-2 text-sm font-semibold text-neutral-600 transition-colors hover:border-brand-200 hover:bg-brand-50/50 hover:text-brand-600"
                            >
                                Voir le profil <ChevronRight size={15} />
                            </Link>
                        </div>
                    </div>

                    {/* Streak */}
                    <div className="overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/70 p-4 shadow-sm shadow-neutral-200/60">
                        <div className="flex items-center gap-3">
                            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-md shadow-brand-500/30">
                                <Flame size={22} />
                            </span>
                            <div>
                                <p className="text-2xl font-black leading-none tabular-nums text-neutral-900">
                                    <StreakDigits weeks={streak.weeks} /> <span className="text-sm font-bold text-neutral-500">sem.</span>
                                    <HelpTip
                                        label="Série"
                                        side="bottom"
                                        text="Nombre de semaines consécutives avec au moins une sortie. Un jour de repos prévu ne casse pas la série."
                                    />
                                </p>
                                <p className="mt-0.5 text-xs text-neutral-500">
                                    {todayRest ? 'Repos aujourd’hui 😴' : streak.weeks > 0 ? 'de série 🔥' : 'Lance ta série !'}
                                </p>
                            </div>
                        </div>
                        <div className="mt-3 flex justify-between gap-1">
                            {streak.days.map((d) => (
                                <div key={d.date} className="flex flex-col items-center gap-1">
                                    <span className="text-[10px] font-medium uppercase text-neutral-400">{d.label}</span>
                                    <span
                                        title={d.rest && !d.active ? 'Repos prévu — série préservée' : undefined}
                                        className={`flex h-7 w-7 items-center justify-center rounded-full border text-[10px] font-bold ${
                                            d.active
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : d.rest
                                                  ? 'border-indigo-200 bg-indigo-50 text-indigo-400'
                                                  : d.today
                                                    ? 'border-brand-300 bg-white text-brand-500'
                                                    : 'border-neutral-200 bg-white text-neutral-300'
                                        }`}
                                    >
                                        {d.active ? <Flame size={12} /> : d.rest ? <Moon size={11} /> : d.date.slice(8)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </aside>

                {/* Middle — feed */}
                <section className="animate-fade-up lg:col-span-6" style={{ animationDelay: '60ms' }}>
                    <div className="mb-3 flex items-baseline justify-between">
                        <h2 className="text-lg font-extrabold tracking-tight text-neutral-900">Dernières sorties</h2>
                        <span className="text-xs font-medium text-neutral-400">{activities.length} activités</span>
                    </div>
                    {activities.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-neutral-300 p-10 text-center text-neutral-400">
                            Aucune activité. Ajoute ta première sortie.
                        </div>
                    ) : (
                        <ul className="space-y-3">
                            {activities.map((a) => (
                                <li key={a.id}>
                                    <Link
                                        href={`/activites/${a.id}`}
                                        className="flex items-stretch gap-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/50 transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lg hover:shadow-neutral-300/40"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-baseline gap-2">
                                                    <span className="text-2xl font-black tabular-nums text-neutral-900">
                                                        {formatKilometers(a.distanceMeters)}
                                                    </span>
                                                    <span className="text-sm font-semibold text-neutral-400">km</span>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-bold tabular-nums text-neutral-800">{formatDuration(a.movingSeconds)}</p>
                                                    <p className="text-xs font-semibold tabular-nums text-brand-600">{formatPace(a.averagePaceSecondsPerKm)}</p>
                                                </div>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-400">
                                                <span>{formatDate(a.occurredAt)}</span>
                                                <span>· {a.source === 'STRAVA' ? 'Strava' : a.source === 'GPX' ? 'GPX' : 'Manuel'}</span>
                                                {a.elevationGainMeters > 0 && <span>· D+ {a.elevationGainMeters} m</span>}
                                            </div>
                                            {a.medals.length > 0 && (
                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                    {a.medals.map((m) => {
                                                        const style = MEDAL_STYLES[m.rank] ?? MEDAL_STYLES[3];
                                                        return (
                                                            <span
                                                                key={m.distanceMeters}
                                                                className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold ${style.cls}`}
                                                            >
                                                                <Medal size={12} />
                                                                {style.label} · {m.label}
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                        {a.track && (
                                            <div className="hidden w-28 shrink-0 items-center rounded-xl border border-neutral-200 bg-gradient-to-br from-neutral-50 to-white p-1.5 sm:flex">
                                                <RouteMap track={a.track} className="h-full w-full" />
                                            </div>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Right — widgets */}
                <aside className="animate-fade-up space-y-4 lg:col-span-3 lg:sticky lg:top-24 lg:self-start" style={{ animationDelay: '120ms' }}>
                    {goal && (
                        <div className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm shadow-neutral-200/60">
                            <div className="flex items-center justify-between gap-2 bg-gradient-to-br from-neutral-900 to-neutral-700 p-4 text-white">
                                <div className="flex items-center gap-2">
                                    <Target size={18} className="text-brand-400" />
                                    <p className="text-sm font-bold">{goal.label}</p>
                                </div>
                                {goal.daysLeft !== null && goal.daysLeft >= 0 && (
                                    <span className="rounded-full bg-white/15 px-2 py-0.5 text-xs font-bold tabular-nums">J-{goal.daysLeft}</span>
                                )}
                            </div>
                            <div className="p-4">
                                <div className="mb-1.5 flex items-center justify-between text-xs font-medium text-neutral-500">
                                    <span className="tabular-nums">{goal.currentSeconds !== null ? formatDuration(goal.currentSeconds) : '—'}</span>
                                    <span className="text-emerald-600 tabular-nums">Objectif {formatDuration(goal.targetSeconds)}</span>
                                </div>
                                <div className="h-2.5 w-full overflow-hidden rounded-full bg-neutral-200">
                                    <div
                                        className={`animate-bar h-full rounded-full ${goal.achieved ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand-400 to-brand-600'}`}
                                        style={{ width: `${goal.progressPct}%` }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-neutral-500">
                                    {goal.currentSeconds === null
                                        ? 'Enregistre une sortie sur la distance pour lancer le suivi.'
                                        : goal.achieved
                                          ? 'Objectif atteint 🎉'
                                          : `Encore ${formatDuration(goal.gapSeconds ?? 0)} à gagner`}
                                </p>
                            </div>
                        </div>
                    )}

                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Records
                                <HelpTip
                                    label="Records personnels"
                                    text="Ton meilleur temps sur chaque distance, calculé à partir de tes portions les plus rapides."
                                />
                            </span>
                        }
                    >
                        {records.length === 0 ? (
                            <p className="text-sm text-neutral-400">Pas encore de record — chaque sortie compte !</p>
                        ) : (
                            <ul className="space-y-2">
                                {records.map((r) => (
                                    <li key={r.distanceMeters} className="flex items-center gap-3 rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-2.5">
                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                            <Trophy size={15} />
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-sm font-bold text-neutral-900">{r.label}</p>
                                            <p className="text-[11px] text-neutral-500">{formatPace(r.paceSecondsPerKm)}</p>
                                        </div>
                                        <Link href={`/activites/${r.activityId}`} className="text-sm font-bold tabular-nums text-amber-700 hover:text-amber-800">
                                            {formatDuration(r.durationSeconds)}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                {`Succès — ${unlocked}/${achievements.length}`}
                                <HelpTip label="Succès" text="Des badges débloqués au fil de tes performances : distances, volume, allure, objectif." />
                            </span>
                        }
                    >
                        <div className="grid grid-cols-3 gap-2">
                            {achievements.map((a) => {
                                const Icon = ACHIEVEMENT_ICONS[a.icon] ?? Sparkles;
                                return (
                                    <div
                                        key={a.id}
                                        title={`${a.title} — ${a.description}`}
                                        className={`flex flex-col items-center gap-1 rounded-xl border p-2 text-center transition-all ${
                                            a.unlocked ? 'border-brand-200 bg-gradient-to-br from-brand-50 to-white' : 'border-neutral-200 bg-neutral-50 opacity-70'
                                        }`}
                                    >
                                        <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${a.unlocked ? 'bg-brand-500 text-white' : 'bg-neutral-200 text-neutral-400'}`}>
                                            {a.unlocked ? <Icon size={16} /> : <Lock size={13} />}
                                        </span>
                                        <p className={`text-[10px] font-bold leading-tight ${a.unlocked ? 'text-neutral-800' : 'text-neutral-400'}`}>{a.title}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                </aside>
            </div>
        </>
    );
}

History.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
