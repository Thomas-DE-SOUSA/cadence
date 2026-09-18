import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Check, ChevronDown, ChevronRight, ChevronUp, Copy, History, Link2, Plus, RotateCcw, Search, Trash2, X } from 'lucide-react';

export interface CatalogItem {
    id: string;
    name: string;
    muscle: string;
    muscleLabel: string;
    equipment: string;
    equipmentLabel: string;
    isCustom: boolean;
}
export interface Option {
    value: string;
    label: string;
}
export interface SetRow {
    weight_kg: number | null;
    reps: number | null;
    rpe: number | null;
    duration_seconds: number | null;
    is_warmup: boolean;
    done: boolean;
}
export interface Item {
    exercise_id: string;
    name: string;
    muscleLabel?: string;
    equipmentLabel?: string;
    per_side: boolean;
    superset_group: number | null;
    note: string;
    sets: SetRow[];
    /** UI-only: card folded to its summary. Not persisted meaningfully. */
    collapsed?: boolean;
}

export const emptySet = (): SetRow => ({ weight_kg: null, reps: null, rpe: null, duration_seconds: null, is_warmup: false, done: true });

/** "100 kg × 8" for the heaviest working set, or "12 reps" when bodyweight. */
export function lastTopSet(sets: SetRow[]): string | null {
    const working = sets.filter((s) => !s.is_warmup);
    if (working.length === 0) return null;
    const top = working.reduce((a, b) => ((b.weight_kg ?? 0) > (a.weight_kg ?? 0) ? b : a));
    if (top.weight_kg && top.reps) return `${top.weight_kg} kg × ${top.reps}`;
    if (top.reps) return `${top.reps} reps`;
    if (top.duration_seconds) return `${top.duration_seconds}s`;
    return null;
}

/** Compact "35kg × 15" for the PRÉCÉDENT column (a single prior set), or "—". */
function prevLabel(p: SetRow | undefined): string {
    if (!p) return '—';
    if (p.weight_kg != null && p.reps != null) return `${p.weight_kg}kg × ${p.reps}`;
    if (p.reps != null) return `${p.reps} reps`;
    if (p.duration_seconds != null) return `${p.duration_seconds}s`;
    return '—';
}

export function numOrNull(v: string): number | null {
    if (v.trim() === '') return null;
    const n = Number(v.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

/** Superset group number → a stable human label (1 → A, 2 → B, …). */
export const supersetLabel = (group: number): string => String.fromCharCode(64 + group);

/**
 * Decimal text field bound to a number model. Keeps the raw text so an
 * in-progress "22," or "22." isn't stripped the instant it's parsed (the bug
 * that made decimals impossible). Only re-syncs the text when the external
 * number changes for another reason (repeat-last prefill, reset).
 */
function DecimalInput({
    value,
    onChange,
    className,
    placeholder,
    title,
}: {
    value: number | null;
    onChange: (n: number | null) => void;
    className?: string;
    placeholder?: string;
    title?: string;
}) {
    const [text, setText] = useState(value === null ? '' : String(value));

    useEffect(() => {
        if (numOrNull(text) !== value) {
            setText(value === null ? '' : String(value));
        }
        // Only react to external value changes, not to local typing.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <input
            inputMode="decimal"
            value={text}
            onChange={(e) => {
                setText(e.target.value);
                onChange(numOrNull(e.target.value));
            }}
            placeholder={placeholder}
            title={title}
            className={className}
        />
    );
}

/** Map a server exercise payload to the editor's Item shape. */
export function itemsFromServer(exercises: { exercise_id: string; name: string; note?: string; per_side?: boolean; superset_group?: number | null; sets: SetRow[] }[]): Item[] {
    return exercises.map((e) => ({
        exercise_id: e.exercise_id,
        name: e.name,
        per_side: e.per_side ?? false,
        superset_group: e.superset_group ?? null,
        note: e.note ?? '',
        sets: e.sets.length ? e.sets : [emptySet()],
    }));
}

function ExercisePicker({
    catalog,
    muscles,
    equipments,
    onPick,
    onClose,
}: {
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    onPick: (e: CatalogItem) => void;
    onClose: () => void;
}) {
    const [q, setQ] = useState('');
    const [muscle, setMuscle] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [newMuscle, setNewMuscle] = useState(muscles[0]?.value ?? '');
    const [newEquip, setNewEquip] = useState(equipments[0]?.value ?? '');

    const filtered = useMemo(
        () => catalog.filter((e) => (!muscle || e.muscle === muscle) && (q.trim() === '' || e.name.toLowerCase().includes(q.toLowerCase().trim()))),
        [catalog, q, muscle],
    );

    const createExercise = () => {
        if (newName.trim() === '') return;
        router.post(
            '/muscu/exercices',
            { name: newName.trim(), primaryMuscle: newMuscle, equipment: newEquip },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setCreating(false);
                    setNewName('');
                    setQ(newName.trim());
                },
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 sm:items-center" onClick={onClose}>
            <div className="flex max-h-[85vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center gap-2 border-b border-neutral-100 p-3">
                    <div className="flex flex-1 items-center gap-2 rounded-lg bg-neutral-100 px-3">
                        <Search size={15} className="text-neutral-400" />
                        <input
                            autoFocus
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Rechercher un exercice…"
                            className="w-full bg-transparent py-2 text-sm text-neutral-700 placeholder:text-neutral-400 focus:outline-none"
                        />
                    </div>
                    <button onClick={onClose} className="rounded-lg p-2 text-neutral-400 hover:bg-neutral-100">
                        <X size={18} />
                    </button>
                </div>

                <div className="flex gap-1.5 overflow-x-auto border-b border-neutral-100 p-2">
                    <button onClick={() => setMuscle(null)} className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${!muscle ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-500'}`}>
                        Tous
                    </button>
                    {muscles.map((m) => (
                        <button
                            key={m.value}
                            onClick={() => setMuscle(m.value === muscle ? null : m.value)}
                            className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${muscle === m.value ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-500'}`}
                        >
                            {m.label}
                        </button>
                    ))}
                </div>

                <div className="flex-1 overflow-y-auto p-2">
                    {filtered.map((e) => (
                        <button key={e.id} onClick={() => onPick(e)} className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-neutral-50">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-neutral-800">{e.name}</p>
                                <p className="text-xs text-neutral-400">
                                    {e.muscleLabel} · {e.equipmentLabel}
                                    {e.isCustom && ' · perso'}
                                </p>
                            </div>
                            <Plus size={16} className="shrink-0 text-brand-600" />
                        </button>
                    ))}
                    {filtered.length === 0 && <p className="px-3 py-6 text-center text-sm text-neutral-400">Aucun exercice — crée-le ci-dessous.</p>}
                </div>

                <div className="border-t border-neutral-100 p-3">
                    {creating ? (
                        <div className="space-y-2">
                            <input
                                autoFocus
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                placeholder="Nom de l'exercice"
                                className="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-neutral-400 focus:outline-none"
                            />
                            <div className="flex gap-2">
                                <select value={newMuscle} onChange={(e) => setNewMuscle(e.target.value)} className="flex-1 rounded-lg border border-neutral-200 px-2 py-2 text-sm">
                                    {muscles.map((m) => (
                                        <option key={m.value} value={m.value}>
                                            {m.label}
                                        </option>
                                    ))}
                                </select>
                                <select value={newEquip} onChange={(e) => setNewEquip(e.target.value)} className="flex-1 rounded-lg border border-neutral-200 px-2 py-2 text-sm">
                                    {equipments.map((eq) => (
                                        <option key={eq.value} value={eq.value}>
                                            {eq.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <button onClick={createExercise} className="flex-1 rounded-lg bg-neutral-900 py-2 text-sm font-semibold text-white">
                                    Créer &amp; ajouter
                                </button>
                                <button onClick={() => setCreating(false)} className="rounded-lg px-3 py-2 text-sm text-neutral-500">
                                    Annuler
                                </button>
                            </div>
                        </div>
                    ) : (
                        <button onClick={() => setCreating(true)} className="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-neutral-300 py-2.5 text-sm font-semibold text-neutral-600 hover:bg-neutral-50">
                            <Plus size={15} /> Créer un exercice
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * The exercise-and-sets editor shared by the template editor and the "do the
 * session" screen. The parent owns `items`; this renders the cards, the sets
 * grid and the add-exercise picker. `lastByExercise` (optional) powers the
 * "repeat last time" prefill on the session screen.
 */
interface HistorySet {
    weightKg: number | null;
    reps: number | null;
    rpe: number | null;
    durationSeconds: number | null;
    isWarmup: boolean;
    e1rm: number;
}
interface HistoryEntry {
    date: string;
    position: number;
    totalExercises: number;
    supersetGroup: number | null;
    perSide: boolean;
    bestE1rm: number;
    sets: HistorySet[];
}

const ordinal = (n: number): string => (n === 1 ? '1ᵉʳ' : `${n}ᵉ`);
const historyDate = (date: string): string =>
    new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const shortDate = (date: string): string => new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
const setText = (s: HistorySet): string => {
    if (s.weightKg != null && s.reps != null) return `${s.weightKg} kg × ${s.reps}`;
    if (s.reps != null) return `${s.reps} reps`;
    if (s.durationSeconds != null) return `${s.durationSeconds}s`;
    return '—';
};

type MetricKey = 'weight' | 'e1rm' | 'volume';
const METRICS: { key: MetricKey; label: string }[] = [
    { key: 'weight', label: 'Plus gros poids' },
    { key: 'e1rm', label: 'Meilleur 1RM' },
    { key: 'volume', label: 'Volume' },
];
const workingSets = (e: HistoryEntry): HistorySet[] => e.sets.filter((s) => !s.isWarmup);
const entryMetric = (e: HistoryEntry, k: MetricKey): number => {
    if (k === 'e1rm') return e.bestE1rm;
    if (k === 'weight') return workingSets(e).reduce((m, s) => Math.max(m, s.weightKg ?? 0), 0);
    return workingSets(e).reduce((m, s) => Math.max(m, (s.weightKg ?? 0) * (s.reps ?? 0)), 0); // best set volume
};

/** Hand-rolled SVG line chart (no chart lib in the project). Chronological left → right. */
function ProgressionChart({ values, dates }: { values: number[]; dates: string[] }) {
    const W = 320;
    const H = 132;
    const padL = 32;
    const padR = 10;
    const padT = 12;
    const padB = 20;
    const n = values.length;
    const max = Math.max(...values);
    const min = Math.min(...values);
    const lo = min === max ? Math.max(0, min - Math.max(1, min * 0.1)) : min;
    const range = max - lo || 1;
    const x = (i: number): number => (n <= 1 ? (padL + (W - padR)) / 2 : padL + (i * (W - padL - padR)) / (n - 1));
    const y = (v: number): number => padT + (H - padT - padB) * (1 - (v - lo) / range);
    const ticks = [max, lo + range / 2, lo];
    const line = values.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');

    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label="Courbe de progression">
            {ticks.map((t, k) => {
                const yy = y(t);
                return (
                    <g key={k}>
                        <line x1={padL} y1={yy} x2={W - padR} y2={yy} stroke="rgb(229 229 229)" strokeWidth={1} />
                        <text x={padL - 4} y={yy + 3} textAnchor="end" fontSize={9} fill="rgb(163 163 163)">
                            {Math.round(t)}
                        </text>
                    </g>
                );
            })}
            {n > 1 && <polyline points={line} fill="none" stroke="rgb(242 103 34)" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />}
            {values.map((v, i) => (
                <circle key={i} cx={x(i)} cy={y(v)} r={n > 30 ? 1.5 : 2.5} fill="rgb(242 103 34)" />
            ))}
            {n > 0 && (
                <>
                    <text x={padL} y={H - 6} textAnchor="start" fontSize={9} fill="rgb(163 163 163)">
                        {shortDate(dates[0])}
                    </text>
                    {n > 1 && (
                        <text x={W - padR} y={H - 6} textAnchor="end" fontSize={9} fill="rgb(163 163 163)">
                            {shortDate(dates[n - 1])}
                        </text>
                    )}
                </>
            )}
        </svg>
    );
}

/**
 * In-session history sheet for one exercise (Hevy-style, light theme). Loads on
 * demand (JSON) so the athlete never leaves the running session. Shows a
 * progression chart (selectable metric), personal records, then every done
 * session with its date, position that day (order rotates weekly), and sets.
 */
function ExerciseHistoryModal({ exerciseId, name, onClose }: { exerciseId: string; name: string; onClose: () => void }) {
    const [entries, setEntries] = useState<HistoryEntry[] | null>(null);
    const [failed, setFailed] = useState(false);
    const [metric, setMetric] = useState<MetricKey>('weight');

    useEffect(() => {
        let alive = true;
        setEntries(null);
        setFailed(false);
        fetch(`/muscu/exercice/${encodeURIComponent(exerciseId)}/historique`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('http'))))
            .then((d: { entries?: HistoryEntry[] }) => {
                if (alive) setEntries(Array.isArray(d.entries) ? d.entries : []);
            })
            .catch(() => {
                if (alive) setFailed(true);
            });
        return () => {
            alive = false;
        };
    }, [exerciseId]);

    const all = entries ?? [];
    const bestOverall = all.reduce((m, e) => Math.max(m, e.bestE1rm), 0);

    // Personal records across every set ever logged for this exercise.
    let pgp = 0; // plus gros poids
    let best1rm = 0;
    let bestVol = 0;
    let bestVolSet: { w: number; r: number } | null = null;
    for (const e of all) {
        for (const s of e.sets) {
            if (s.isWarmup) continue;
            const w = s.weightKg ?? 0;
            const r = s.reps ?? 0;
            pgp = Math.max(pgp, w);
            best1rm = Math.max(best1rm, s.e1rm);
            if (w * r > bestVol) {
                bestVol = w * r;
                bestVolSet = { w, r };
            }
        }
    }

    // Chart data, chronological (oldest → newest).
    const chrono = [...all].reverse();
    const chartValues = chrono.map((e) => entryMetric(e, metric));
    const chartDates = chrono.map((e) => e.date);
    const hasChart = chartValues.some((v) => v > 0);

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={onClose}>
            <div className="flex max-h-[88vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between gap-2 border-b border-neutral-100 p-4">
                    <div className="min-w-0">
                        <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400">
                            <History size={13} /> Historique
                        </p>
                        <p className="truncate text-base font-bold text-neutral-900">{name}</p>
                    </div>
                    <button onClick={onClose} className="shrink-0 rounded-lg p-2 text-neutral-400 hover:bg-neutral-100">
                        <X size={18} />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-4">
                    {failed ? (
                        <p className="py-8 text-center text-sm text-neutral-500">Impossible de charger l'historique.</p>
                    ) : entries === null ? (
                        <p className="py-8 text-center text-sm text-neutral-400">Chargement…</p>
                    ) : all.length === 0 ? (
                        <p className="py-8 text-center text-sm text-neutral-500">Aucune séance terminée avec cet exercice.</p>
                    ) : (
                        <>
                            {/* Progression chart + metric selector */}
                            <div className="mb-1 flex flex-wrap gap-1.5">
                                {METRICS.map((m) => (
                                    <button
                                        key={m.key}
                                        onClick={() => setMetric(m.key)}
                                        className={`rounded-full px-3 py-1 text-xs font-semibold transition-colors ${
                                            metric === m.key ? 'bg-brand-600 text-white' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                                        }`}
                                    >
                                        {m.label}
                                    </button>
                                ))}
                            </div>
                            {hasChart ? (
                                <ProgressionChart values={chartValues} dates={chartDates} />
                            ) : (
                                <p className="py-6 text-center text-xs text-neutral-400">Pas de données chiffrées pour cette métrique.</p>
                            )}

                            {/* Personal records */}
                            <div className="mt-2 rounded-xl border border-neutral-200">
                                <p className="border-b border-neutral-100 px-3 py-2 text-sm font-bold text-neutral-800">🏅 Records personnels</p>
                                <div className="divide-y divide-neutral-100 text-sm">
                                    <div className="flex items-center justify-between px-3 py-2">
                                        <span className="text-neutral-500">Plus gros poids</span>
                                        <span className="font-bold text-brand-600">{pgp > 0 ? `${pgp} kg` : '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between px-3 py-2">
                                        <span className="text-neutral-500">Meilleur 1RM</span>
                                        <span className="font-bold text-brand-600">{best1rm > 0 ? `${best1rm} kg` : '—'}</span>
                                    </div>
                                    <div className="flex items-center justify-between px-3 py-2">
                                        <span className="text-neutral-500">Meilleur volume de série</span>
                                        <span className="font-bold text-brand-600">{bestVolSet ? `${bestVolSet.w} kg × ${bestVolSet.r}` : '—'}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Session-by-session history */}
                            <p className="mb-2 mt-4 text-sm font-bold text-neutral-800">Séances</p>
                            <div className="space-y-2.5">
                                {all.map((entry, i) => {
                                    const isRecord = entry.bestE1rm > 0 && entry.bestE1rm === bestOverall;
                                    return (
                                        <div key={i} className="rounded-xl border border-neutral-200 bg-white p-3">
                                            <div className="mb-2 flex flex-wrap items-center justify-between gap-1.5">
                                                <p className="text-sm font-semibold capitalize text-neutral-800">{historyDate(entry.date)}</p>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-500">
                                                        {ordinal(entry.position)} / {entry.totalExercises}
                                                    </span>
                                                    {entry.supersetGroup != null && (
                                                        <span className="inline-flex items-center gap-0.5 rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-violet-700">
                                                            <Link2 size={11} /> {supersetLabel(entry.supersetGroup)}
                                                        </span>
                                                    )}
                                                    {entry.bestE1rm > 0 && (
                                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${isRecord ? 'bg-brand-100 text-brand-700' : 'bg-neutral-100 text-neutral-500'}`}>
                                                            e1RM {entry.bestE1rm} kg
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="space-y-1">
                                                {entry.sets.map((s, j) => (
                                                    <div
                                                        key={j}
                                                        className={`flex items-center justify-between gap-2 rounded-lg px-2.5 py-1 text-sm ${s.isWarmup ? 'text-neutral-400' : 'bg-neutral-50 text-neutral-800'}`}
                                                    >
                                                        <span className="flex items-center gap-2">
                                                            <span className="w-4 shrink-0 text-right text-xs font-medium text-neutral-400">{j + 1}</span>
                                                            <span className={s.isWarmup ? '' : 'font-semibold'}>{setText(s)}</span>
                                                            {entry.perSide && !s.isWarmup && <span className="text-[10px] text-neutral-400">/ côté</span>}
                                                            {s.isWarmup && <span className="text-[10px] uppercase tracking-wide">échauff.</span>}
                                                        </span>
                                                        {s.rpe != null && <span className="text-xs text-neutral-400">RPE {s.rpe}</span>}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

export function ExerciseEditor({
    items,
    setItems,
    catalog,
    muscles,
    equipments,
    lastByExercise = {},
    execution = false,
    onSetValidated,
}: {
    items: Item[];
    setItems: (updater: (prev: Item[]) => Item[]) => void;
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    lastByExercise?: Record<string, { sets: SetRow[] }>;
    execution?: boolean;
    /** Called when a set is ticked as done (not when un-ticked) — used to (re)start the rest timer. */
    onSetValidated?: () => void;
}) {
    const [pickerOpen, setPickerOpen] = useState(false);
    // Which exercise's history sheet is open (null = closed). Opened by the
    // per-exercise history button; loads on demand so the session isn't left.
    const [historyFor, setHistoryFor] = useState<{ id: string; name: string } | null>(null);

    const addExercise = (e: CatalogItem) => {
        const last = lastByExercise[e.id];
        const sets = last?.sets?.length ? last.sets.map((s) => ({ ...s, done: !execution })) : [{ ...emptySet(), done: !execution }];
        setItems((prev) => [...prev, { exercise_id: e.id, name: e.name, muscleLabel: e.muscleLabel, equipmentLabel: e.equipmentLabel, per_side: false, superset_group: null, note: '', sets }]);
        setPickerOpen(false);
    };
    const removeItem = (i: number) => setItems((prev) => prev.filter((_, idx) => idx !== i));
    const patchItem = (i: number, patch: Partial<Item>) => setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
    const moveItem = (i: number, dir: -1 | 1) =>
        setItems((prev) => {
            const j = i + dir;
            if (j < 0 || j >= prev.length) return prev;
            const next = [...prev];
            [next[i], next[j]] = [next[j], next[i]];
            return next;
        });
    const addSet = (i: number) =>
        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== i) return it;
                const prevSet = it.sets[it.sets.length - 1];
                const next: SetRow = prevSet ? { ...prevSet, is_warmup: false, done: !execution } : { ...emptySet(), done: !execution };
                return { ...it, sets: [...it.sets, next] };
            }),
        );
    const patchSet = (i: number, s: number, patch: Partial<SetRow>) =>
        setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, sets: it.sets.map((set, sIdx) => (sIdx === s ? { ...set, ...patch } : set)) } : it)));
    const removeSet = (i: number, s: number) => setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, sets: it.sets.filter((_, sIdx) => sIdx !== s) } : it)));
    const repeatLast = (i: number) => {
        const last = lastByExercise[items[i].exercise_id];
        if (last?.sets?.length) patchItem(i, { sets: last.sets.map((s) => ({ ...s, done: true })) });
    };

    return (
        <div>
            {items.map((it, i) => {
                const collapsed = it.collapsed ?? false;
                const workingSets = it.sets.filter((s) => !s.is_warmup);
                const doneWorking = workingSets.filter((s) => s.done).length;
                const topSet = lastTopSet(it.sets);
                const allDone = execution && it.sets.length > 0 && it.sets.every((s) => s.done);
                // A superset run reads as one block: same-group neighbours share a
                // violet left rail, joined tight (rounding opened between them).
                const group = it.superset_group;
                const linkedAbove = group != null && i > 0 && items[i - 1].superset_group === group;
                const linkedBelow = group != null && i < items.length - 1 && items[i + 1].superset_group === group;
                const rounding = linkedAbove && linkedBelow ? 'rounded-none' : linkedAbove ? 'rounded-b-2xl rounded-t-none' : linkedBelow ? 'rounded-t-2xl rounded-b-none' : 'rounded-2xl';
                const color = allDone
                    ? 'border-emerald-300 bg-emerald-50 shadow-emerald-200/50'
                    : group != null
                      ? 'border-neutral-200 border-l-4 border-l-violet-400 bg-white shadow-neutral-200/60'
                      : 'border-neutral-200 bg-white shadow-neutral-200/60';
                const prevWorking = (lastByExercise[it.exercise_id]?.sets ?? []).filter((x) => !x.is_warmup);
                // During a session: Série · Précédent · Kg · Reps · ✓ (Hevy-style).
                // While planning: Set · Kg · Reps · RPE · ✗.
                const gridCols = execution ? 'grid-cols-[1.5rem_minmax(0,1fr)_3.8rem_3rem_1.75rem]' : 'grid-cols-[1.5rem_1fr_1fr_1fr_1.5rem]';
                return (
                <div
                    key={i}
                    className={
                        execution
                            ? `pb-4 ${group != null ? 'border-l-4 border-l-violet-400 pl-3' : ''} ${i < items.length - 1 ? 'mb-4 border-b border-neutral-200' : ''}`
                            : `border p-4 shadow-sm transition-colors ${rounding} ${color} ${linkedAbove ? 'border-t-0' : ''} ${linkedBelow ? '' : 'mb-3'}`
                    }
                >
                    <div className={`flex items-start justify-between gap-2 ${collapsed ? '' : 'mb-2'}`}>
                        <button onClick={() => patchItem(i, { collapsed: !collapsed })} className="flex min-w-0 flex-1 items-start gap-2 text-left">
                            <div className="min-w-0">
                                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-neutral-800">
                                    <span className={`break-words ${execution ? 'text-brand-600' : ''}`}>{it.name}</span>
                                    {it.superset_group != null && (
                                        <span className="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-700">
                                            <Link2 size={11} /> Superset {supersetLabel(it.superset_group)}
                                        </span>
                                    )}
                                </p>
                                {collapsed ? (
                                    <p className="text-xs text-neutral-500">
                                        {execution ? `${doneWorking}/${workingSets.length} série${workingSets.length > 1 ? 's' : ''}` : `${workingSets.length} série${workingSets.length > 1 ? 's' : ''}`}
                                        {topSet ? ` · ${topSet}` : ''}
                                    </p>
                                ) : (
                                    <>
                                        {(it.muscleLabel || it.equipmentLabel) && (
                                            <p className="text-xs text-neutral-400">
                                                {it.muscleLabel}
                                                {it.equipmentLabel ? ` · ${it.equipmentLabel}` : ''}
                                            </p>
                                        )}
                                        {it.note !== '' && <p className="mt-1 whitespace-pre-line text-xs text-neutral-500">{it.note}</p>}
                                        {execution && lastByExercise[it.exercise_id] && lastTopSet(lastByExercise[it.exercise_id].sets) && (
                                            <p className="mt-0.5 text-xs font-medium text-brand-600">↩ Dernière fois : {lastTopSet(lastByExercise[it.exercise_id].sets)}</p>
                                        )}
                                    </>
                                )}
                            </div>
                        </button>
                        <div className="flex shrink-0 items-center gap-1">
                            {items.length > 1 && (
                                <div className="flex flex-col">
                                    <button onClick={() => moveItem(i, -1)} disabled={i === 0} title="Monter" className="rounded p-0.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 disabled:opacity-30">
                                        <ChevronUp size={14} />
                                    </button>
                                    <button onClick={() => moveItem(i, 1)} disabled={i === items.length - 1} title="Descendre" className="rounded p-0.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 disabled:opacity-30">
                                        <ChevronDown size={14} />
                                    </button>
                                </div>
                            )}
                            {!execution && (
                                <select
                                    value={it.superset_group ?? ''}
                                    onChange={(e) => patchItem(i, { superset_group: e.target.value === '' ? null : Number(e.target.value) })}
                                    title="Regrouper en superset (même lettre = enchaînés)"
                                    className={`rounded-lg border px-1.5 py-1 text-xs font-semibold focus:outline-none ${
                                        it.superset_group != null ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-neutral-200 bg-white text-neutral-500'
                                    }`}
                                >
                                    <option value="">Solo</option>
                                    <option value="1">SS A</option>
                                    <option value="2">SS B</option>
                                    <option value="3">SS C</option>
                                    <option value="4">SS D</option>
                                </select>
                            )}
                            {lastByExercise[it.exercise_id] && (
                                <button onClick={() => repeatLast(i)} title="Reprendre la dernière fois" className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                                    <RotateCcw size={15} />
                                </button>
                            )}
                            {!execution && (
                                <button onClick={() => removeItem(i)} className="rounded-lg p-1.5 text-neutral-400 hover:bg-rose-50 hover:text-rose-500">
                                    <Trash2 size={15} />
                                </button>
                            )}
                            {/* Always present, pinned far-right so they line up across every card. */}
                            <button
                                onClick={() => setHistoryFor({ id: it.exercise_id, name: it.name })}
                                title="Historique de l'exercice"
                                className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-brand-600"
                            >
                                <History size={15} />
                            </button>
                            <button
                                onClick={() => patchItem(i, { collapsed: !collapsed })}
                                title={collapsed ? 'Agrandir' : 'Réduire'}
                                className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600"
                            >
                                {collapsed ? <ChevronRight size={16} /> : <ChevronDown size={16} />}
                            </button>
                        </div>
                    </div>

                    {!collapsed && (
                    <>
                    <div className={`grid ${gridCols} items-center gap-2 px-1 pb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400`}>
                        <span>{execution ? 'Série' : 'Set'}</span>
                        {execution ? <span>Précédent</span> : <span>Kg</span>}
                        {execution ? <span className="text-center">Kg</span> : <span>Reps</span>}
                        {execution ? <span className="text-center">Reps</span> : <span>RPE</span>}
                        <span />
                    </div>

                    {it.sets.map((set, s) => {
                        const workingIndex = it.sets.slice(0, s + 1).filter((x) => !x.is_warmup).length;
                        return (
                            <div key={s} className={`grid ${gridCols} items-center gap-2 rounded-lg px-1 py-1 transition-colors ${execution && set.done ? 'bg-emerald-50' : ''} ${execution && !set.done ? 'opacity-50' : ''}`}>
                                <button
                                    onClick={() => patchSet(i, s, { is_warmup: !set.is_warmup })}
                                    title={set.is_warmup ? 'Échauffement' : 'Série de travail'}
                                    className={`h-6 w-6 rounded-md text-[11px] font-bold ${set.is_warmup ? 'bg-amber-100 text-amber-600' : 'bg-neutral-100 text-neutral-500'}`}
                                >
                                    {set.is_warmup ? 'É' : workingIndex}
                                </button>
                                {execution && (
                                    <span className="truncate text-xs tabular-nums text-neutral-400" title="Dernière fois">
                                        {set.is_warmup ? '—' : prevLabel(prevWorking[workingIndex - 1])}
                                    </span>
                                )}
                                <DecimalInput
                                    value={set.weight_kg}
                                    onChange={(n) => patchSet(i, s, { weight_kg: n })}
                                    placeholder="—"
                                    className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                />
                                <input
                                    inputMode="numeric"
                                    value={set.reps ?? ''}
                                    onChange={(e) => {
                                        const n = numOrNull(e.target.value);
                                        patchSet(i, s, { reps: n === null ? null : Math.round(n) });
                                    }}
                                    placeholder="—"
                                    className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                />
                                {!execution && (
                                    <DecimalInput
                                        value={set.rpe}
                                        onChange={(n) => patchSet(i, s, { rpe: n === null ? null : Math.min(10, Math.max(0, n)) })}
                                        placeholder="—"
                                        title="RPE = intensité perçue, de 0 à 10"
                                        className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                    />
                                )}
                                {execution ? (
                                    <button
                                        onClick={() => {
                                            const nowDone = !set.done;
                                            patchSet(i, s, { done: nowDone });
                                            if (nowDone) onSetValidated?.();
                                        }}
                                        title={set.done ? 'Série faite' : 'Marquer comme faite'}
                                        className={`flex h-6 w-6 items-center justify-center rounded-md transition-colors ${
                                            set.done ? 'bg-emerald-500 text-white' : 'border border-neutral-300 text-neutral-300 hover:border-emerald-400 hover:text-emerald-400'
                                        }`}
                                    >
                                        <Check size={14} />
                                    </button>
                                ) : (
                                    <button onClick={() => removeSet(i, s)} className="text-neutral-300 hover:text-rose-500">
                                        <X size={14} />
                                    </button>
                                )}
                            </div>
                        );
                    })}

                    <button onClick={() => addSet(i)} className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-neutral-200 py-2 text-xs font-semibold text-neutral-500 hover:bg-neutral-50">
                        <Copy size={13} /> Ajouter une série
                    </button>
                    </>
                    )}
                </div>
                );
            })}

            <button
                onClick={() => setPickerOpen(true)}
                className="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-neutral-300 py-4 text-sm font-semibold text-neutral-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
            >
                <Plus size={18} /> Ajouter un exercice
            </button>

            {pickerOpen && <ExercisePicker catalog={catalog} muscles={muscles} equipments={equipments} onPick={addExercise} onClose={() => setPickerOpen(false)} />}
            {historyFor && <ExerciseHistoryModal exerciseId={historyFor.id} name={historyFor.name} onClose={() => setHistoryFor(null)} />}
        </div>
    );
}
