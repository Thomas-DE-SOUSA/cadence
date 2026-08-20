import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CalendarDays } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PagePlaceholder } from '@/components/PagePlaceholder';

export default function Program() {
    return (
        <>
            <Head title="Programme" />
            <PagePlaceholder
                title="Programme"
                description="Ton plan Odysséa : blocs, cycles C1–C4 et séances prévues, avec le comparatif prévu / réalisé."
                icon={CalendarDays}
            />
        </>
    );
}

Program.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
