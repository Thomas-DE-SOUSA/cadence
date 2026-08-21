import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import type { Activity } from '@/types';
import { ActivitySummary } from '@/features/activity/components/ActivitySummary';
import { SplitsChart } from '@/features/activity/components/SplitsChart';
import { BestEfforts } from '@/features/activity/components/BestEfforts';

interface Props {
    activity: Activity;
}

export default function ActivityDetail({ activity }: Props) {
    const del = useForm();

    function remove() {
        if (window.confirm('Supprimer définitivement cette activité ?')) {
            del.delete(`/activites/${activity.id}`);
        }
    }

    return (
        <>
            <Head title="Activité" />
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href="/historique"
                    className="inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900"
                >
                    <ArrowLeft size={16} /> Historique
                </Link>
                <div className="flex items-center gap-2">
                    <Link
                        href={`/activites/${activity.id}/modifier`}
                        className="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-100"
                    >
                        Modifier
                    </Link>
                    <button
                        onClick={remove}
                        disabled={del.processing}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-1.5 text-sm text-red-600 transition-colors hover:bg-red-500/10 disabled:opacity-50"
                    >
                        <Trash2 size={15} /> Supprimer
                    </button>
                </div>
            </div>

            <div className="space-y-6">
                <Card title="Sortie">
                    <ActivitySummary activity={activity} />
                </Card>
                <Card title="Splits kilométriques">
                    <SplitsChart splits={activity.splits} />
                </Card>
                <Card title="Meilleurs efforts">
                    <BestEfforts efforts={activity.bestEfforts} />
                </Card>
            </div>
        </>
    );
}

ActivityDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
