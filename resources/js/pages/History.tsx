import { Head, Link } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import {
    CalendarCheck,
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

interface Props {
    stats: { totalActivities: number; totalDistanceMeters: number; thisWeekMeters: number; lastActivityDate: string | null };
    streak: { weeks: number; days: { label: string; date: string; active: boolean; today: boolean; rest: boolean }[] };
    records: RecordItem[];
    achievements: Achievement[];
    activities: ActivityRow[];
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

export default function History({ stats, streak, records, achievements, activities }: Props) {
    const unlocked = achievements.filter((a) => a.unlocked).length;
    const todayRest = streak.days.some((d) => d.today && d.rest && !d.active);

    return (
        <>
            <Head title="Tableau de bord" />

            <h1 className="mb-6 text-2xl font-bold tracking-tight text-neutral-900">Tableau de bord</h1>

            {/* Streak hero */}
            <div className="animate-fade-up mb-5 overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-orange-50/60 p-5 shadow-sm shadow-neutral-200/60">
                <div className="flex flex-wrap items-center justify-between gap-5">
                    <div className="flex items-center gap-4">
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-brand-500/30">
                            <Flame size={28} />
                        </span>
                        <div>
                            <p className="text-3xl font-black leading-none tabular-nums text-neutral-900">
                                <StreakDigits weeks={streak.weeks} />{' '}
                                <span className="text-lg font-bold text-neutral-500">
                                    semaine{streak.weeks > 1 ? 's' : ''}
                                </span>{' '}
                                <HelpTip
                                    label="Série"
                                    side="bottom"
                                    size={16}
                                    text="Le nombre de semaines consécutives avec au moins une sortie. Un jour de repos prévu par ton programme ne casse pas la série."
                                />
                            </p>
                            <p className="mt-1 text-sm text-neutral-500">
                                {todayRest
                                    ? 'Repos prévu aujourd’hui — ta série tient bon 😴'
                                    : streak.weeks > 0
                                      ? 'de série d’entraînement 🔥'
                                      : 'Lance ta série cette semaine !'}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {streak.days.map((d) => (
                            <div key={d.date} className="flex flex-col items-center gap-1.5">
                                <span className="text-[11px] font-medium uppercase text-neutral-400">{d.label}</span>
                                <span
                                    title={d.rest && !d.active ? 'Repos prévu — ta série est préservée' : undefined}
                                    className={`flex h-8 w-8 items-center justify-center rounded-full border text-[11px] font-bold ${
                                        d.active
                                            ? 'border-brand-500 bg-brand-500 text-white'
                                            : d.rest
                                              ? 'border-indigo-200 bg-indigo-50 text-indigo-400'
                                              : d.today
                                                ? 'border-brand-300 bg-white text-brand-500'
                                                : 'border-neutral-200 bg-white text-neutral-300'
                                    }`}
                                >
                                    {d.active ? <Flame size={14} /> : d.rest ? <Moon size={13} /> : d.date.slice(8)}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Stats */}
            <div className="animate-fade-up mb-5 grid grid-cols-3 gap-3" style={{ animationDelay: '60ms' }}>
                {[
                    { icon: CalendarCheck, tint: 'bg-brand-100 text-brand-600', value: `${stats.totalActivities}`, label: 'Sorties' },
                    { icon: Mountain, tint: 'bg-sky-100 text-sky-600', value: `${formatKilometers(stats.totalDistanceMeters)} km`, label: 'Distance totale' },
                    { icon: MoveUpRight, tint: 'bg-emerald-100 text-emerald-600', value: `${formatKilometers(stats.thisWeekMeters)} km`, label: 'Cette semaine' },
                ].map((s) => (
                    <div
                        key={s.label}
                        className="flex items-center gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/60"
                    >
                        <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${s.tint}`}>
                            <s.icon size={18} />
                        </span>
                        <div>
                            <p className="text-lg font-bold leading-none tabular-nums text-neutral-900">{s.value}</p>
                            <p className="mt-1 text-[11px] uppercase tracking-wide text-neutral-400">{s.label}</p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                {/* Records + achievements */}
                <div className="animate-fade-up space-y-5 lg:col-span-1" style={{ animationDelay: '90ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Records personnels
                                <HelpTip
                                    label="Records personnels"
                                    text="Ton meilleur temps sur chaque distance, calculé à partir de tes portions les plus rapides — pas seulement d’une course entière."
                                />
                            </span>
                        }
                    >
                        {records.length === 0 ? (
                            <p className="text-sm text-neutral-400">Pas encore de record — chaque sortie compte !</p>
                        ) : (
                            <ul className="space-y-2">
                                {records.map((r) => (
                                    <li
                                        key={r.distanceMeters}
                                        className="flex items-center gap-3 rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-3"
                                    >
                                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                            <Trophy size={16} />
                                        </span>
                                        <div className="flex-1">
                                            <p className="text-sm font-bold text-neutral-900">{r.label}</p>
                                            <p className="text-xs text-neutral-500">{formatPace(r.paceSecondsPerKm)}</p>
                                        </div>
                                        <Link
                                            href={`/activites/${r.activityId}`}
                                            className="text-sm font-bold tabular-nums text-amber-700 hover:text-amber-800"
                                        >
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
                                <HelpTip
                                    label="Succès"
                                    text="Des badges débloqués au fil de tes performances (distances franchies, volume, allure, objectif). Une façon ludique de suivre tes paliers."
                                />
                            </span>
                        }
                    >
                        <div className="grid grid-cols-2 gap-2.5">
                            {achievements.map((a) => {
                                const Icon = ACHIEVEMENT_ICONS[a.icon] ?? Sparkles;
                                return (
                                    <div
                                        key={a.id}
                                        className={`rounded-xl border p-3 transition-all ${
                                            a.unlocked
                                                ? 'border-brand-200 bg-gradient-to-br from-brand-50 to-white'
                                                : 'border-neutral-200 bg-neutral-50 opacity-70'
                                        }`}
                                    >
                                        <span
                                            className={`flex h-9 w-9 items-center justify-center rounded-lg ${
                                                a.unlocked ? 'bg-brand-500 text-white' : 'bg-neutral-200 text-neutral-400'
                                            }`}
                                        >
                                            {a.unlocked ? <Icon size={17} /> : <Lock size={15} />}
                                        </span>
                                        <p className={`mt-2 text-xs font-bold ${a.unlocked ? 'text-neutral-900' : 'text-neutral-400'}`}>
                                            {a.title}
                                        </p>
                                        <p className="mt-0.5 text-[11px] leading-tight text-neutral-400">{a.description}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>
                </div>

                {/* Feed */}
                <div className="animate-fade-up lg:col-span-2" style={{ animationDelay: '120ms' }}>
                    <p className="mb-3 text-[13px] font-semibold uppercase tracking-wide text-neutral-500">Mes sorties</p>
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
                                        className="flex items-stretch gap-4 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/50 transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md hover:shadow-neutral-200/70"
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
                                                    <p className="text-sm font-bold tabular-nums text-neutral-800">
                                                        {formatDuration(a.movingSeconds)}
                                                    </p>
                                                    <p className="text-xs font-semibold tabular-nums text-brand-600">
                                                        {formatPace(a.averagePaceSecondsPerKm)}
                                                    </p>
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
                </div>
            </div>
        </>
    );
}

History.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
