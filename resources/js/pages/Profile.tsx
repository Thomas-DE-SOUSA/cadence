import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { User } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PagePlaceholder } from '@/components/PagePlaceholder';

export default function Profile() {
    return (
        <>
            <Head title="Profil" />
            <PagePlaceholder
                title="Profil"
                description="Ton profil : âge, gabarit, historique, objectifs et préférences d'entraînement."
                icon={User}
            />
        </>
    );
}

Profile.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
