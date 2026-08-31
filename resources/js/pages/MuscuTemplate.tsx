import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/AppLayout';
import { ExerciseEditor, itemsFromServer, type CatalogItem, type Item, type Option, type SetRow } from '@/muscu/ExerciseEditor';

interface TemplateData {
    id: string;
    name: string;
    exercises: { exercise_id: string; name: string; note?: string; per_side?: boolean; superset_group?: number | null; sets: SetRow[] }[];
}
interface Props {
    catalog: CatalogItem[];
    muscles: Option[];
    equipments: Option[];
    template: TemplateData | null;
}

export default function MuscuTemplate({ catalog, muscles, equipments, template }: Props) {
    const [name, setName] = useState(template?.name ?? '');
    const [items, setItems] = useState<Item[]>(template ? itemsFromServer(template.exercises) : []);
    const [saving, setSaving] = useState(false);
    const [nameError, setNameError] = useState(false);
    const nameRef = useRef<HTMLInputElement>(null);

    const save = () => {
        if (name.trim() === '') {
            setNameError(true);
            nameRef.current?.focus();
            return;
        }
        setSaving(true);
        router.post(
            '/muscu/seances',
            { id: template?.id ?? null, name, exercises: items },
            {
                onError: (errors) => toast.error(Object.values(errors)[0] ?? "Impossible d'enregistrer la séance."),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={name || 'Nouvelle séance'} />

            <div className="mb-4 flex items-center gap-2">
                <Link href="/muscu/seances" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ArrowLeft size={18} />
                </Link>
                <input
                    ref={nameRef}
                    value={name}
                    onChange={(e) => {
                        setName(e.target.value);
                        if (nameError) setNameError(false);
                    }}
                    placeholder="Nom de la séance (Push A, Jambes…)"
                    className={`flex-1 rounded-lg border bg-transparent px-2 py-1.5 text-lg font-bold text-neutral-900 placeholder:text-neutral-300 focus:outline-none ${
                        nameError ? 'border-rose-300 focus:border-rose-400' : 'border-transparent focus:border-neutral-200'
                    }`}
                />
            </div>

            {nameError ? (
                <p className="mb-4 text-sm font-medium text-rose-600">Donne un nom à ta séance pour pouvoir l'enregistrer.</p>
            ) : (
                <p className="mb-4 text-sm text-neutral-500">Définis les exercices et les séries cibles. Tu poseras ensuite cette séance sur les jours de ton agenda.</p>
            )}

            <div className="pb-28">
                <ExerciseEditor items={items} setItems={setItems} catalog={catalog} muscles={muscles} equipments={equipments} />
            </div>

            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 p-3 backdrop-blur">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-3">
                    <span className="text-sm text-neutral-500">
                        {items.length} exercice{items.length > 1 ? 's' : ''}
                    </span>
                    <button
                        onClick={save}
                        disabled={saving || items.length === 0}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-40"
                    >
                        <Check size={16} /> {saving ? 'Enregistrement…' : 'Enregistrer la séance'}
                    </button>
                </div>
            </div>
        </>
    );
}

MuscuTemplate.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
