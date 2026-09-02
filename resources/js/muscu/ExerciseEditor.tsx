import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Check, ChevronDown, ChevronRight, Copy, Plus, RotateCcw, Search, Trash2, X } from 'lucide-react';

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

export function numOrNull(v: string): number | null {
    if (v.trim() === '') return null;
    const n = Number(v.replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

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
export function ExerciseEditor({
    items,
    setItems,
    catalog,
    muscles,
    equipments,
    lastByExercise = {},
    execution = false,
}: {
    items: Item[];
    setItems: (updater: (prev: Item[]) => Item[]) => void;
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    lastByExercise?: Record<string, { sets: SetRow[] }>;
    execution?: boolean;
}) {
    const [pickerOpen, setPickerOpen] = useState(false);

    const addExercise = (e: CatalogItem) => {
        const last = lastByExercise[e.id];
        const sets = last?.sets?.length ? last.sets.map((s) => ({ ...s, done: !execution })) : [{ ...emptySet(), done: !execution }];
        setItems((prev) => [...prev, { exercise_id: e.id, name: e.name, muscleLabel: e.muscleLabel, equipmentLabel: e.equipmentLabel, per_side: false, superset_group: null, note: '', sets }]);
        setPickerOpen(false);
    };
    const removeItem = (i: number) => setItems((prev) => prev.filter((_, idx) => idx !== i));
    const patchItem = (i: number, patch: Partial<Item>) => setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
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
        <div className="space-y-3">
            {items.map((it, i) => {
                const collapsed = it.collapsed ?? false;
                const workingSets = it.sets.filter((s) => !s.is_warmup);
                const doneWorking = workingSets.filter((s) => s.done).length;
                const topSet = lastTopSet(it.sets);
                const allDone = execution && it.sets.length > 0 && it.sets.every((s) => s.done);
                return (
                <div key={i} className={`rounded-2xl border p-4 shadow-sm transition-colors ${allDone ? 'border-emerald-300 bg-emerald-50 shadow-emerald-200/50' : 'border-neutral-200 bg-white shadow-neutral-200/60'}`}>
                    <div className={`flex items-start justify-between gap-2 ${collapsed ? '' : 'mb-2'}`}>
                        <button onClick={() => patchItem(i, { collapsed: !collapsed })} className="flex min-w-0 flex-1 items-start gap-2 text-left">
                            <span className="mt-0.5 shrink-0 text-neutral-400">{collapsed ? <ChevronRight size={16} /> : <ChevronDown size={16} />}</span>
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-neutral-800">{it.name}</p>
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
                                        {execution && lastByExercise[it.exercise_id] && lastTopSet(lastByExercise[it.exercise_id].sets) && (
                                            <p className="mt-0.5 text-xs font-medium text-brand-600">↩ Dernière fois : {lastTopSet(lastByExercise[it.exercise_id].sets)}</p>
                                        )}
                                    </>
                                )}
                            </div>
                        </button>
                        <div className="flex shrink-0 gap-1">
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
                        </div>
                    </div>

                    {!collapsed && (
                    <>
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
                            <div key={s} className={`grid grid-cols-[1.5rem_1fr_1fr_1fr_1.5rem] items-center gap-2 rounded-lg px-1 py-1 transition-colors ${execution && set.done ? 'bg-emerald-50' : ''} ${execution && !set.done ? 'opacity-50' : ''}`}>
                                <button
                                    onClick={() => patchSet(i, s, { is_warmup: !set.is_warmup })}
                                    title={set.is_warmup ? 'Échauffement' : 'Série de travail'}
                                    className={`h-6 w-6 rounded-md text-[11px] font-bold ${set.is_warmup ? 'bg-amber-100 text-amber-600' : 'bg-neutral-100 text-neutral-500'}`}
                                >
                                    {set.is_warmup ? 'É' : workingIndex}
                                </button>
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
                                <DecimalInput
                                    value={set.rpe}
                                    onChange={(n) => patchSet(i, s, { rpe: n === null ? null : Math.min(10, Math.max(0, n)) })}
                                    placeholder="—"
                                    title="RPE = intensité perçue, de 0 à 10"
                                    className="w-full rounded-lg border border-neutral-200 px-2 py-1.5 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                                />
                                {execution ? (
                                    <button
                                        onClick={() => patchSet(i, s, { done: !set.done })}
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
        </div>
    );
}
