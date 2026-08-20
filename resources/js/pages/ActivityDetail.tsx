import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
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
    return (
        <>
            <Head title="Activité" />
            <Link
                href="/historique"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> Historique
            </Link>

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
