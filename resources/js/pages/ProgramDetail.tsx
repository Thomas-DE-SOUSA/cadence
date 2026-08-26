import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ComponentType, DragEvent, FormEvent, ReactNode } from 'react';
import {
    Activity,
    ArrowLeft,
    Bike,
    CheckCircle2,
    ChevronDown,
    Circle,
    Flag,
    Footprints,
    Gauge,
    Layers,
    Leaf,
    ListChecks,
    Lock,
    MessageSquare,
    Moon,
    RefreshCw,
    Route,
    Sparkles,
    Target,
    Timer,
    Trophy,
    X,
    Zap,
} from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { Modal } from '@/components/Modal';
import { SessionDetail } from '@/features/coach/SessionDetail';
import { CoachThread } from '@/features/coach/CoachThread';
import { useCountUp } from '@/lib/useCountUp';
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

interface SessionStep {
    label: string;
    repeat: number;
    distanceMeters: number | null;
    durationSeconds: number | null;
    paceSecondsPerKm: number | null;
    recoverySeconds: number | null;
    note: string;
}

interface PlannedSession {
    date: string;
    suggestedDate: string | null;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetDurationSeconds: number | null;
    targetPaceSecondsPerKm: number | null;
    steps: SessionStep[];
    actual: ActivityStats | null;
    manual: boolean;
}

interface CycleWeek {
    label: string;
    startDate: string;
    done: number;
    total: number;
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

interface Athlete {
    vdot: number;
    reference: { distanceMeters: number; seconds: number; date: string };
    paces: { easy: number; marathon: number; threshold: number; interval: number; repetition: number };
    recentVolumeMeters: number;
    longestRunMeters: number;
    recentRunCount: number;
    target: { vdot: number; distanceMeters: number; seconds: number } | null;
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
    athlete: Athlete | null;
}

interface SessionStyle {
    label: string;
    icon: ComponentType<{ size?: number; className?: string }>;
    badge: string;
    border: string;
}

const SESSION_STYLES: Record<string, SessionStyle> = {
    EASY: { label: 'Footing', icon: Footprints, badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', border: 'hover:border-emerald-300' },
    LONG: { label: 'Sortie longue', icon: Route, badge: 'bg-sky-50 text-sky-700 border-sky-200', border: 'hover:border-sky-300' },
    THRESHOLD: { label: 'Seuil', icon: Gauge, badge: 'bg-orange-50 text-orange-700 border-orange-200', border: 'hover:border-orange-300' },
    INTERVALS: { label: 'Fractionné', icon: Zap, badge: 'bg-red-50 text-red-700 border-red-200', border: 'hover:border-red-300' },
    RECOVERY: { label: 'Récupération', icon: Leaf, badge: 'bg-teal-50 text-teal-700 border-teal-200', border: 'hover:border-teal-300' },
    RACE_PACE: { label: 'Allure course', icon: Timer, badge: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200', border: 'hover:border-fuchsia-300' },
    RACE: { label: 'Course', icon: Trophy, badge: 'bg-brand-50 text-brand-700 border-brand-200', border: 'hover:border-brand-300' },
    CROSS: { label: 'Cross-training', icon: Bike, badge: 'bg-indigo-50 text-indigo-700 border-indigo-200', border: 'hover:border-indigo-300' },
    REST: { label: 'Repos', icon: Moon, badge: 'bg-neutral-100 text-neutral-500 border-neutral-200', border: 'hover:border-neutral-300' },
};

const PACE_ZONES: { key: keyof Athlete['paces']; label: string; hint: string }[] = [
    { key: 'easy', label: 'Facile (E)', hint: 'footing, récup' },
    { key: 'marathon', label: 'Marathon (M)', hint: 'endurance active' },
    { key: 'threshold', label: 'Seuil (T)', hint: 'tempo, ~1 h effort' },
    { key: 'interval', label: 'Intervalle (I)', hint: 'VMA, VO₂max' },
    { key: 'repetition', label: 'Répétition (R)', hint: 'vitesse, économie' },
];

const ROADMAP_BADGE: Record<RoadmapPhase['status'], { label: string; cls: string }> = {
    done: { label: 'Terminé', cls: 'border-emerald-300 bg-emerald-50 text-emerald-700' },
    active: { label: 'En cours', cls: 'border-brand-300 bg-brand-50 text-brand-700' },
    locked: { label: 'À débloquer', cls: 'border-neutral-200 bg-neutral-100 text-neutral-400' },
};

function sessionStyle(type: string) {
    return SESSION_STYLES[type] ?? SESSION_STYLES.EASY;
}

function weekdayLabel(iso: string): string {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, (m || 1) - 1, d || 1).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' });
}

function Stat({
    value,
    label,
    icon: Icon,
    tint,
}: {
    value: ReactNode;
    label: string;
    icon: ComponentType<{ size?: number; className?: string }>;
    tint: string;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <span className={`flex h-9 w-9 items-center justify-center rounded-xl ${tint}`}>
                <Icon size={17} />
            </span>
            <div>
                <p className="text-xl font-bold leading-none tabular-nums text-neutral-900">{value}</p>
                <p className="mt-1 text-[11px] uppercase tracking-wide text-neutral-400">{label}</p>
            </div>
        </div>
    );
}

function CountUp({ value, decimals = 0 }: { value: number; decimals?: number }) {
    return <>{useCountUp(value).toFixed(decimals)}</>;
}

const SECTION_TABS = [
    { key: 'cycle' as const, label: 'Cycle', icon: Layers },
    { key: 'objectifs' as const, label: 'Objectifs', icon: Target },
    { key: 'profil' as const, label: 'Profil', icon: Gauge },
];

export default function ProgramDetail({ program, available, cycles, roadmap, canGenerate, activeCycleId, athlete }: Props) {
    const ai = useForm({ start_date: '', weeks: 3, ressenti: '' });
    const [collapsed, setCollapsed] = useState<Set<string>>(
        () => new Set(cycles.filter((c) => c.status === 'completed').map((c) => c.id)),
    );
    const [selectedRun, setSelectedRun] = useState<string | null>(null);
    const [openDay, setOpenDay] = useState<{ cycleId: string; date: string } | null>(null);
    const [aiOpen, setAiOpen] = useState(false);
    // On mobile the session detail and the coach share one panel via a toggle.
    const [mobileTab, setMobileTab] = useState<'details' | 'coach'>('details');
    const [moveDate, setMoveDate] = useState('');
    const [section, setSection] = useState<'cycle' | 'objectifs' | 'profil'>('cycle');

    useEffect(() => {
        if (openDay) {
            setMobileTab('details');
            setMoveDate('');
        }
    }, [openDay]);

    const openSession = openDay
        ? cycles.find((c) => c.id === openDay.cycleId)?.weeks.flatMap((w) => w.sessions).find((s) => s.date === openDay.date) ?? null
        : null;

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

    function rescheduleSession(cycleId: string, fromDate: string, toDate: string) {
        if (!toDate || toDate === fromDate) return;
        router.post(
            `/programme/${program.id}/cycles/${cycleId}/jour/deplacer`,
            { from: fromDate, to: toDate },
            { preserveScroll: true, onSuccess: () => setOpenDay(null) },
        );
    }

    function completeCycle(cycleId: string) {
        router.post(`/programme/${program.id}/cycles/${cycleId}/terminer`, {}, { preserveScroll: true });
    }

    function submitGenerate(e: FormEvent) {
        e.preventDefault();
        ai.post(`/programme/${program.id}/generer-cycle`, { preserveScroll: true, onSuccess: () => setAiOpen(false) });
    }

    function submitRegenerate(e: FormEvent) {
        e.preventDefault();
        if (activeCycleId)
            ai.post(`/programme/${program.id}/cycles/${activeCycleId}/refaire`, { preserveScroll: true, onSuccess: () => setAiOpen(false) });
    }

    const achieved = program.objectives.filter((o) => o.achieved).length;
    const totalMeters = program.activities.reduce((sum, a) => sum + a.distanceMeters, 0);
    const aiError = (ai.errors as Record<string, string>).cycle ?? (ai.errors as Record<string, string>).ressenti;
    const aiAction = activeCycleId ? 'refaire' : canGenerate ? 'generate' : null;

    const inputClass =
        'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100';

    return (
        <>
            <Head title={program.name} />
            <Link
                href="/programme"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900"
            >
                <ArrowLeft size={16} /> Programmes
            </Link>

            {/* Header */}
            <div className="animate-fade-up mb-4 overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/40 p-5 shadow-sm shadow-neutral-200/60">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-neutral-900">{program.name}</h1>
                        <p className="mt-1 text-sm text-neutral-500">
                            {program.goal}
                            {program.targetRaceName ? ` · ${program.targetRaceName}` : ''}
                            {program.targetRaceDate ? ` — ${formatDate(program.targetRaceDate)}` : ''}
                        </p>
                    </div>
                    {aiAction && (
                        <button
                            onClick={() => setAiOpen(true)}
                            className={`inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition-all hover:-translate-y-0.5 active:translate-y-0 ${
                                aiAction === 'generate'
                                    ? 'bg-brand-500 text-white shadow-brand-500/30 hover:bg-brand-600 hover:shadow-brand-500/40'
                                    : 'border border-brand-300 text-brand-600 hover:bg-brand-50'
                            }`}
                        >
                            {aiAction === 'generate' ? <Sparkles size={16} /> : <RefreshCw size={15} />}
                            {aiAction === 'generate' ? 'Générer le prochain cycle' : 'Refaire ce cycle'}
                        </button>
                    )}
                </div>
                <div className="mt-4 flex flex-wrap gap-x-8 gap-y-4 border-t border-neutral-200/70 pt-4">
                    <Stat value={program.activities.length} label="Courses" icon={Activity} tint="bg-sky-100 text-sky-600" />
                    <Stat value={formatKilometers(totalMeters)} label="Kilomètres" icon={Route} tint="bg-emerald-100 text-emerald-600" />
                    <Stat value={cycles.length} label="Cycles" icon={Layers} tint="bg-brand-100 text-brand-600" />
                    <Stat value={`${achieved}/${program.objectives.length}`} label="Objectifs" icon={Target} tint="bg-violet-100 text-violet-600" />
                    {athlete && <Stat value={<CountUp value={athlete.vdot} />} label="VDOT" icon={Gauge} tint="bg-brand-100 text-brand-600" />}
                </div>
            </div>

            {/* Section nav — underline tabs, flush under the header */}
            <div className="mb-5 flex gap-1 border-b border-neutral-200 sm:gap-2">
                {SECTION_TABS.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setSection(t.key)}
                        className={`-mb-px flex items-center gap-1.5 border-b-2 px-3 pb-2.5 pt-1 text-sm font-semibold transition-colors ${
                            section === t.key
                                ? 'border-brand-500 text-brand-600'
                                : 'border-transparent text-neutral-400 hover:text-neutral-700'
                        }`}
                    >
                        <t.icon size={16} /> {t.label}
                    </button>
                ))}
            </div>

            {section === 'profil' && (
                <div className="animate-fade-up space-y-5">
                    {athlete && (
                        <Card title="Profil coureur (estimé)">
                            <div className="flex items-end justify-between">
                                <div>
                                    <p className="text-4xl font-bold tabular-nums leading-none text-brand-500">
                                        <CountUp value={athlete.vdot} />
                                    </p>
                                    <p className="mt-1.5 text-xs text-neutral-400">
                                        VDOT · d'après {formatKilometers(athlete.reference.distanceMeters)} km en{' '}
                                        {formatDuration(athlete.reference.seconds)}
                                    </p>
                                </div>
                                {athlete.target && (
                                    <div className="text-right">
                                        <p className="text-sm font-semibold tabular-nums text-neutral-700">
                                            objectif {athlete.target.vdot.toFixed(0)}
                                        </p>
                                        <p className="text-xs text-neutral-400">
                                            écart{' '}
                                            <span className={athlete.target.vdot > athlete.vdot ? 'text-orange-600' : 'text-emerald-600'}>
                                                {athlete.target.vdot > athlete.vdot ? '+' : ''}
                                                {(athlete.target.vdot - athlete.vdot).toFixed(1)}
                                            </span>
                                        </p>
                                    </div>
                                )}
                            </div>
                            <div className="mt-4 space-y-1.5 border-t border-neutral-100 pt-4">
                                {PACE_ZONES.map((zone) => (
                                    <div key={zone.key} className="flex items-baseline justify-between gap-2 text-sm">
                                        <span className="text-neutral-700">{zone.label}</span>
                                        <span className="flex-1 border-b border-dashed border-neutral-200" />
                                        <span className="tabular-nums font-medium text-neutral-900">
                                            {formatPace(athlete.paces[zone.key])}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-4 text-xs text-neutral-400">
                                {athlete.recentRunCount} sortie{athlete.recentRunCount > 1 ? 's' : ''} récentes ·{' '}
                                {formatKilometers(athlete.recentVolumeMeters)} km · plus longue{' '}
                                {formatKilometers(athlete.longestRunMeters)} km
                            </p>
                        </Card>
                    )}
                    {!athlete && (
                        <p className="text-sm text-neutral-400">Profil indisponible — enregistre quelques sorties pour l'estimer.</p>
                    )}
                </div>
            )}

            {section === 'objectifs' && (
                <div className="animate-fade-up space-y-5">
                    <Card title={`Objectifs — ${achieved}/${program.objectives.length}`}>
                        {program.objectives.length === 0 ? (
                            <p className="text-sm text-neutral-400">Aucun objectif défini.</p>
                        ) : (
                            <ul className="space-y-3.5">
                                {program.objectives.map((o) => (
                                    <li key={o.id}>
                                        <div className="mb-1 flex items-center justify-between">
                                            <span className="flex items-center gap-2 text-sm text-neutral-800">
                                                {o.achieved ? (
                                                    <CheckCircle2 size={16} className="text-emerald-500" />
                                                ) : (
                                                    <Circle size={16} className="text-neutral-300" />
                                                )}
                                                {o.label}
                                            </span>
                                            <span className="text-xs tabular-nums text-neutral-400">{Math.round(o.progress * 100)}%</span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-neutral-100">
                                            <div
                                                className={`animate-bar h-full rounded-full ${o.achieved ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-brand-400 to-brand-500'}`}
                                                style={{ width: `${Math.round(o.progress * 100)}%` }}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-neutral-400">{o.detail}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            )}

            {section === 'cycle' && (
                <div className="animate-fade-up space-y-5">
                    {roadmap.length > 0 && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {roadmap.map((phase) => {
                                const badge = ROADMAP_BADGE[phase.status];
                                return (
                                    <div
                                        key={phase.index}
                                        className={`rounded-xl border bg-white p-3 shadow-sm shadow-neutral-200/50 ${
                                            phase.status === 'active' ? 'border-brand-300' : 'border-neutral-200'
                                        }`}
                                    >
                                        <div className="mb-1.5 flex items-center justify-between">
                                            <span className="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                                                Cycle {phase.index + 1}
                                            </span>
                                            {phase.status === 'done' ? (
                                                <CheckCircle2 size={14} className="text-emerald-500" />
                                            ) : phase.status === 'locked' ? (
                                                <Lock size={13} className="text-neutral-300" />
                                            ) : (
                                                <Circle size={13} className="text-brand-500" />
                                            )}
                                        </div>
                                        <p className={`text-sm font-semibold ${phase.status === 'locked' ? 'text-neutral-400' : 'text-neutral-900'}`}>
                                            {phase.name}
                                        </p>
                                        <p className="mt-0.5 line-clamp-2 text-xs text-neutral-400">{phase.focus}</p>
                                        <span className={`mt-2 inline-block rounded border px-1.5 py-0.5 text-[10px] font-medium ${badge.cls}`}>
                                            {phase.weeks} sem. · {badge.label}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {cycles.length === 0 ? (
                        <Card title="Plan d'entraînement">
                            <p className="text-sm text-neutral-500">
                                Aucun cycle pour l'instant. Générez un premier cycle avec l'IA, ou créez un programme depuis un plan
                                tout fait.
                            </p>
                        </Card>
                    ) : (
                        cycles.map((cycle) => {
                            const isCollapsed = collapsed.has(cycle.id);
                            const isDone = cycle.status === 'completed';
                            return (
                                <Card key={cycle.id} className={isDone ? 'border-emerald-300 bg-emerald-50' : undefined}>
                                    <button
                                        onClick={() => toggleCycle(cycle.id)}
                                        className="flex w-full cursor-pointer items-start justify-between gap-3 text-left"
                                    >
                                        <div>
                                            <h2 className="flex flex-wrap items-center gap-2 text-sm font-bold uppercase tracking-wide text-neutral-900">
                                                Cycle {cycle.phaseIndex + 1} · {cycle.name}
                                                {cycle.status === 'completed' && (
                                                    <span className="rounded border border-emerald-300 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium normal-case text-emerald-700">
                                                        Terminé
                                                    </span>
                                                )}
                                            </h2>
                                            <p className="mt-1 text-xs normal-case text-neutral-400">
                                                {cycle.focus} · {formatDate(cycle.startDate)} → {formatDate(cycle.endDate)}
                                            </p>
                                        </div>
                                        <ChevronDown
                                            size={18}
                                            className={`mt-0.5 shrink-0 text-neutral-400 transition-transform ${isCollapsed ? '' : 'rotate-180'}`}
                                        />
                                    </button>

                                    {!isCollapsed && (
                                        <div className="mt-5 space-y-5">
                                            {cycle.weeks.map((week) => {
                                                const complete = week.total > 0 && week.done >= week.total;
                                                return (
                                                <div key={week.label}>
                                                    <div className="mb-2 flex items-center justify-between gap-2">
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-neutral-400">
                                                            {week.label}
                                                        </p>
                                                        <span
                                                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums ${
                                                                complete ? 'bg-emerald-50 text-emerald-600' : 'bg-neutral-100 text-neutral-500'
                                                            }`}
                                                        >
                                                            {complete && <CheckCircle2 size={12} />}
                                                            {week.done}/{week.total} séance{week.total > 1 ? 's' : ''}
                                                        </span>
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                                        {week.sessions
                                                            .filter((s) => s.type !== 'REST' || s.suggestedDate)
                                                            .map((s, i) => {
                                                            const style = sessionStyle(s.type);
                                                            const StyleIcon = style.icon;
                                                            const placing = selectedRun !== null && !s.actual;
                                                            const ran = s.actual !== null;
                                                            const rest = s.type === 'REST';
                                                            const done = ran || rest;
                                                            return (
                                                                <div
                                                                    key={s.date}
                                                                    onDragOver={(e) => e.preventDefault()}
                                                                    onDrop={(e) => onDropDay(e, cycle.id, s.date)}
                                                                    onClick={() =>
                                                                        selectedRun
                                                                            ? assignDay(cycle.id, s.date, selectedRun)
                                                                            : setOpenDay({ cycleId: cycle.id, date: s.date })
                                                                    }
                                                                    className={`flex cursor-pointer flex-col rounded-xl border p-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:shadow-neutral-200/70 ${
                                                                        placing
                                                                            ? 'border-dashed border-brand-400 bg-brand-50/50'
                                                                            : done
                                                                              ? 'border-emerald-300 bg-emerald-50/40 ring-1 ring-emerald-200/70'
                                                                              : `border-neutral-200 bg-white ${style.border}`
                                                                    }`}
                                                                >
                                                                    <div className="mb-1.5 flex items-center justify-between">
                                                                        <span className="flex items-center gap-1 text-xs font-semibold text-neutral-500">
                                                                            {s.suggestedDate ? weekdayLabel(s.suggestedDate) : `Séance ${i + 1}`}
                                                                            {ran ? (
                                                                                <CheckCircle2 size={13} className="text-emerald-500" />
                                                                            ) : rest ? (
                                                                                <Moon size={12} className="text-emerald-500" />
                                                                            ) : null}
                                                                        </span>
                                                                        <span
                                                                            className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                                                                                done ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : style.badge
                                                                            }`}
                                                                        >
                                                                            <StyleIcon size={11} />
                                                                            {style.label}
                                                                        </span>
                                                                    </div>
                                                                    <p className="text-sm font-semibold text-neutral-900">{s.title}</p>
                                                                    <p className="mt-0.5 text-xs leading-relaxed text-neutral-500">
                                                                        {s.description}
                                                                    </p>
                                                                    <div className="mt-auto pt-2">
                                                                        {(s.targetDistanceMeters || s.targetPaceSecondsPerKm) && (
                                                                            <p className="text-xs tabular-nums text-neutral-400">
                                                                                Cible :{' '}
                                                                                {s.targetDistanceMeters
                                                                                    ? `${formatKilometers(s.targetDistanceMeters)} km`
                                                                                    : ''}
                                                                                {s.targetDistanceMeters && s.targetPaceSecondsPerKm ? ' · ' : ''}
                                                                                {s.targetPaceSecondsPerKm ? formatPace(s.targetPaceSecondsPerKm) : ''}
                                                                            </p>
                                                                        )}
                                                                        {s.actual ? (
                                                                            <div className="mt-2 flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1.5">
                                                                                <Link
                                                                                    href={`/activites/${s.actual.id}`}
                                                                                    onClick={(e) => e.stopPropagation()}
                                                                                    className="flex items-center gap-1.5 text-xs tabular-nums text-emerald-700 hover:text-emerald-800"
                                                                                >
                                                                                    <CheckCircle2 size={13} />
                                                                                    {formatKilometers(s.actual.distanceMeters)} km ·{' '}
                                                                                    {formatPace(s.actual.averagePaceSecondsPerKm)}
                                                                                </Link>
                                                                                {s.manual && (
                                                                                    <button
                                                                                        onClick={(e) => {
                                                                                            e.stopPropagation();
                                                                                            assignDay(cycle.id, s.date, null);
                                                                                        }}
                                                                                        className="cursor-pointer text-neutral-400 hover:text-red-500"
                                                                                        aria-label="Détacher la sortie"
                                                                                    >
                                                                                        <X size={13} />
                                                                                    </button>
                                                                                )}
                                                                            </div>
                                                                        ) : (
                                                                            placing && (
                                                                                <p className="mt-2 text-[11px] italic text-brand-500">
                                                                                    Placer la sortie ici
                                                                                </p>
                                                                            )
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                                );
                                            })}

                                            {cycle.id === activeCycleId && (
                                                <button
                                                    onClick={() => completeCycle(cycle.id)}
                                                    className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50"
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
                </div>
            )}

            {/* Day detail + coach */}
            <Modal
                open={openSession !== null}
                onClose={() => setOpenDay(null)}
                size="full"
                title={openSession ? (openSession.suggestedDate ? `${weekdayLabel(openSession.suggestedDate)} · ${openSession.title}` : openSession.title) : ''}
            >
                {openSession && openDay && (
                    <div className="flex h-full flex-col">
                        {/* Mobile toggle between the session detail and the coach */}
                        <div className="flex shrink-0 gap-1 border-b border-neutral-200 bg-white p-2 md:hidden">
                            <button
                                onClick={() => setMobileTab('details')}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-semibold transition-colors ${
                                    mobileTab === 'details' ? 'bg-brand-500 text-white' : 'text-neutral-500 hover:bg-neutral-100'
                                }`}
                            >
                                <ListChecks size={16} /> Séance
                            </button>
                            <button
                                onClick={() => setMobileTab('coach')}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-sm font-semibold transition-colors ${
                                    mobileTab === 'coach' ? 'bg-brand-500 text-white' : 'text-neutral-500 hover:bg-neutral-100'
                                }`}
                            >
                                <MessageSquare size={16} /> Coach
                            </button>
                        </div>

                        <div className="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-2">
                            <div
                                className={`min-h-0 overflow-y-auto border-neutral-200 p-5 md:block md:border-r ${
                                    mobileTab === 'details' ? 'block' : 'hidden'
                                }`}
                            >
                                <div className="mb-4 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                                    <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                        Jour prévu {openSession.suggestedDate ? '' : '(libre)'}
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="date"
                                            value={moveDate || (openSession.suggestedDate ?? openSession.date)}
                                            onChange={(e) => setMoveDate(e.target.value)}
                                            className="min-w-0 flex-1 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => rescheduleSession(openDay.cycleId, openSession.date, moveDate || (openSession.suggestedDate ?? openSession.date))}
                                            disabled={!moveDate || moveDate === (openSession.suggestedDate ?? openSession.date)}
                                            className="shrink-0 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-600 disabled:opacity-40"
                                        >
                                            Déplacer
                                        </button>
                                    </div>
                                    <p className="mt-1.5 text-[11px] text-neutral-400">
                                        Indicatif : tu peux courir n'importe quel jour de la semaine, ça comptera quand même.
                                    </p>
                                </div>
                                <SessionDetail session={openSession} paces={athlete?.paces ?? null} />
                            </div>
                            <div
                                className={`min-h-0 flex-col bg-neutral-50 p-5 md:flex ${mobileTab === 'coach' ? 'flex' : 'hidden'}`}
                            >
                                <CoachThread
                                    key={`${openDay.cycleId}-${openDay.date}`}
                                    programId={program.id}
                                    cycleId={openDay.cycleId}
                                    date={openDay.date}
                                    onApplied={() => router.reload({ only: ['cycles'] })}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </Modal>

            {/* AI action */}
            <Modal
                open={aiOpen}
                onClose={() => setAiOpen(false)}
                title={aiAction === 'generate' ? 'Générer le prochain cycle (IA)' : 'Refaire le cycle courant (IA)'}
            >
                {aiAction === 'generate' ? (
                    <>
                        <p className="mb-4 text-sm text-neutral-500">
                            L'IA adapte le prochain cycle du plan à tes performances, tes allures et ton ressenti.
                        </p>
                        {aiError && <p className="mb-3 text-xs text-red-600">{aiError}</p>}
                        <form onSubmit={submitGenerate} className="space-y-3">
                            <label className="block">
                                <span className="mb-1 block text-xs text-neutral-500">Semaines</span>
                                <input
                                    type="number"
                                    min={1}
                                    max={6}
                                    value={ai.data.weeks}
                                    onChange={(e) => ai.setData('weeks', Number(e.target.value))}
                                    className={inputClass}
                                />
                            </label>
                            <textarea
                                value={ai.data.ressenti}
                                onChange={(e) => ai.setData('ressenti', e.target.value)}
                                rows={4}
                                placeholder="Fatigue, douleurs, motivation, disponibilités…"
                                className={`${inputClass} resize-none`}
                            />
                            <button
                                type="submit"
                                disabled={ai.processing}
                                className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                            >
                                <Sparkles size={16} />
                                {ai.processing ? 'Génération…' : 'Générer le cycle'}
                            </button>
                        </form>
                    </>
                ) : (
                    <>
                        <p className="mb-4 text-sm text-neutral-500">
                            L'IA réécrit le cycle courant à partir de tes sorties récentes, tes allures et ton ressenti (les sorties
                            déjà placées sont conservées).
                        </p>
                        {aiError && <p className="mb-3 text-xs text-red-600">{aiError}</p>}
                        <form onSubmit={submitRegenerate} className="space-y-3">
                            <textarea
                                value={ai.data.ressenti}
                                onChange={(e) => ai.setData('ressenti', e.target.value)}
                                rows={4}
                                placeholder="Ressenti, fatigue, contraintes de la semaine…"
                                className={`${inputClass} resize-none`}
                            />
                            <button
                                type="submit"
                                disabled={ai.processing}
                                className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-brand-300 px-4 py-2.5 text-sm font-semibold text-brand-600 transition-colors hover:bg-brand-50 disabled:opacity-50"
                            >
                                <RefreshCw size={15} />
                                {ai.processing ? 'Réécriture…' : 'Refaire ce cycle'}
                            </button>
                        </form>
                    </>
                )}
            </Modal>
        </>
    );
}

ProgramDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
