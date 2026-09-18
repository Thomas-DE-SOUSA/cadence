import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { AlertTriangle, ArrowLeft, Check, CircleCheck, Flag, Play, RotateCcw, Square, Timer, Trash2 } from 'lucide-react';
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

interface ChronoState {
    running: boolean;
    startedAt: number | null; // epoch ms of the current running segment
    accumulated: number; // seconds banked from previous segments
}

/**
 * A locally-persisted snapshot of an in-progress session. Written to
 * localStorage on every edit (so validating a set is auto-saved) and restored
 * on return, so an accidental back-navigation, a reload or the PWA being killed
 * never wipes the workout. Cleared once the session is saved to the backend.
 */
interface SessionDraft {
    savedAt: number;
    date: string;
    title: string;
    started: boolean;
    startedAt: number | null; // epoch ms when the session started (for total duration)
    items: Item[];
}

const DRAFT_PREFIX = 'cadence.session-draft.';

function readDraft(key: string): SessionDraft | null {
    try {
        const raw = localStorage.getItem(key);
        if (raw === null) return null;
        const d = JSON.parse(raw) as Partial<SessionDraft>;
        if (Array.isArray(d.items) && typeof d.date === 'string') {
            return {
                savedAt: typeof d.savedAt === 'number' ? d.savedAt : 0,
                date: d.date,
                title: typeof d.title === 'string' ? d.title : '',
                started: d.started === true,
                startedAt: typeof d.startedAt === 'number' ? d.startedAt : null,
                items: d.items as Item[],
            };
        }
    } catch {
        /* ignore corrupt draft */
    }
    return null;
}

/**
 * Manual count-up stopwatch that survives leaving the app. Time is measured from
 * a persisted start timestamp (localStorage), not by counting ticks — so it keeps
 * running while the PWA is backgrounded or closed and restores the real elapsed
 * time on return. A full-width button opens a popup; the button shows the running
 * time instead of the "Chrono" label. Keyed per session so each workout has its own.
 */
function SessionChrono({ storageKey, restartSignal = 0 }: { storageKey: string; restartSignal?: number }) {
    const key = `cadence.chrono.${storageKey}`;
    const [open, setOpen] = useState(false);
    const [state, setState] = useState<ChronoState>(() => {
        try {
            const raw = localStorage.getItem(key);
            if (raw !== null) {
                const s = JSON.parse(raw) as Partial<ChronoState>;
                if (typeof s.accumulated === 'number') {
                    return { running: s.running === true, startedAt: typeof s.startedAt === 'number' ? s.startedAt : null, accumulated: s.accumulated };
                }
            }
        } catch {
            /* ignore corrupt state */
        }
        return { running: false, startedAt: null, accumulated: 0 };
    });
    const [, tick] = useState(0);

    const persist = (next: ChronoState) => {
        setState(next);
        try {
            localStorage.setItem(key, JSON.stringify(next));
        } catch {
            /* ignore */
        }
    };

    // Validating a set acts as a rest timer: (re)start the chrono from zero
    // whenever the signal counter increases. Comparing against the previous
    // value (rather than skipping the first render) fires reliably even if the
    // component remounts mid-session.
    const prevSignal = useRef(restartSignal);
    useEffect(() => {
        if (restartSignal > prevSignal.current) {
            prevSignal.current = restartSignal;
            persist({ running: true, startedAt: Date.now(), accumulated: 0 });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restartSignal]);

    const elapsed = Math.floor(state.accumulated + (state.running && state.startedAt !== null ? (Date.now() - state.startedAt) / 1000 : 0));

    // While running, re-render every second AND when the app regains focus,
    // recomputing from the timestamp — correct even after the browser throttled
    // or suspended timers in the background.
    useEffect(() => {
        if (!state.running) return;
        const t = window.setInterval(() => tick((n) => n + 1), 1000);
        const refresh = () => tick((n) => n + 1);
        document.addEventListener('visibilitychange', refresh);
        window.addEventListener('focus', refresh);
        return () => {
            window.clearInterval(t);
            document.removeEventListener('visibilitychange', refresh);
            window.removeEventListener('focus', refresh);
        };
    }, [state.running]);

    const start = () => persist({ running: true, startedAt: Date.now(), accumulated: state.accumulated });
    const stop = () => persist({ running: false, startedAt: null, accumulated: elapsed });
    const reset = () => persist({ running: false, startedAt: null, accumulated: 0 });
    const openModal = () => {
        if (!state.running && elapsed === 0) start(); // start from 0 on first open
        setOpen(true);
    };
    const active = state.running || elapsed > 0;

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
                            {state.running ? (
                                <button
                                    type="button"
                                    onClick={stop}
                                    className="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-neutral-900 py-2.5 text-sm font-semibold text-white"
                                >
                                    <Square size={15} /> Arrêter
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={start}
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

interface ConfirmOptions {
    title: string;
    message?: string;
    confirmLabel: string;
    tone?: 'danger' | 'brand';
    onConfirm: () => void;
}

/** Styled replacement for the native confirm() dialog, matching the app's sheets. */
function ConfirmDialog({ opts, onClose }: { opts: ConfirmOptions | null; onClose: () => void }) {
    if (opts === null) return null;
    const danger = opts.tone === 'danger';
    return (
        <div className="fixed inset-0 z-[60] flex items-end justify-center bg-neutral-900/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={onClose}>
            <div className="w-full max-w-sm rounded-t-3xl bg-white p-6 shadow-2xl sm:rounded-3xl" onClick={(e) => e.stopPropagation()}>
                <div className={`mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full ${danger ? 'bg-rose-100 text-rose-600' : 'bg-brand-100 text-brand-600'}`}>
                    <AlertTriangle size={22} />
                </div>
                <p className="text-center text-lg font-bold text-neutral-900">{opts.title}</p>
                {opts.message && <p className="mt-1.5 text-center text-sm text-neutral-500">{opts.message}</p>}
                <div className="mt-6 flex flex-col gap-2">
                    <button
                        type="button"
                        onClick={() => {
                            opts.onConfirm();
                            onClose();
                        }}
                        className={`w-full rounded-xl py-3 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 ${danger ? 'bg-rose-600' : 'bg-brand-600'}`}
                    >
                        {opts.confirmLabel}
                    </button>
                    <button type="button" onClick={onClose} className="w-full rounded-xl py-3 text-sm font-semibold text-neutral-600 hover:bg-neutral-100">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function MuscuSession({ catalog, muscles, equipments, session, lastByExercise }: Props) {
    const draftKey = `${DRAFT_PREFIX}${session?.id ?? 'new'}`;
    // Restore a locally-saved draft so returning to a session — even after a
    // reload or an accidental back — never loses the in-progress work. Only a
    // genuinely started run is restored: otherwise merely opening a planned
    // session (never started) would wrongly "restore" itself.
    const [draft] = useState<SessionDraft | null>(() => {
        if (session?.status === 'DONE') return null;
        const d = readDraft(draftKey);
        return d && d.started ? d : null;
    });

    const [date, setDate] = useState(draft?.date ?? session?.date ?? today());
    const [title, setTitle] = useState(draft?.title ?? session?.title ?? '');
    const [done, setDone] = useState(session?.status === 'DONE');
    const [items, setItems] = useState<Item[]>(draft ? draft.items : session ? itemsFromServer(session.exercises) : []);
    const [saving, setSaving] = useState(false);
    // A planned session with any unchecked set is already in progress (its sets
    // were reset to "to-do" when it was started) — resume it instead of showing
    // the "Démarrer" gate, so returning to it never loses the ticked sets.
    const resuming = !!session && session.status === 'PLANNED' && session.exercises.some((e) => e.sets.some((s) => !s.done));
    const [started, setStarted] = useState(draft?.started ?? resuming);
    // Total-session chrono: just the start timestamp. Duration is computed as
    // (now − startedAt) on finish — robust to backgrounding/reload, and never
    // shown during the session (it only feeds the agenda). Not a ticking state.
    const [startedAt, setStartedAt] = useState<number | null>(draft?.startedAt ?? null);
    // Bumped each time a set is validated, to (re)start the rest chrono.
    const [chronoRestart, setChronoRestart] = useState(0);
    const [confirmOpts, setConfirmOpts] = useState<ConfirmOptions | null>(null);

    // Tell the user we brought their session back — doubles as the "alert" so a
    // restore is never silent.
    useEffect(() => {
        if (draft) toast.success('Séance en cours restaurée.');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Stamp the start time the moment the session becomes started (Démarrer, or
    // a resumed session that has no stamp yet).
    useEffect(() => {
        if (started && startedAt === null) setStartedAt(Date.now());
    }, [started, startedAt]);

    // Elapsed seconds since the session started (null if not started).
    const sessionDuration = (): number | null => (startedAt !== null ? Math.max(1, Math.round((Date.now() - startedAt) / 1000)) : null);

    // Persist a draft on every edit of a *started* session — validating a set
    // mutates `items`, so each validated série is auto-saved. A planned session
    // that hasn't been started is never drafted (so it can't self-restore).
    const startedAtRef = useRef(startedAt);
    startedAtRef.current = startedAt;
    useEffect(() => {
        if (!started || items.length === 0) return;
        try {
            const d: SessionDraft = { savedAt: Date.now(), date, title, started, startedAt: startedAtRef.current, items };
            localStorage.setItem(draftKey, JSON.stringify(d));
        } catch {
            /* ignore quota / serialization errors */
        }
    }, [items, title, date, started, draftKey]);

    const clearDraft = () => {
        try {
            localStorage.removeItem(draftKey);
        } catch {
            /* ignore */
        }
    };

    const post = (status: 'PLANNED' | 'DONE', duration: number | null) => {
        setSaving(true);
        router.post(
            '/muscu/agenda',
            { id: session?.id ?? null, date, title, note: '', status, templateId: session?.templateId ?? null, durationSeconds: duration, exercises: items },
            {
                onSuccess: () => clearDraft(),
                onError: (errors) => toast.error(Object.values(errors)[0] ?? "Impossible d'enregistrer la séance."),
                onFinish: () => setSaving(false),
            },
        );
    };

    const removeFromAgenda = () => {
        if (!session) return;
        const id = session.id;
        setConfirmOpts({
            title: 'Retirer cette séance ?',
            message: 'Elle sera retirée de ton agenda.',
            confirmLabel: 'Retirer',
            tone: 'danger',
            onConfirm: () => router.post(`/muscu/agenda/${id}/supprimer`, {}, { preserveScroll: true, onSuccess: () => clearDraft() }),
        });
    };

    // Discard the in-progress run (local draft + rest chrono). The planned
    // session on the server is left untouched.
    const discardRun = () => {
        clearDraft();
        try {
            localStorage.removeItem(`cadence.chrono.${session?.id ?? 'adhoc'}`);
        } catch {
            /* ignore */
        }
    };

    // Ask to confirm leaving a running session, then discard and go back.
    const requestQuit = () =>
        setConfirmOpts({
            title: 'Quitter la séance ?',
            message: 'Votre progression en cours sera perdue.',
            confirmLabel: 'Quitter',
            tone: 'danger',
            onConfirm: () => {
                discardRun();
                router.visit('/muscu');
            },
        });

    // In-app back button.
    const goBack = () => {
        if (started && items.length > 0) requestQuit();
        else router.visit('/muscu');
    };

    // The in-app button can't catch the phone's Android/browser back gesture,
    // which fires a history popstate instead. While a session is running, keep a
    // sentinel history entry so a back press lands here: we re-arm the sentinel
    // (staying on the page) and open the same confirmation dialog.
    useEffect(() => {
        if (!started) return;
        window.history.pushState(window.history.state, '', window.location.href);
        const onPopState = () => {
            window.history.pushState(window.history.state, '', window.location.href);
            requestQuit();
        };
        window.addEventListener('popstate', onPopState);
        return () => window.removeEventListener('popstate', onPopState);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [started]);

    const totalSets = items.reduce((n, it) => n + it.sets.filter((s) => !s.is_warmup).length, 0);
    const canStart = session !== null && !started && session.status !== 'DONE';

    return (
        <>
            <Head title={title || 'Séance'} />

            <div className="mb-4 flex items-center gap-2">
                <button onClick={goBack} title="Retour" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ArrowLeft size={18} />
                </button>
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
                // Sticky just under the fixed header so the chrono stays in view
                // while scrolling. The band uses the page's own background (via
                // bg-page-fixed) so it blends in seamlessly and just hides the
                // scrolling cards — no out-of-place strip. No z-index/blur here
                // on purpose: a stacking context would trap the chrono popup
                // under the bottom action bar.
                <div className="sticky top-16 -mx-4 mb-4 bg-page-fixed px-4 pb-3 pt-1 sm:-mx-6 sm:px-6 md:-mx-8 md:px-8 lg:-mx-10 lg:px-10">
                    <SessionChrono storageKey={session?.id ?? 'adhoc'} restartSignal={chronoRestart} />
                </div>
            ) : session ? (
                <p className="mb-4 text-sm capitalize text-neutral-500">
                    {new Date(date + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}
                </p>
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
                <ExerciseEditor
                    items={items}
                    setItems={setItems}
                    catalog={catalog}
                    muscles={muscles}
                    equipments={equipments}
                    lastByExercise={lastByExercise}
                    execution={started}
                    onSetValidated={() => setChronoRestart((n) => n + 1)}
                />

                {started && items.length > 0 && (
                    <button onClick={goBack} className="mt-2 w-full py-3 text-center text-sm font-semibold text-rose-500 hover:text-rose-600">
                        Quitter la séance
                    </button>
                )}
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 p-3 backdrop-blur">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
                    <span className="text-sm text-neutral-500">
                        {items.length} exo{items.length > 1 ? 's' : ''} · {totalSets} série{totalSets > 1 ? 's' : ''}
                    </span>
                    {started ? (
                        <button
                            onClick={() => post('DONE', sessionDuration())}
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

            <ConfirmDialog opts={confirmOpts} onClose={() => setConfirmOpts(null)} />
        </>
    );
}

MuscuSession.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
