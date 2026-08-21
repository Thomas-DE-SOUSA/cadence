import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { DragEvent, FormEvent, ReactNode } from 'react';
import { ArrowLeft, CheckCircle2, ChevronDown, Circle, Flag, GripVertical, Lock, RefreshCw, Sparkles, X } from 'lucide-react';
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

interface ActivityStats {
    id: string;
    occurredAt: string;
    distanceMeters: number;
    movingSeconds: number;
    averagePaceSecondsPerKm: number;
}

interface AssignedActivity extends ActivityStats {}

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
    activityId: string | null;
    actual: ActivityStats | null;
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
    phaseIndex: number;
    status: string;
    weeks: CycleWeek[];
}

interface RoadmapPhase {
    index: number;
    name: string;
    focus: string;
    weeks: number;
    status: 'done' | 'active' | 'locked';
    cycleId: string | null;
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
    roadmap: RoadmapPhase[];
    canGenerate: boolean;
    activeCycleId: string | null;
}

const SESSION_STYLES: Record<string, { label: string; dot: string; text: string }> = {
    EASY: { label: 'Footing', dot: 'bg-emerald-400', text: 'text-emerald-300' },
    LONG: { label: 'Sortie longue', dot: 'bg-sky-400', text: 'text-sky-300' },
    THRESHOLD: { label: 'Seuil', dot: 'bg-orange-400', text: 'text-orange-300' },
    INTERVALS: { label: 'Fractionné', dot: 'bg-red-400', text: 'text-red-300' },
    RECOVERY: { label: 'Récupération', dot: 'bg-teal-400', text: 'text-teal-300' },
    RACE_PACE: { label: 'Allure course', dot: 'bg-fuchsia-400', text: 'text-fuchsia-300' },
    RACE: { label: 'Course', dot: 'bg-lime-400', text: 'text-lime-300' },
    CROSS: { label: 'Cross-training', dot: 'bg-indigo-400', text: 'text-indigo-300' },
    REST: { label: 'Repos', dot: 'bg-neutral-600', text: 'text-neutral-500' },
};

function sessionStyle(type: string) {
    return SESSION_STYLES[type] ?? SESSION_STYLES.EASY;
}

function weekdayLabel(iso: string): string {
    // Parse as a local date so the weekday never shifts by timezone.
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, (m || 1) - 1, d || 1).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' });
}

const ROADMAP_BADGE: Record<RoadmapPhase['status'], { label: string; cls: string }> = {
    done: { label: 'Terminé', cls: 'border-lime-400/40 bg-lime-400/10 text-lime-300' },
    active: { label: 'En cours', cls: 'border-sky-400/40 bg-sky-400/10 text-sky-300' },
    locked: { label: 'À débloquer', cls: 'border-neutral-700 bg-neutral-800/40 text-neutral-500' },
};

export default function ProgramDetail({ program, available, cycles, roadmap, canGenerate, activeCycleId }: Props) {
    const ai = useForm({ start_date: '', weeks: 3, ressenti: '' });
    // Completed cycles start collapsed; the active one stays open.
    const [collapsed, setCollapsed] = useState<Set<string>>(
        () => new Set(cycles.filter((c) => c.status === 'completed').map((c) => c.id)),
    );
    const [selectedRun, setSelectedRun] = useState<string | null>(null);

    function toggleCycle(id: string) {
        setCollapsed((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function assignDay(cycleId: string, date: string, activityId: string | null) {
        router.post(
            `/programme/${program.id}/cycles/${cycleId}/jour`,
            { date, activity_id: activityId },
            { preserveScroll: true, onSuccess: () => setSelectedRun(null) },
        );
    }

    function onDropDay(e: DragEvent, cycleId: string, date: string) {
        e.preventDefault();
        const id = e.dataTransfer.getData('text/plain');
        if (id) assignDay(cycleId, date, id);
    }

    function completeCycle(cycleId: string) {
        router.post(`/programme/${program.id}/cycles/${cycleId}/terminer`, {}, { preserveScroll: true });
    }

    function submitGenerate(e: FormEvent) {
        e.preventDefault();
        ai.post(`/programme/${program.id}/generer-cycle`, { preserveScroll: true });
    }

    function submitRegenerate(e: FormEvent) {
        e.preventDefault();
        if (activeCycleId) ai.post(`/programme/${program.id}/cycles/${activeCycleId}/refaire`, { preserveScroll: true });
    }

    function removeAssigned(activityId: string) {
        router.post(`/programme/${program.id}/retirer`, { activity_id: activityId }, { preserveScroll: true });
    }

    const achieved = program.objectives.filter((o) => o.achieved).length;
    const aiError = (ai.errors as Record<string, string>).cycle ?? (ai.errors as Record<string, string>).ressenti;

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

            {roadmap.length > 0 && (
                <div className="mb-8">
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Feuille de route</p>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {roadmap.map((phase) => {
                            const badge = ROADMAP_BADGE[phase.status];
                            return (
                                <div
                                    key={phase.index}
                                    className={`rounded-xl border p-3 ${
                                        phase.status === 'active'
                                            ? 'border-sky-400/40 bg-sky-400/[0.06]'
                                            : 'border-neutral-800 bg-neutral-900/40'
                                    }`}
                                >
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <span className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                                            Cycle {phase.index + 1}
                                        </span>
                                        {phase.status === 'done' ? (
                                            <CheckCircle2 size={14} className="text-lime-400" />
                                        ) : phase.status === 'locked' ? (
                                            <Lock size={13} className="text-neutral-600" />
                                        ) : (
                                            <Circle size={13} className="text-sky-400" />
                                        )}
                                    </div>
                                    <p
                                        className={`text-sm font-semibold ${
                                            phase.status === 'locked' ? 'text-neutral-500' : 'text-neutral-100'
                                        }`}
                                    >
                                        {phase.name}
                                    </p>
                                    <p className="mt-0.5 line-clamp-2 text-xs text-neutral-500">{phase.focus}</p>
                                    <span
                                        className={`mt-2 inline-block rounded border px-1.5 py-0.5 text-[10px] font-medium ${badge.cls}`}
                                    >
                                        {phase.weeks} sem. · {badge.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Main column — the plan */}
                <div className="space-y-6 lg:col-span-2">
                    {cycles.length === 0 ? (
                        <Card title="Plan d'entraînement">
                            <p className="text-sm text-neutral-400">
                                Aucun cycle pour l'instant. Générez un premier cycle avec l'IA, ou créez un programme depuis un plan
                                tout fait.
                            </p>
                        </Card>
                    ) : (
                        cycles.map((cycle) => {
                            const isCollapsed = collapsed.has(cycle.id);
                            return (
                                <Card key={cycle.id}>
                                    <button
                                        onClick={() => toggleCycle(cycle.id)}
                                        className="flex w-full cursor-pointer items-start justify-between gap-3 text-left"
                                    >
                                        <div>
                                            <h2 className="flex flex-wrap items-center gap-2 text-sm font-semibold uppercase tracking-wide text-neutral-200">
                                                Cycle {cycle.phaseIndex + 1} · {cycle.name}
                                                {cycle.status === 'completed' && (
                                                    <span className="rounded border border-lime-400/40 bg-lime-400/10 px-1.5 py-0.5 text-[10px] font-medium normal-case text-lime-300">
                                                        Terminé
                                                    </span>
                                                )}
                                            </h2>
                                            <p className="mt-1 text-xs normal-case text-neutral-500">
                                                {cycle.focus} · {formatDate(cycle.startDate)} → {formatDate(cycle.endDate)}
                                            </p>
                                        </div>
                                        <ChevronDown
                                            size={18}
                                            className={`mt-0.5 shrink-0 text-neutral-500 transition-transform ${isCollapsed ? '' : 'rotate-180'}`}
                                        />
                                    </button>

                                    {!isCollapsed && (
                                        <div className="mt-5 space-y-5">
                                            {cycle.weeks.map((week) => (
                                                <div key={week.label}>
                                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                                        {week.label}
                                                    </p>
                                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                        {week.sessions.map((s) => {
                                                            const style = sessionStyle(s.type);
                                                            const placing = selectedRun !== null && !s.activityId;
                                                            return (
                                                                <div
                                                                    key={s.date}
                                                                    onDragOver={(e) => e.preventDefault()}
                                                                    onDrop={(e) => onDropDay(e, cycle.id, s.date)}
                                                                    onClick={() =>
                                                                        selectedRun && assignDay(cycle.id, s.date, selectedRun)
                                                                    }
                                                                    className={`rounded-lg border p-3 transition-colors ${
                                                                        placing
                                                                            ? 'cursor-pointer border-dashed border-lime-400/50 bg-lime-400/[0.04]'
                                                                            : 'border-neutral-800 bg-neutral-900/60'
                                                                    }`}
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
                                                                            Cible :{' '}
                                                                            {s.targetDistanceMeters
                                                                                ? `${formatKilometers(s.targetDistanceMeters)} km`
                                                                                : ''}
                                                                            {s.targetDistanceMeters && s.targetPaceSecondsPerKm ? ' · ' : ''}
                                                                            {s.targetPaceSecondsPerKm ? formatPace(s.targetPaceSecondsPerKm) : ''}
                                                                        </p>
                                                                    )}
                                                                    {s.actual ? (
                                                                        <div className="mt-2 flex items-center justify-between rounded-md border border-lime-400/30 bg-lime-400/[0.07] px-2 py-1.5">
                                                                            <span className="flex items-center gap-1.5 text-xs tabular-nums text-lime-300">
                                                                                <CheckCircle2 size={13} />
                                                                                {formatKilometers(s.actual.distanceMeters)} km ·{' '}
                                                                                {formatPace(s.actual.averagePaceSecondsPerKm)}
                                                                            </span>
                                                                            <button
                                                                                onClick={(e) => {
                                                                                    e.stopPropagation();
                                                                                    assignDay(cycle.id, s.date, null);
                                                                                }}
                                                                                className="cursor-pointer text-neutral-500 hover:text-red-400"
                                                                                aria-label="Retirer la sortie"
                                                                            >
                                                                                <X size={13} />
                                                                            </button>
                                                                        </div>
                                                                    ) : (
                                                                        placing && (
                                                                            <p className="mt-2 text-[11px] italic text-lime-400/70">
                                                                                Placer la sortie ici
                                                                            </p>
                                                                        )
                                                                    )}
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            ))}

                                            {cycle.id === activeCycleId && (
                                                <button
                                                    onClick={() => completeCycle(cycle.id)}
                                                    className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-lime-400/50 px-4 py-2 text-sm font-semibold text-lime-300 transition-colors hover:bg-lime-400/10"
                                                >
                                                    <Flag size={15} /> Terminer ce cycle
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </Card>
                            );
                        })
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

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Runs to place */}
                    <Card title="Sorties à placer">
                        <p className="-mt-1 mb-3 text-xs text-neutral-400">
                            Glissez une sortie sur un jour du plan — ou touchez-la puis touchez le jour (mobile).
                        </p>
                        {selectedRun && (
                            <div className="mb-3 flex items-center justify-between rounded-lg border border-lime-400/40 bg-lime-400/10 px-3 py-2 text-xs text-lime-200">
                                Touchez un jour pour y placer la sortie.
                                <button
                                    onClick={() => setSelectedRun(null)}
                                    className="cursor-pointer text-lime-300 hover:text-lime-100"
                                >
                                    Annuler
                                </button>
                            </div>
                        )}
                        {available.length === 0 ? (
                            <p className="text-sm text-neutral-500">Toutes vos sorties sont placées.</p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {available.map((a) => (
                                    <button
                                        key={a.id}
                                        draggable
                                        onDragStart={(e) => e.dataTransfer.setData('text/plain', a.id)}
                                        onClick={() => setSelectedRun((cur) => (cur === a.id ? null : a.id))}
                                        className={`flex cursor-grab items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors active:cursor-grabbing ${
                                            selectedRun === a.id
                                                ? 'border-lime-400/60 bg-lime-400/10 text-lime-200'
                                                : 'border-neutral-800 bg-neutral-900 text-neutral-200 hover:border-neutral-700'
                                        }`}
                                    >
                                        <GripVertical size={14} className="shrink-0 text-neutral-600" />
                                        {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                    </button>
                                ))}
                            </div>
                        )}
                    </Card>

                    {/* AI panel — refine current cycle or generate next */}
                    <Card title={activeCycleId ? 'Refaire le cycle courant (IA)' : 'Générer le prochain cycle (IA)'}>
                        {activeCycleId ? (
                            <>
                                <p className="-mt-1 mb-4 text-sm text-neutral-400">
                                    L'IA réécrit le cycle courant à partir de vos sorties récentes et de votre ressenti (les
                                    sorties déjà placées sont conservées).
                                </p>
                                {aiError && <p className="mb-3 text-xs text-red-400">{aiError}</p>}
                                <form onSubmit={submitRegenerate} className="space-y-3">
                                    <textarea
                                        value={ai.data.ressenti}
                                        onChange={(e) => ai.setData('ressenti', e.target.value)}
                                        rows={4}
                                        placeholder="Ressenti, fatigue, contraintes de la semaine…"
                                        className="w-full resize-none rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                    />
                                    <button
                                        type="submit"
                                        disabled={ai.processing}
                                        className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-lime-400/50 px-4 py-2.5 text-sm font-semibold text-lime-300 transition-colors hover:bg-lime-400/10 disabled:opacity-50"
                                    >
                                        <RefreshCw size={15} />
                                        {ai.processing ? 'Réécriture…' : 'Refaire ce cycle'}
                                    </button>
                                </form>
                            </>
                        ) : canGenerate ? (
                            <>
                                <p className="-mt-1 mb-4 text-sm text-neutral-400">
                                    L'IA adapte le prochain cycle du plan à vos performances et à votre ressenti.
                                </p>
                                {aiError && <p className="mb-3 text-xs text-red-400">{aiError}</p>}
                                <form onSubmit={submitGenerate} className="space-y-3">
                                    <label className="block">
                                        <span className="mb-1 block text-xs text-neutral-400">Semaines</span>
                                        <input
                                            type="number"
                                            min={1}
                                            max={6}
                                            value={ai.data.weeks}
                                            onChange={(e) => ai.setData('weeks', Number(e.target.value))}
                                            className="w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                        />
                                    </label>
                                    <textarea
                                        value={ai.data.ressenti}
                                        onChange={(e) => ai.setData('ressenti', e.target.value)}
                                        rows={4}
                                        placeholder="Fatigue, douleurs, motivation, disponibilités…"
                                        className="w-full resize-none rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                                    />
                                    <button
                                        type="submit"
                                        disabled={ai.processing}
                                        className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-lime-400 px-4 py-2.5 text-sm font-semibold text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                                    >
                                        <Sparkles size={16} />
                                        {ai.processing ? 'Génération…' : 'Générer le cycle'}
                                    </button>
                                </form>
                            </>
                        ) : (
                            <div className="flex items-start gap-3 text-sm text-neutral-400">
                                <Lock size={16} className="mt-0.5 shrink-0 text-neutral-500" />
                                <p>Feuille de route terminée : bravo, tous les cycles sont bouclés.</p>
                            </div>
                        )}
                    </Card>

                    {program.activities.length > 0 && (
                        <Card title="Sorties du programme">
                            <ul className="divide-y divide-neutral-800">
                                {program.activities.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between py-2.5">
                                        <Link href={`/activites/${a.id}`} className="text-sm text-neutral-200 hover:text-lime-300">
                                            {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                        </Link>
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs tabular-nums text-neutral-400">
                                                {formatDuration(a.movingSeconds)} · {formatPace(a.averagePaceSecondsPerKm)}
                                            </span>
                                            <button
                                                onClick={() => removeAssigned(a.id)}
                                                className="cursor-pointer text-neutral-500 hover:text-red-400"
                                                aria-label="Retirer"
                                            >
                                                <X size={16} />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

ProgramDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
