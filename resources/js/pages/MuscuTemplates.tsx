import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Dumbbell, Pencil, Plus, Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';

interface Template {
    id: string;
    name: string;
    exerciseCount: number;
    exerciseNames: string[];
    usageCount: number;
}
interface Props {
    templates: Template[];
}

export default function MuscuTemplates({ templates }: Props) {
    const remove = (t: Template) => {
        const msg =
            t.usageCount > 0
                ? `⚠️ « ${t.name} » est posée ${t.usageCount} fois sur ton agenda.\n\nSupprimer le modèle n'efface PAS ces séances (elles gardent leur propre copie), mais tu ne pourras plus le reposer sur de nouveaux jours.\n\nSupprimer quand même ?`
                : `Supprimer la séance « ${t.name} » ?`;
        if (confirm(msg)) {
            router.post(`/muscu/seances/${t.id}/supprimer`, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Mes séances" />
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Mes séances</h1>
                    <p className="mt-1 text-sm text-neutral-500">Tes séances-modèles, à poser sur l'agenda autant de fois que tu veux.</p>
                </div>
                <Link href="/muscu/seances/nouveau" className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5">
                    <Plus size={16} /> Séance
                </Link>
            </div>

            {templates.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-200 px-6 py-16 text-center">
                    <Dumbbell size={32} className="mb-3 text-neutral-400" />
                    <p className="max-w-sm text-sm text-neutral-500">Crée ta première séance-modèle (Push, Jambes…). Tu la poseras ensuite sur tes jours d'entraînement.</p>
                    <Link href="/muscu/seances/nouveau" className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5">
                        <Plus size={16} /> Nouvelle séance
                    </Link>
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                    {templates.map((t) => (
                        <div key={t.id} className="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-200/60">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate font-bold text-neutral-900">{t.name}</p>
                                    <p className="flex flex-wrap items-center gap-x-1.5 text-xs text-neutral-400">
                                        <span>
                                            {t.exerciseCount} exercice{t.exerciseCount > 1 ? 's' : ''}
                                        </span>
                                        {t.usageCount > 0 && (
                                            <span className="rounded-full bg-brand-50 px-1.5 py-0.5 font-medium text-brand-600">
                                                posée {t.usageCount}×
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Link href={`/muscu/seances/${t.id}/modifier`} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700">
                                        <Pencil size={15} />
                                    </Link>
                                    <button onClick={() => remove(t)} className="rounded-lg p-1.5 text-neutral-400 hover:bg-rose-50 hover:text-rose-500">
                                        <Trash2 size={15} />
                                    </button>
                                </div>
                            </div>
                            {t.exerciseNames.length > 0 && (
                                <p className="mt-2 line-clamp-2 text-sm text-neutral-500">{t.exerciseNames.join(' · ')}</p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}

MuscuTemplates.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
