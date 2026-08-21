import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft, CheckCircle2, Circle, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { formatDate, formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface ObjectiveResult {
    id: string;
    label: string;
    type: string;
    achieved: boolean;
    progress: number;
    detail: string;
}

interface AssignedActivity {
    id: string;
    occurredAt: string;
    distanceMeters: number;
    movingSeconds: number;
    averagePaceSecondsPerKm: number;
}

interface AvailableActivity {
    id: string;
    occurredAt: string;
    distanceMeters: number;
}

interface Program {
    id: string;
    name: string;
    goal: string;
    targetRaceName: string;
    targetRaceDate: string | null;
    startDate: string;
    endDate: string | null;
    priority: string;
    status: string;
    objectives: ObjectiveResult[];
    activities: AssignedActivity[];
}

interface Props {
    program: Program;
    available: AvailableActivity[];
}

export default function ProgramDetail({ program, available }: Props) {
    const assign = useForm({ activity_id: available[0]?.id ?? '' });

    function submitAssign(e: FormEvent) {
        e.preventDefault();
        if (assign.data.activity_id) {
            assign.post(`/programme/${program.id}/assigner`, { preserveScroll: true });
        }
    }

    function remove(activityId: string) {
        router.post(`/programme/${program.id}/retirer`, { activity_id: activityId }, { preserveScroll: true });
    }

    const achieved = program.objectives.filter((o) => o.achieved).length;

    return (
        <>
            <Head title={program.name} />
            <Link
                href="/programme"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> Programmes
            </Link>

            <div className="mb-6">
                <h1 className="text-xl font-bold tracking-tight">{program.name}</h1>
                <p className="text-sm text-neutral-500">
                    {program.goal}
                    {program.targetRaceName ? ` · ${program.targetRaceName}` : ''}
                    {program.targetRaceDate ? ` — ${formatDate(program.targetRaceDate)}` : ''}
                </p>
            </div>

            <div className="space-y-6">
                <Card title={`Objectifs — ${achieved}/${program.objectives.length} atteints`}>
                    {program.objectives.length === 0 ? (
                        <p className="text-sm text-neutral-500">Aucun objectif défini.</p>
                    ) : (
                        <ul className="space-y-3">
                            {program.objectives.map((o) => (
                                <li key={o.id}>
                                    <div className="mb-1 flex items-center justify-between">
                                        <span className="flex items-center gap-2 text-sm text-neutral-200">
                                            {o.achieved ? (
                                                <CheckCircle2 size={16} className="text-lime-400" />
                                            ) : (
                                                <Circle size={16} className="text-neutral-600" />
                                            )}
                                            {o.label}
                                        </span>
                                        <span className="text-xs tabular-nums text-neutral-500">
                                            {Math.round(o.progress * 100)}%
                                        </span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded bg-neutral-800">
                                        <div
                                            className={`h-full rounded ${o.achieved ? 'bg-lime-400' : 'bg-orange-400/70'}`}
                                            style={{ width: `${Math.round(o.progress * 100)}%` }}
                                        />
                                    </div>
                                    <p className="mt-1 text-xs text-neutral-500">{o.detail}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Sorties assignées">
                    {available.length > 0 && (
                        <form onSubmit={submitAssign} className="mb-4 flex gap-2">
                            <select
                                value={assign.data.activity_id}
                                onChange={(e) => assign.setData('activity_id', e.target.value)}
                                className="flex-1 rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-sm text-neutral-100 outline-none focus:border-lime-400/60"
                            >
                                {available.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                    </option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                disabled={assign.processing}
                                className="rounded-lg bg-lime-400 px-4 py-2 text-sm font-medium text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                            >
                                Assigner
                            </button>
                        </form>
                    )}

                    {program.activities.length === 0 ? (
                        <p className="text-sm text-neutral-500">Aucune sortie assignée à ce programme.</p>
                    ) : (
                        <ul className="divide-y divide-neutral-800">
                            {program.activities.map((a) => (
                                <li key={a.id} className="flex items-center justify-between py-2.5">
                                    <Link href={`/activites/${a.id}`} className="text-sm text-neutral-200 hover:text-lime-300">
                                        {formatDate(a.occurredAt)} · {formatKilometers(a.distanceMeters)} km
                                    </Link>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs tabular-nums text-neutral-400">
                                            {formatDuration(a.movingSeconds)} · {formatPace(a.averagePaceSecondsPerKm)}
                                        </span>
                                        <button
                                            onClick={() => remove(a.id)}
                                            className="text-neutral-500 hover:text-red-400"
                                            aria-label="Retirer"
                                        >
                                            <X size={16} />
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </>
    );
}

ProgramDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
