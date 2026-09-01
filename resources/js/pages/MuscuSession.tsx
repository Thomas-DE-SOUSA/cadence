import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check, CircleCheck, Flag, Play, RotateCcw, Square, Timer, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/AppLayout';
import { ExerciseEditor, itemsFromServer, type CatalogItem, type Item, type Option, type SetRow } from '@/muscu/ExerciseEditor';

interface SessionData {
    id: string;
    date: string;
    title: string;
    note: string;
    status: 'PLANNED' | 'DONE';
    templateId: string | null;
    exercises: { exercise_id: string; name: string; note?: string; per_side?: boolean; superset_group?: number | null; sets: SetRow[] }[];
}
interface Props {
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    session: SessionData | null;
    lastByExercise: Record<string, { sets: SetRow[] }>;
}

const today = () => new Date().toISOString().slice(0, 10);

function mmss(total: number): string {
    const m = Math.floor(total / 60);
    return `${String(m).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

/**
 * Manual count-up stopwatch. A full-width button opens a popup; the chrono
 * starts from 0 on first open and keeps running even when the popup is closed —
 * closing never stops it. While active, the button shows the running time
 * instead of the "Chrono" label.
 */
function SessionChrono() {
    const [open, setOpen] = useState(false);
    const [running, setRunning] = useState(false);
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        if (!running) return;
        const t = window.setInterval(() => setElapsed((s) => s + 1), 1000);
        return () => window.clearInterval(t);
    }, [running]);

    const openModal = () => {
        if (!running && elapsed === 0) setRunning(true); // start from 0 on first open
        setOpen(true);
    };
    const reset = () => {
        setElapsed(0);
        setRunning(false);
    };
    const active = running || elapsed > 0;

    return (
        <>
            <button
                type="button"
                onClick={openModal}
                className={`flex w-full items-center justify-center gap-2 rounded-2xl border py-3.5 text-sm font-bold transition-colors ${
                    active ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-600'
                }`}
            >
                <Timer size={18} />
                {active ? <span className="tabular-nums">{mmss(elapsed)}</span> : 'Chrono'}
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 sm:items-center" onClick={() => setOpen(false)}>
                    <div className="w-full max-w-sm rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
                        <p className="text-center text-xs font-semibold uppercase tracking-wide text-neutral-400">Chrono</p>
                        <p className="mb-6 mt-1 text-center text-5xl font-bold tabular-nums text-neutral-900">{mmss(elapsed)}</p>
                        <div className="flex gap-2">
                            {running ? (
                                <button
                                    type="button"
                                    onClick={() => setRunning(false)}
                                    className="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-neutral-900 py-2.5 text-sm font-semibold text-white"
                                >
                                    <Square size={15} /> Arrêter
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setRunning(true)}
                                    className="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand-600 py-2.5 text-sm font-semibold text-white"
                                >
                                    <Play size={15} /> Démarrer
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={reset}
                                title="Remettre à zéro"
                                className="flex items-center justify-center rounded-xl border border-neutral-200 px-4 text-neutral-500 hover:bg-neutral-50"
                            >
                                <RotateCcw size={16} />
                            </button>
                        </div>
                        <button type="button" onClick={() => setOpen(false)} className="mt-3 w-full py-2 text-sm text-neutral-500 hover:text-neutral-700">
                            Fermer
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

export default function MuscuSession({ catalog, muscles, equipments, session, lastByExercise }: Props) {
    const [date, setDate] = useState(session?.date ?? today());
    const [title, setTitle] = useState(session?.title ?? '');
    const [done, setDone] = useState(session?.status === 'DONE');
    const [items, setItems] = useState<Item[]>(session ? itemsFromServer(session.exercises) : []);
    const [saving, setSaving] = useState(false);
    const [started, setStarted] = useState(false);
    const [elapsed, setElapsed] = useState(0);

    // Session chrono runs automatically once started.
    useEffect(() => {
        if (!started) return;
        const t = setInterval(() => setElapsed((s) => s + 1), 1000);
        return () => clearInterval(t);
    }, [started]);

    const post = (status: 'PLANNED' | 'DONE', duration: number | null) => {
        setSaving(true);
        router.post(
            '/muscu/agenda',
            { id: session?.id ?? null, date, title, note: '', status, templateId: session?.templateId ?? null, durationSeconds: duration, exercises: items },
            {
                onError: (errors) => toast.error(Object.values(errors)[0] ?? "Impossible d'enregistrer la séance."),
                onFinish: () => setSaving(false),
            },
        );
    };

    const removeFromAgenda = () => {
        if (session && confirm("Retirer cette séance de l'agenda ?")) {
            router.post(`/muscu/agenda/${session.id}/supprimer`, {}, { preserveScroll: true });
        }
    };

    const totalSets = items.reduce((n, it) => n + it.sets.filter((s) => !s.is_warmup).length, 0);
    const canStart = session !== null && !started && session.status !== 'DONE';

    return (
        <>
            <Head title={title || 'Séance'} />

            <div className="mb-4 flex items-center gap-2">
                <Link href="/muscu" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ArrowLeft size={18} />
                </Link>
                <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Titre de la séance"
                    className="flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-lg font-bold text-neutral-900 placeholder:text-neutral-300 focus:border-neutral-200 focus:outline-none"
                />
                {session && !started && (
                    <button onClick={removeFromAgenda} title="Retirer de l'agenda" className="shrink-0 rounded-lg p-2 text-neutral-400 hover:bg-rose-50 hover:text-rose-500">
                        <Trash2 size={18} />
                    </button>
                )}
            </div>

            {started ? (
                <div className="mb-4">
                    <SessionChrono />
                </div>
            ) : (
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <input
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                        className="rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 focus:border-neutral-400 focus:outline-none"
                    />
                    <button
                        onClick={() => setDone((d) => !d)}
                        className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors ${
                            done ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-500'
                        }`}
                    >
                        <CircleCheck size={15} /> {done ? 'Fait' : 'Prévu'}
                    </button>
                </div>
            )}

            {canStart && (
                <button
                    onClick={() => {
                        setItems((prev) => prev.map((it) => ({ ...it, sets: it.sets.map((s) => ({ ...s, done: false })) })));
                        setStarted(true);
                    }}
                    className="mb-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 py-3.5 text-sm font-bold text-white shadow-md shadow-brand-500/25 transition-transform hover:-translate-y-0.5"
                >
                    <Play size={18} /> Démarrer la séance
                </button>
            )}

            <div className="pb-28">
                <ExerciseEditor items={items} setItems={setItems} catalog={catalog} muscles={muscles} equipments={equipments} lastByExercise={lastByExercise} execution={started} />
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 p-3 backdrop-blur">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
                    <span className="text-sm text-neutral-500">
                        {items.length} exo{items.length > 1 ? 's' : ''} · {totalSets} série{totalSets > 1 ? 's' : ''}
                    </span>
                    {started ? (
                        <button
                            onClick={() => post('DONE', elapsed)}
                            disabled={saving || items.length === 0}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-40"
                        >
                            <Flag size={16} /> {saving ? 'Enregistrement…' : 'Terminer la séance'}
                        </button>
                    ) : (
                        <button
                            onClick={() => post(done ? 'DONE' : 'PLANNED', null)}
                            disabled={saving || items.length === 0}
                            className="inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-40"
                        >
                            <Check size={16} /> {saving ? 'Enregistrement…' : 'Enregistrer'}
                        </button>
                    )}
                </div>
            </div>
        </>
    );
}

MuscuSession.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
