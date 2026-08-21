import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import type { Activity } from '@/types';

interface Props {
    activity: Activity;
}

function parseTimeToSeconds(value: string): number {
    return value.split(':').reduce((total, part) => total * 60 + (parseInt(part, 10) || 0), 0);
}

function secondsToTime(total: number): string {
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

const inputClass =
    'w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-neutral-100 outline-none focus:border-lime-400/60';

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">{label}</span>
            {children}
        </label>
    );
}

export default function ActivityEdit({ activity }: Props) {
    const form = useForm({
        occurred_at: activity.occurredAt.slice(0, 10),
        distance_km: (activity.distanceMeters / 1000).toString(),
        moving_time: secondsToTime(activity.movingSeconds),
        elapsed_time: secondsToTime(activity.elapsedSeconds),
        elevation_gain_meters: activity.elevationGainMeters.toString(),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form
            .transform((d) => ({
                occurred_at: new Date(d.occurred_at).toISOString(),
                distance_meters: Math.round(parseFloat(d.distance_km || '0') * 1000),
                moving_seconds: parseTimeToSeconds(d.moving_time),
                elapsed_seconds: parseTimeToSeconds(d.elapsed_time || d.moving_time),
                elevation_gain_meters: parseInt(d.elevation_gain_meters || '0', 10),
            }))
            .put(`/activites/${activity.id}`);
    }

    const errors = Object.values(form.errors);

    return (
        <>
            <Head title="Modifier l'activité" />
            <Link
                href={`/activites/${activity.id}`}
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> Retour
            </Link>
            <h1 className="mb-6 text-xl font-bold tracking-tight">Modifier l'activité</h1>

            <Card>
                {errors.length > 0 && (
                    <ul className="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                        {errors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <Field label="Date">
                        <input
                            type="date"
                            value={form.data.occurred_at}
                            onChange={(e) => form.setData('occurred_at', e.target.value)}
                            className={inputClass}
                            required
                        />
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Distance (km)">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.distance_km}
                                onChange={(e) => form.setData('distance_km', e.target.value)}
                                className={inputClass}
                                required
                            />
                        </Field>
                        <Field label="Dénivelé + (m)">
                            <input
                                type="number"
                                value={form.data.elevation_gain_meters}
                                onChange={(e) => form.setData('elevation_gain_meters', e.target.value)}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Temps de déplacement (mm:ss)">
                            <input
                                type="text"
                                inputMode="numeric"
                                value={form.data.moving_time}
                                onChange={(e) => form.setData('moving_time', e.target.value)}
                                className={inputClass}
                                required
                            />
                        </Field>
                        <Field label="Temps écoulé (mm:ss)">
                            <input
                                type="text"
                                inputMode="numeric"
                                value={form.data.elapsed_time}
                                onChange={(e) => form.setData('elapsed_time', e.target.value)}
                                className={inputClass}
                            />
                        </Field>
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded-lg bg-lime-400 px-4 py-2.5 font-medium text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                    >
                        Enregistrer les modifications
                    </button>
                </form>
            </Card>
        </>
    );
}

ActivityEdit.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
