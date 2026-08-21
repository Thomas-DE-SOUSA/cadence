import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Activity } from '@/types';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { ActivitySummary } from '@/features/activity/components/ActivitySummary';
import { SplitsChart } from '@/features/activity/components/SplitsChart';
import { BestEfforts } from '@/features/activity/components/BestEfforts';

interface Props {
    activity: Activity | null;
}

export default function Dashboard({ activity }: Props) {
    return (
        <>
            <Head title="Tableau de bord" />
            <h1 className="mb-6 text-xl font-bold tracking-tight">Tableau de bord</h1>

            {activity ? (
                <div className="space-y-6">
                    <Card title="Dernière sortie">
                        <ActivitySummary activity={activity} />
                    </Card>
                    <Card title="Splits kilométriques">
                        <SplitsChart splits={activity.splits} />
                    </Card>
                    <Card title="Meilleurs efforts">
                        <BestEfforts efforts={activity.bestEfforts} />
                    </Card>
                </div>
            ) : (
                <div className="rounded-xl border border-dashed border-neutral-200 p-10 text-center text-neutral-500">
                    Aucune activité enregistrée pour l'instant.
                </div>
            )}
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
