import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check, CircleCheck } from 'lucide-react';
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

export default function MuscuSession({ catalog, muscles, equipments, session, lastByExercise }: Props) {
    const [date, setDate] = useState(session?.date ?? today());
    const [title, setTitle] = useState(session?.title ?? '');
    const [done, setDone] = useState(session?.status === 'DONE');
    const [items, setItems] = useState<Item[]>(session ? itemsFromServer(session.exercises) : []);
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.post(
            '/muscu/agenda',
            {
                id: session?.id ?? null,
                date,
                title,
                note: '',
                status: done ? 'DONE' : 'PLANNED',
                templateId: session?.templateId ?? null,
                exercises: items,
            },
            { onFinish: () => setSaving(false) },
        );
    };

    const totalSets = items.reduce((n, it) => n + it.sets.filter((s) => !s.is_warmup).length, 0);

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
            </div>

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

            <div className="pb-28">
                <ExerciseEditor items={items} setItems={setItems} catalog={catalog} muscles={muscles} equipments={equipments} lastByExercise={lastByExercise} />
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 p-3 backdrop-blur">
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
        </>
    );
}

MuscuSession.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
