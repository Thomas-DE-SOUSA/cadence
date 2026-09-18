import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { CalendarPlus, ChevronLeft, ChevronRight, CircleCheck, Dumbbell, Plus, Trash2, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { useConfirm } from '@/components/ConfirmDialog';

interface DaySession {
    id: string;
    title: string;
    status: 'PLANNED' | 'DONE';
    durationSeconds: number | null;
}

/** Session length for the agenda: "45 min", "1 h 05". */
function formatDuration(sec: number): string {
    const m = Math.round(sec / 60);
    if (m < 1) return `${sec}s`;
    if (m < 60) return `${m} min`;
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return mm ? `${h} h ${String(mm).padStart(2, '0')}` : `${h} h`;
}
interface Day {
    date: string;
    dayLabel: string;
    isToday: boolean;
    sessions: DaySession[];
}
interface TemplateOpt {
    id: string;
    name: string;
    exerciseCount: number;
}
interface Props {
    weekLabel: string;
    weekOffset: number;
    days: Day[];
    templates: TemplateOpt[];
}

function PlacePicker({ date, templates, onClose }: { date: string; templates: TemplateOpt[]; onClose: () => void }) {
    const place = (templateId: string) => {
        router.post('/muscu/agenda/planifier', { templateId, date }, { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/40 sm:items-center" onClick={onClose}>
            <div className="flex max-h-[80vh] w-full max-w-md flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-neutral-100 p-4">
                    <p className="font-bold text-neutral-900">Poser une séance</p>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100">
                        <X size={18} />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto p-2">
                    {templates.length === 0 ? (
                        <div className="px-3 py-8 text-center">
                            <p className="text-sm text-neutral-500">Aucune séance-modèle pour l'instant.</p>
                            <Link href="/muscu/seances/nouveau" className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white">
                                <Plus size={15} /> Créer une séance
                            </Link>
                        </div>
                    ) : (
                        templates.map((t) => (
                            <button key={t.id} onClick={() => place(t.id)} className="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition hover:bg-neutral-50">
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                                    <Dumbbell size={16} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-neutral-800">{t.name}</p>
                                    <p className="text-xs text-neutral-400">{t.exerciseCount} exercice{t.exerciseCount > 1 ? 's' : ''}</p>
                                </div>
                                <Plus size={16} className="shrink-0 text-brand-600" />
                            </button>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

export default function MuscuAgenda({ weekLabel, weekOffset, days, templates }: Props) {
    const [placeDate, setPlaceDate] = useState<string | null>(null);
    const { confirm, node: confirmNode } = useConfirm();

    const removeSession = async (id: string, title: string) => {
        if (await confirm({ title: 'Retirer cette séance ?', message: `« ${title || 'la séance'} » sera retirée de ton agenda.`, confirmLabel: 'Retirer' })) {
            router.post(`/muscu/agenda/${id}/supprimer`, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Agenda muscu" />
            <div className="mb-4 flex items-center justify-between gap-3">
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Agenda</h1>
                <Link href="/muscu/seances" className="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-white px-3 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-50">
                    <Dumbbell size={15} /> Mes séances
                </Link>
            </div>

            {/* Week nav */}
            <div className="mb-4 flex items-center justify-between border-b border-neutral-200 pb-3">
                <Link href={`/muscu?week=${weekOffset - 1}`} preserveScroll className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ChevronLeft size={18} />
                </Link>
                <span className="text-sm font-semibold text-neutral-700">{weekLabel}</span>
                <Link href={`/muscu?week=${weekOffset + 1}`} preserveScroll className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ChevronRight size={18} />
                </Link>
            </div>

            <div>
                {days.map((d) => (
                    <div key={d.date} className="mb-5 last:mb-0">
                        <div className={`mb-1 flex items-center justify-between rounded-lg px-3 py-2 ${d.isToday ? 'bg-brand-50' : 'bg-neutral-100/70'}`}>
                            <span className={`flex items-center gap-2 text-sm font-bold capitalize ${d.isToday ? 'text-brand-700' : 'text-neutral-700'}`}>
                                {d.dayLabel}
                                {d.isToday && <span className="rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Auj.</span>}
                            </span>
                            <button onClick={() => setPlaceDate(d.date)} className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-neutral-500 transition hover:bg-white hover:text-brand-600">
                                <CalendarPlus size={14} /> Poser
                            </button>
                        </div>
                        {d.sessions.length === 0 ? (
                            <p className="px-3 py-1 text-xs text-neutral-300">Rien de prévu</p>
                        ) : (
                            <div className="px-1">
                                {d.sessions.map((s) => (
                                    <div key={s.id} className="flex items-center border-b border-neutral-100 last:border-b-0">
                                        <Link
                                            href={`/muscu/agenda/${s.id}`}
                                            className="flex min-w-0 flex-1 items-center gap-2.5 py-3 text-left transition-colors hover:bg-neutral-50"
                                        >
                                            <CircleCheck size={16} className={s.status === 'DONE' ? 'text-brand-600' : 'text-neutral-300'} />
                                            <span className="min-w-0 flex-1 truncate text-sm font-semibold text-neutral-800">{s.title || 'Séance'}</span>
                                            <span className="shrink-0 text-xs text-neutral-400">
                                                {s.status === 'DONE' && s.durationSeconds ? formatDuration(s.durationSeconds) : ''}
                                            </span>
                                            <ChevronRight size={16} className="shrink-0 text-neutral-300" />
                                        </Link>
                                        <button
                                            onClick={() => removeSession(s.id, s.title)}
                                            title="Retirer de l'agenda"
                                            className="shrink-0 px-3 py-3 text-neutral-300 transition hover:text-rose-500"
                                        >
                                            <Trash2 size={15} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {placeDate && <PlacePicker date={placeDate} templates={templates} onClose={() => setPlaceDate(null)} />}
            {confirmNode}
        </>
    );
}

MuscuAgenda.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
