import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Activity } from '@/types';
import { ActivitySummary } from '@/features/activity/components/ActivitySummary';
import { SplitsChart } from '@/features/activity/components/SplitsChart';
import { BestEfforts } from '@/features/activity/components/BestEfforts';

interface Props {
    activity: Activity | null;
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border border-neutral-800 bg-neutral-900/40 p-6">
            <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-400">{title}</h2>
            {children}
        </section>
    );
}

export default function Dashboard({ activity }: Props) {
    return (
        <>
            <Head title="Tableau de bord" />
            <div className="min-h-screen bg-neutral-950 text-neutral-100">
                <div className="mx-auto max-w-3xl px-5 py-10">
                    <header className="mb-8">
                        <h1 className="text-xl font-bold tracking-tight">Cadence</h1>
                        <p className="text-sm text-neutral-500">Objectif sub-40 · Odysséa Paris — 04/10/2026</p>
                    </header>

                    {activity ? (
                        <div className="space-y-6">
                            <Section title="Dernière sortie">
                                <ActivitySummary activity={activity} />
                            </Section>
                            <Section title="Splits kilométriques">
                                <SplitsChart splits={activity.splits} />
                            </Section>
                            <Section title="Meilleurs efforts">
                                <BestEfforts efforts={activity.bestEfforts} />
                            </Section>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-neutral-800 p-10 text-center text-neutral-500">
                            Aucune activité enregistrée pour l'instant.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
