import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import type { ActivitySummary } from '@/types';
import { formatDate, formatDuration, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface Props {
    activities: ActivitySummary[];
}

export default function History({ activities }: Props) {
    return (
        <>
            <Head title="Historique" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-xl font-bold tracking-tight">Historique</h1>
                <Link
                    href="/activites/nouvelle"
                    className="flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-brand-600"
                >
                    <Plus size={16} /> Nouvelle
                </Link>
            </div>

            {activities.length === 0 ? (
                <div className="rounded-xl border border-dashed border-neutral-200 p-10 text-center text-neutral-500">
                    Aucune activité. Ajoute ta première sortie.
                </div>
            ) : (
                <ul className="space-y-2">
                    {activities.map((activity) => (
                        <li key={activity.id}>
                            <Link
                                href={`/activites/${activity.id}`}
                                className="flex items-center justify-between rounded-xl border border-neutral-200 bg-white px-5 py-4 transition-colors hover:border-neutral-300"
                            >
                                <div>
                                    <div className="font-medium text-neutral-900">
                                        {formatKilometers(activity.distanceMeters)} km
                                    </div>
                                    <div className="text-xs text-neutral-500">
                                        {formatDate(activity.occurredAt)} · {activity.source === 'STRAVA' ? 'Strava' : 'Manuel'}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="tabular-nums text-neutral-800">
                                        {formatDuration(activity.movingSeconds)}
                                    </div>
                                    <div className="text-xs tabular-nums text-brand-600">
                                        {formatPace(activity.averagePaceSecondsPerKm)}
                                    </div>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

History.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
