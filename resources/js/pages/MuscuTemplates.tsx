import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Dumbbell, Pencil, Plus, Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { useConfirm } from '@/components/ConfirmDialog';

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
    const { confirm, node: confirmNode } = useConfirm();

    const remove = async (t: Template) => {
        const message =
            t.usageCount > 0
                ? `Elle est posée ${t.usageCount}× sur ton agenda. Ces séances gardent leur copie, mais tu ne pourras plus la reposer sur de nouveaux jours.`
                : 'Ce modèle de séance sera supprimé.';
        if (await confirm({ title: `Supprimer « ${t.name} » ?`, message, confirmLabel: 'Supprimer' })) {
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
                <div>
                    {templates.map((t) => (
                        <div key={t.id} className="flex items-start gap-3 border-b border-neutral-200 py-4 last:border-b-0">
                            <Link href={`/muscu/seances/${t.id}/modifier`} className="flex min-w-0 flex-1 items-start gap-3 text-left transition-colors hover:opacity-70">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                                    <Dumbbell size={18} />
                                </span>
                                <div className="min-w-0 flex-1">
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
                                {t.exerciseNames.length > 0 && (
                                    <p className="mt-1 line-clamp-2 text-sm text-neutral-500">{t.exerciseNames.join(' · ')}</p>
                                )}
                                </div>
                            </Link>
                            <div className="flex shrink-0 items-center gap-1">
                                <Link href={`/muscu/seances/${t.id}/modifier`} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700">
                                    <Pencil size={15} />
                                </Link>
                                <button onClick={() => remove(t)} className="rounded-lg p-1.5 text-neutral-400 hover:bg-rose-50 hover:text-rose-500">
                                    <Trash2 size={15} />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
            {confirmNode}
        </>
    );
}

MuscuTemplates.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
