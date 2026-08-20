import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TrendingUp } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PagePlaceholder } from '@/components/PagePlaceholder';

export default function Progression() {
    return (
        <>
            <Head title="Progression" />
            <PagePlaceholder
                title="Progression"
                description="Tes records par distance, tes courbes de progression et le suivi de l'objectif sub-40."
                icon={TrendingUp}
            />
        </>
    );
}

Progression.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
