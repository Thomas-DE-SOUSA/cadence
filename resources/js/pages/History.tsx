import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { History as HistoryIcon } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PagePlaceholder } from '@/components/PagePlaceholder';

export default function History() {
    return (
        <>
            <Head title="Historique" />
            <PagePlaceholder
                title="Historique"
                description="L'historique de toutes tes sorties, filtrable par date et par type de séance."
                icon={HistoryIcon}
            />
        </>
    );
}

History.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
