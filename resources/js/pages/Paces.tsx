import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Gauge } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PagePlaceholder } from '@/components/PagePlaceholder';

export default function Paces() {
    return (
        <>
            <Head title="Allures" />
            <PagePlaceholder
                title="Allures"
                description="Tes zones d'allure (récupération → intervalles), recalibrées à partir de tes vraies séances."
                icon={Gauge}
            />
        </>
    );
}

Paces.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
