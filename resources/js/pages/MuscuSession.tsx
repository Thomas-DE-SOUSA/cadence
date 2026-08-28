import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check, Copy, Dumbbell, Plus, RotateCcw, Search, Timer, Trash2, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';

interface CatalogItem {
    id: string;
    name: string;
    muscle: string;
    muscleLabel: string;
    equipment: string;
    equipmentLabel: string;
    isCustom: boolean;
}
interface Option {
    value: string;
    label: string;
}
interface SetRow {
    weight_kg: number | null;
    reps: number | null;
    rpe: number | null;
    duration_seconds: number | null;
    is_warmup: boolean;
    done: boolean;
}
interface Item {
    exercise_id: string;
    name: string;
    muscleLabel?: string;
    equipmentLabel?: string;
    per_side: boolean;
    superset_group: number | null;
    note: string;
    sets: SetRow[];
}
interface SessionData {
    id: string;
    date: string;
    title: string;
    note: string;
    durationSeconds: number | null;
    exercises: {
        exercise_id: string;
        name: string;
        note?: string;
        per_side?: boolean;
        superset_group?: number | null;
        sets: SetRow[];
    }[];
}
interface Props {
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    session: SessionData | null;
    lastByExercise: Record<string, { sets: SetRow[] }>;
}

const today = () => new Date().toISOString().slice(0, 10);
const emptySet = (): SetRow => ({ weight_kg: null, reps: null, rpe: null, duration_seconds: null, is_warmup: false, done: true });

function mmss(total: number): string {
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function numOrNull(v: string): number | null {
    if (v.trim() === '') return null;
    const n = Number(v.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

/** Count-up session chrono, auto-started, resumable from a saved duration. */
function useChrono(initial: number): [number, () => void] {
    const [seconds, setSeconds] = useState(initial);
    const [running, setRunning] = useState(true);
    useEffect(() => {
        if (!running) return;
        const t = setInterval(() => setSeconds((s) => s + 1), 1000);
        return () => clearInterval(t);
    }, [running]);
    return [seconds, () => setRunning((r) => !r)];
}

/** Rest countdown with presets. */
function RestTimer() {
    const [remaining, setRemaining] = useState(0);
    const ref = useRef<number | null>(null);
    const start = (s: number) => {
        setRemaining(s);
        if (ref.current) window.clearInterval(ref.current);
        ref.current = window.setInterval(() => {
            setRemaining((r) => {
                if (r <= 1) {
                    if (ref.current) window.clearInterval(ref.current);
                    return 0;
                }
                return r - 1;
            });
        }, 1000);
    };
    useEffect(() => () => void (ref.current && window.clearInterval(ref.current)), []);
    const active = remaining > 0;
    return (
        <div className={`flex items-center gap-2 rounded-xl border px-3 py-2 ${active ? 'border-brand-300 bg-brand-50' : 'border-neutral-200 bg-white'}`}>
            <Timer size={16} className={active ? 'text-brand-600' : 'text-neutral-400'} />
            <span className={`w-12 text-sm font-bold tabular-nums ${active ? 'text-brand-700' : 'text-neutral-400'}`}>{mmss(remaining)}</span>
            <div className="flex gap-1">
                {[60, 90, 120, 180].map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => start(s)}
                        className="rounded-md bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-200"
                    >
                        {s < 120 ? `${s}s` : `${s / 60}m`}
                    </button>
                ))}
                {active && (
                    <button type="button" onClick={() => setRemaining(0)} className="rounded-md px-1.5 py-1 text-neutral-400 hover:text-neutral-600">
                        <X size={14} />
                    </button>
                )}
            </div>
        </div>
    );
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
        () =>
            catalog.filter(
                (e) => (!muscle || e.muscle === muscle) && (q.trim() === '' || e.name.toLowerCase().includes(q.toLowerCase().trim())),
            ),
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
            <div
                className="flex max-h-[85vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
                onClick={(e) => e.stopPropagation()}
            >
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
                    <button
                        onClick={() => setMuscle(null)}
                        className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${!muscle ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-500'}`}
                    >
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
                        <button
                            key={e.id}
                            onClick={() => onPick(e)}
                            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-neutral-50"
                        >
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
                                    Créer & ajouter
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

export default function MuscuSession({ catalog, muscles, equipments, session, lastByExercise }: Props) {
    const [date, setDate] = useState(session?.date ?? today());
    const [title, setTitle] = useState(session?.title ?? '');
    const [items, setItems] = useState<Item[]>(
        session
            ? session.exercises.map((e) => ({
                  exercise_id: e.exercise_id,
                  name: e.name,
                  per_side: e.per_side ?? false,
                  superset_group: e.superset_group ?? null,
                  note: e.note ?? '',
                  sets: e.sets.length ? e.sets : [emptySet()],
              }))
            : [],
    );
    const [pickerOpen, setPickerOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [elapsed, toggleChrono] = useChrono(session?.durationSeconds ?? 0);

    const addExercise = (e: CatalogItem) => {
        const last = lastByExercise[e.id];
        const sets = last?.sets?.length ? last.sets.map((s) => ({ ...s, done: true })) : [emptySet()];
        setItems((prev) => [
            ...prev,
            { exercise_id: e.id, name: e.name, muscleLabel: e.muscleLabel, equipmentLabel: e.equipmentLabel, per_side: false, superset_group: null, note: '', sets },
        ]);
        setPickerOpen(false);
    };

    const removeItem = (i: number) => setItems((prev) => prev.filter((_, idx) => idx !== i));
    const patchItem = (i: number, patch: Partial<Item>) => setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
    const addSet = (i: number) =>
        setItems((prev) =>
            prev.map((it, idx) => {
                if (idx !== i) return it;
                const prevSet = it.sets[it.sets.length - 1];
                const next: SetRow = prevSet ? { ...prevSet, is_warmup: false, done: true } : emptySet();
                return { ...it, sets: [...it.sets, next] };
            }),
        );
    const patchSet = (i: number, s: number, patch: Partial<SetRow>) =>
        setItems((prev) =>
            prev.map((it, idx) => (idx === i ? { ...it, sets: it.sets.map((set, sIdx) => (sIdx === s ? { ...set, ...patch } : set)) } : it)),
        );
    const removeSet = (i: number, s: number) =>
        setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, sets: it.sets.filter((_, sIdx) => sIdx !== s) } : it)));

    const repeatLast = (i: number) => {
        const id = items[i].exercise_id;
        const last = lastByExercise[id];
        if (last?.sets?.length) patchItem(i, { sets: last.sets.map((s) => ({ ...s, done: true })) });
    };

    const save = () => {
        setSaving(true);
        router.post(
            '/muscu',
            { id: session?.id ?? null, date, title, note: '', durationSeconds: elapsed, exercises: items },
            { onFinish: () => setSaving(false) },
        );
    };

    const totalSets = items.reduce((n, it) => n + it.sets.filter((s) => !s.is_warmup).length, 0);

    return (
        <>
            <Head title={session ? 'Modifier la séance' : 'Nouvelle séance'} />

            <div className="mb-4 flex items-center gap-2">
                <Link href="/muscu" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ArrowLeft size={18} />
                </Link>
                <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Titre (Push, Jambes, Full-body…)"
                    className="flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-lg font-bold text-neutral-900 placeholder:text-neutral-300 focus:border-neutral-200 focus:outline-none"
                />
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <input
                    type="date"
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                    className="rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 focus:border-neutral-400 focus:outline-none"
                />
                <button
                    onClick={toggleChrono}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm font-semibold tabular-nums text-neutral-700"
                >
                    <Timer size={15} className="text-brand-600" /> {mmss(elapsed)}
                </button>
                <RestTimer />
            </div>

            <div className="space-y-3 pb-28">
                {items.map((it, i) => (
                    <div key={i} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/60">
                        <div className="mb-2 flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-neutral-800">{it.name}</p>
                                {(it.muscleLabel || it.equipmentLabel) && (
                                    <p className="text-xs text-neutral-400">
                                        {it.muscleLabel}
                                        {it.equipmentLabel ? ` · ${it.equipmentLabel}` : ''}
                                    </p>
                                )}
                            </div>
                            <div className="flex shrink-0 gap-1">
                                {lastByExercise[it.exercise_id] && (
                                    <button onClick={() => repeatLast(i)} title="Reprendre la dernière fois" className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                                        <RotateCcw size={15} />
                                    </button>
                                )}
                                <button onClick={() => removeItem(i)} className="rounded-lg p-1.5 text-neutral-400 hover:bg-rose-50 hover:text-rose-500">
                                    <Trash2 size={15} />
                                </button>
                            </div>
                        </div>

                        <div className="grid grid-cols-[1.5rem_1fr_1fr_1fr_1.5rem] items-center gap-2 px-1 pb-1 text-[10px] font-semibold uppercase tracking-wide text-neutral-400">
                            <span>Set</span>
                            <span>Kg</span>
                            <span>Reps</span>
                            <span>RPE</span>
                            <span />
                        </div>

                        {it.sets.map((set, s) => {
                            const workingIndex = it.sets.slice(0, s + 1).filter((x) => !x.is_warmup).length;
                            return (
                                <div key={s} className="grid grid-cols-[1.5rem_1fr_1fr_1fr_1.5rem] items-center gap-2 py-1">
                                    <button
                                        onClick={() => patchSet(i, s, { is_warmup: !set.is_warmup })}
                                        title={set.is_warmup ? 'Échauffement' : 'Série de travail'}
                                        className={`h-6 w-6 rounded-md text-[11px] font-bold ${set.is_warmup ? 'bg-amber-100 text-amber-600' : 'bg-neutral-100 text-neutral-500'}`}
                                    >
                                        {set.is_warmup ? 'É' : workingIndex}
                                    </button>
                                    <input
                                        inputMode="decimal"
                                        value={set.weight_kg ?? ''}
                                        onChange={(e) => patchSet(i, s, { weight_kg: numOrNull(e.target.value) })}
                                        placeholder="—"
                                        className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                    />
                                    <input
                                        inputMode="numeric"
                                        value={set.reps ?? ''}
                                        onChange={(e) => patchSet(i, s, { reps: numOrNull(e.target.value) === null ? null : Math.round(numOrNull(e.target.value)!) })}
                                        placeholder="—"
                                        className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                    />
                                    <input
                                        inputMode="decimal"
                                        value={set.rpe ?? ''}
                                        onChange={(e) => patchSet(i, s, { rpe: numOrNull(e.target.value) })}
                                        placeholder="—"
                                        className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                    />
                                    <button onClick={() => removeSet(i, s)} className="text-neutral-300 hover:text-rose-500">
                                        <X size={14} />
                                    </button>
                                </div>
                            );
                        })}

                        <button
                            onClick={() => addSet(i)}
                            className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-neutral-200 py-2 text-xs font-semibold text-neutral-500 hover:bg-neutral-50"
                        >
                            <Copy size={13} /> Ajouter une série
                        </button>
                    </div>
                ))}

                <button
                    onClick={() => setPickerOpen(true)}
                    className="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-neutral-300 py-4 text-sm font-semibold text-neutral-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                >
                    <Plus size={18} /> Ajouter un exercice
                </button>
            </div>

            {/* Sticky save bar */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 p-3 backdrop-blur sm:left-64">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
                    <span className="text-sm text-neutral-500">
                        {items.length} exo{items.length > 1 ? 's' : ''} · {totalSets} série{totalSets > 1 ? 's' : ''}
                    </span>
                    <button
                        onClick={save}
                        disabled={saving || items.length === 0}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-40"
                    >
                        <Check size={16} /> {saving ? 'Enregistrement…' : 'Enregistrer'}
                    </button>
                </div>
            </div>

            {pickerOpen && (
                <ExercisePicker catalog={catalog} muscles={muscles} equipments={equipments} onPick={addExercise} onClose={() => setPickerOpen(false)} />
            )}
        </>
    );
}

MuscuSession.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
