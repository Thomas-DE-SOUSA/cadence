import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft, CheckCircle2, Circle, Sparkles, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { formatDate, formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface ObjectiveResult {
    id: string;
    label: string;
    type: string;
    achieved: boolean;
    progress: number;
    detail: string;
}

interface AssignedActivity {
    id: string;
    occurredAt: string;
    distanceMeters: number;
    movingSeconds: number;
    averagePaceSecondsPerKm: number;
}

interface AvailableActivity {
    id: string;
    occurredAt: string;
    distanceMeters: number;
}

interface PlannedSession {
    date: string;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetDurationSeconds: number | null;
    targetPaceSecondsPerKm: number | null;
}

interface CycleWeek {
    label: string;
    sessions: PlannedSession[];
}

interface Cycle {
    id: string;
    name: string;
    focus: string;
    startDate: string;
    endDate: string;
    weeks: CycleWeek[];
}

interface Program {
    id: string;
    name: string;
    goal: string;
    targetRaceName: string;
    targetRaceDate: string | null;
    startDate: string;
    endDate: string | null;
    priority: string;
    status: string;
    objectives: ObjectiveResult[];
    activities: AssignedActivity[];
}

interface Props {
    program: Program;
    available: AvailableActivity[];
    cycles: Cycle[];
}

const SESSION_STYLES: Record<string, { label: string; dot: string; text: string }> = {
    EASY: { label: 'Footing', dot: 'bg-emerald-400', text: 'text-emerald-300' },
    LONG: { label: 'Sortie longue', dot: 'bg-sky-400', text: 'text-sky-300' },
    THRESHOLD: { label: 'Seuil', dot: 'bg-orange-400', text: 'text-orange-300' },
    INTERVALS: { label: 'Fractionné', dot: 'bg-red-400', text: 'text-red-300' },
    RECOVERY: { label: 'Récupération', dot: 'bg-teal-400', text: 'text-teal-300' },
    RACE_PACE: { label: 'Allure course', dot: 'bg-fuchsia-400', text: 'text-fuchsia-300' },
    CROSS: { label: 'Cross-training', dot: 'bg-indigo-400', text: 'text-indigo-300' },
    REST: { label: 'Repos', dot: 'bg-neutral-600', text: 'text-neutral-500' },
};

function sessionStyle(type: string) {
    return SESSION_STYLES[type] ?? SESSION_STYLES.EASY;
}

function weekdayLabel(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' });
}

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function ProgramDetail({ program, available, cycles }: Props) {
    const assign = useForm({ activity_id: available[0]?.id ?? '' });
    const generate = useForm({ start_date: todayIso(), weeks: 2, ressenti: '' });

    function submitAssign(e: FormEvent) {
        e.preventDefault();
        if (assign.data.activity_id) {
            assign.post(`/programme/${program.id}/assigner`, { preserveScroll: true });
        }
    }

    function submitGenerate(e: FormEvent) {
        e.preventDefault();
        generate.post(`/programme/${program.id}/generer-cycle`, { preserveScroll: true });
    }

    function remove(activityId: string) {
        router.post(`/programme/${program.id}/retirer`, { activity_id: activityId }, { preserveScroll: true });
    }

    const achieved = program.objectives.filter((o) => o.achieved).length;

    return (
        <>
            <Head title={program.name} />
            <Link
                href="/programme"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> Programmes
            </Link>

            <div className="mb-8 flex flex-wrap items-end justify-between gap-4 border-b border-neutral-800 pb-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{program.name}</h1>
                    <p className="mt-1 text-sm text-neutral-400">
                        {program.goal}
                        {program.targetRaceName ? ` · ${program.targetRaceName}` : ''}
                        {program.targetRaceDate ? ` — ${formatDate(program.targetRaceDate)}` : ''}
                    </p>
                </div>
                <div className="flex gap-6 text-right">
                    <div>
                        <p className="text-2xl font-bold tabular-nums text-lime-400">
                            {achieved}/{program.objectives.length}
                        </p>
                        <p className="text-xs uppercase tracking-wide text-neutral-500">Objectifs</p>
                    </div>
                    <div>
                        <p className="text-2xl font-bold tabular-nums text-neutral-100">{cycles.length}</p>
                        <p className="text-xs uppercase tracking-wide text-neutral-500">Cycles</p>
                    </div>
                    <div>
                        <p className="text-2xl font-bold tabular-nums text-neutral-100">{program.activities.length}</p>
                        <p className="text-xs uppercase tracking-wide text-neutral-500">Sorties</p>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Main column — the plan */}
                <div className="space-y-6 lg:col-span-2">
                    {cycles.length === 0 ? (
                        <Card title="Plan d'entraînement">
                            <p className="text-sm text-neutral-400">
                                Aucun cycle pour l'instant. Générez un premier cycle avec l'IA à partir de vos performances et de
                                votre ressenti.
                            </p>
                        </Card>
                    ) : (
                        cycles.map((cycle) => (
                            <Card key={cycle.id} title={cycle.name}>
                                <p className="-mt-1 mb-4 text-sm text-neutral-400">
                                    {cycle.focus}
                                    <span className="ml-2 text-xs text-neutral-500">
                                        {formatDate(cycle.startDate)} → {formatDate(cycle.endDate)}
                                    </span>
                                </p>
                                <div className="space-y-5">
                                    {cycle.weeks.map((week) => (
                                        <div key={week.label}>
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                                {week.label}
                                            </p>
                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                {week.sessions.map((s) => {
                                                    const style = sessionStyle(s.type);
                                                    return (
                                                        <div
                                                            key={s.date}
                                                            className="rounded-lg border border-neutral-800 bg-neutral-900/60 p-3"
                                                        >
                                                            <div className="mb-1 flex items-center justify-between">
                                                                <span className="flex items-center gap-2 text-xs font-medium text-neutral-300">
                                                                    <span className={`h-2 w-2 rounded-full ${style.dot}`} />
                                                                    {weekdayLabel(s.date)}
                                                                </span>
                                                                <span className={`text-[11px] font-semibold uppercase ${style.text}`}>
                                                                    {style.label}
                                                                </span>
                                                            </div>
                                                            <p className="text-sm font-medium text-neutral-100">{s.title}</p>
                                                            <p className="mt-0.5 text-xs leading-relaxed text-neutral-400">
                                                                {s.description}
                                                            </p>
                                                            {(s.targetDistanceMeters || s.targetPaceSecondsPerKm) && (
                                                                <p className="mt-1.5 text-xs tabular-nums text-neutral-500">
                                                                    {s.targetDistanceMeters
                                                                        ? `${formatKilometers(s.targetDistanceMeters)} km`
                                                                        : ''}
                                                                    {s.targetDistanceMeters && s.targetPaceSecondsPerKm ? ' · ' : ''}
                                                                    {s.targetPaceSecondsPerKm
                                                                        ? formatPace(s.targetPaceSecondsPerKm)
                                                                        : ''}
                                                                </p>
                                                            )}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        ))
                    )}

                    <Card title={`Objectifs — ${achieved}/${program.objectives.length} atteints`}>
                        {program.objectives.length === 0 ? (
                            <p className="text-sm text-neutral-500">Aucun objectif défini.</p>
                        ) : (
                            <ul className="space-y-3">
                                {program.objectives.map((o) => (
                                    <li key={o.id}>
                                        <div className="mb-1 flex items-center justify-between">
                                            <span className="flex items-center gap-2 text-sm text-neutral-200">
                                                {o.achieved ? (
                                                    <CheckCircle2 size={16} className="text-lime-400" />
                                                ) : (
                                                    <Circle size={16} className="text-neutral-600" />
                                                )}
                                                {o.label}
                                            </span>
                                            <span className="text-xs tabular-nums text-neutral-500">
                                                {Math.round(o.progress * 100)}%
                                            </span>
                                        </div>
                                        <div className="h-1.5 overflow-hidden rounded bg-neutral-800">
                                            <div
                                                className={`h-full rounded ${o.achieved ? 'bg-lime-400' : 'bg-orange-400/70'}`}
                                                style={{ width: `${Math.round(o.progress * 100)}%` }}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-neutral-500">{o.detail}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                {/* Sidebar — generate + assigned runs */}
                <div className="space-y-6">
                    <Card title="Générer un cycle (IA)">
                        <p className="-mt-1 mb-4 text-sm text-neutral-400">
                            Un coach IA conçoit le prochain cycle jour par jour, d'après vos performances, le cycle précédent et
                            votre ressenti.
                        </p>
                        <form onSubmit={submitGenerate} className="space-y-3">
                            <div className="grid grid-cols-2 gap-3">
                                <label className="block">
                                    <span className="mb-1 block text-xs text-neutral-400">Début</span>
                                    <input
                                        type="date"
                                        value={generate.data.start_date}
                                        onChange={(e) => generate.setData('start_date', e.target.value)}
                                        className="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                    />
                                </label>
                                <label className="block">
                                    <span className="mb-1 block text-xs text-neutral-400">Semaines</span>
                                    <input
                                        type="number"
                                        min={1}
                                        max={6}
                                        value={generate.data.weeks}
                                        onChange={(e) => generate.setData('weeks', Number(e.target.value))}
                                        className="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                    />
                                </label>
                            </div>
                            <label className="block">
                                <span className="mb-1 block text-xs text-neutral-400">Ressenti</span>
                                <textarea
                                    value={generate.data.ressenti}
                                    onChange={(e) => generate.setData('ressenti', e.target.value)}
                                    rows={4}
                                    placeholder="Fatigue, douleurs, motivation, disponibilités…"
                                    className="w-full resize-none rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                />
                            </label>
                            {generate.errors.ressenti && (
                                <p className="text-xs text-red-400">{generate.errors.ressenti}</p>
                            )}
                            <button
                                type="submit"
                                disabled={generate.processing}
                                className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-lime-400 px-4 py-2.5 text-sm font-semibold text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                            >
                                <Sparkles size={16} />
                                {generate.processing ? 'Génération…' : 'Générer le cycle'}
                            </button>
                        </form>
                    </Card>

                    <Card title="Sorties assignées">
                        {available.length > 0 && (
                            <form onSubmit={submitAssign} className="mb-4 flex gap-2">
                                <select
                                    value={assign.data.activity_id}
                                    onChange={(e) => assign.setData('activity_id', e.target.value)}
                                    className="flex-1 rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                >
                                    {available.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="submit"
                                    disabled={assign.processing}
                                    className="cursor-pointer rounded-lg bg-lime-400 px-4 py-2 text-sm font-medium text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                                >
                                    Assigner
                                </button>
                            </form>
                        )}

                        {program.activities.length === 0 ? (
                            <p className="text-sm text-neutral-500">Aucune sortie assignée à ce programme.</p>
                        ) : (
                            <ul className="divide-y divide-neutral-800">
                                {program.activities.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between py-2.5">
                                        <Link
                                            href={`/activites/${a.id}`}
                                            className="text-sm text-neutral-200 hover:text-lime-300"
                                        >
                                            {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                        </Link>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs tabular-nums text-neutral-400">
                                                {formatDuration(a.movingSeconds)} · {formatPace(a.averagePaceSecondsPerKm)}
                                            </span>
                                            <button
                                                onClick={() => remove(a.id)}
                                                className="cursor-pointer text-neutral-500 hover:text-red-400"
                                                aria-label="Retirer"
                                            >
                                                <X size={16} />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}

ProgramDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
