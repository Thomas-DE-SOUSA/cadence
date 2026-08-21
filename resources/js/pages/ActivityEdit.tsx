import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import type { Activity } from '@/types';

interface Props {
    activity: Activity;
}

interface SplitRow {
    index: number;
    distance_meters: number;
    duration: string;
    elevation_meters: number;
}

interface EffortRow {
    label: string;
    distance_meters: number;
    duration: string;
    is_personal_record: boolean;
}

function parseTimeToSeconds(value: string): number {
    return value.split(':').reduce((total, part) => total * 60 + (parseInt(part, 10) || 0), 0);
}

function secondsToTime(total: number): string {
    return `${Math.floor(total / 60)}:${(total % 60).toString().padStart(2, '0')}`;
}

const inputClass =
    'w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-neutral-100 outline-none focus:border-lime-400/60';
const smallInput =
    'w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-neutral-100 outline-none focus:border-lime-400/60';

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
        splits: activity.splits.map<SplitRow>((s) => ({
            index: s.index,
            distance_meters: s.distanceMeters,
            duration: secondsToTime(s.durationSeconds),
            elevation_meters: s.elevationMeters,
        })),
        best_efforts: activity.bestEfforts.map<EffortRow>((b) => ({
            label: b.label,
            distance_meters: b.distanceMeters,
            duration: secondsToTime(b.durationSeconds),
            is_personal_record: b.isPersonalRecord,
        })),
    });

    function setSplit(i: number, patch: Partial<SplitRow>) {
        form.setData(
            'splits',
            form.data.splits.map((s, idx) => (idx === i ? { ...s, ...patch } : s)),
        );
    }
    function addSplit() {
        form.setData('splits', [
            ...form.data.splits,
            { index: form.data.splits.length + 1, distance_meters: 1000, duration: '0:00', elevation_meters: 0 },
        ]);
    }
    function removeSplit(i: number) {
        form.setData(
            'splits',
            form.data.splits.filter((_, idx) => idx !== i),
        );
    }

    function setEffort(i: number, patch: Partial<EffortRow>) {
        form.setData(
            'best_efforts',
            form.data.best_efforts.map((b, idx) => (idx === i ? { ...b, ...patch } : b)),
        );
    }
    function addEffort() {
        form.setData('best_efforts', [
            ...form.data.best_efforts,
            { label: '', distance_meters: 5000, duration: '0:00', is_personal_record: false },
        ]);
    }
    function removeEffort(i: number) {
        form.setData(
            'best_efforts',
            form.data.best_efforts.filter((_, idx) => idx !== i),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form
            .transform((d) => ({
                occurred_at: new Date(d.occurred_at).toISOString(),
                distance_meters: Math.round(parseFloat(d.distance_km || '0') * 1000),
                moving_seconds: parseTimeToSeconds(d.moving_time),
                elapsed_seconds: parseTimeToSeconds(d.elapsed_time || d.moving_time),
                elevation_gain_meters: parseInt(d.elevation_gain_meters || '0', 10),
                splits: d.splits.map((s) => ({
                    index: Number(s.index),
                    distance_meters: Number(s.distance_meters),
                    duration_seconds: parseTimeToSeconds(s.duration),
                    elevation_meters: Number(s.elevation_meters),
                })),
                best_efforts: d.best_efforts.map((b) => ({
                    label: b.label,
                    distance_meters: Number(b.distance_meters),
                    duration_seconds: parseTimeToSeconds(b.duration),
                    is_personal_record: b.is_personal_record,
                })),
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

            {errors.length > 0 && (
                <ul className="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="space-y-6">
                <Card title="Sortie">
                    <div className="space-y-4">
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
                    </div>
                </Card>

                <Card title="Splits kilométriques">
                    <div className="space-y-2">
                        {form.data.splits.map((split, i) => (
                            <div key={i} className="grid grid-cols-[3rem_1fr_1fr_1fr_2rem] items-center gap-2">
                                <input
                                    type="number"
                                    value={split.index}
                                    onChange={(e) => setSplit(i, { index: Number(e.target.value) })}
                                    className={smallInput}
                                    aria-label="Numéro"
                                />
                                <input
                                    type="number"
                                    value={split.distance_meters}
                                    onChange={(e) => setSplit(i, { distance_meters: Number(e.target.value) })}
                                    className={smallInput}
                                    placeholder="m"
                                    aria-label="Distance (m)"
                                />
                                <input
                                    type="text"
                                    value={split.duration}
                                    onChange={(e) => setSplit(i, { duration: e.target.value })}
                                    className={smallInput}
                                    placeholder="mm:ss"
                                    aria-label="Temps"
                                />
                                <input
                                    type="number"
                                    value={split.elevation_meters}
                                    onChange={(e) => setSplit(i, { elevation_meters: Number(e.target.value) })}
                                    className={smallInput}
                                    placeholder="D+"
                                    aria-label="Dénivelé (m)"
                                />
                                <button
                                    type="button"
                                    onClick={() => removeSplit(i)}
                                    className="flex justify-center text-neutral-500 hover:text-red-400"
                                    aria-label="Supprimer le split"
                                >
                                    <X size={16} />
                                </button>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={addSplit}
                            className="inline-flex items-center gap-1 text-sm text-lime-300 hover:text-lime-200"
                        >
                            <Plus size={15} /> Ajouter un split
                        </button>
                    </div>
                </Card>

                <Card title="Meilleurs efforts">
                    <div className="space-y-2">
                        {form.data.best_efforts.map((effort, i) => (
                            <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto_2rem] items-center gap-2">
                                <input
                                    type="text"
                                    value={effort.label}
                                    onChange={(e) => setEffort(i, { label: e.target.value })}
                                    className={smallInput}
                                    placeholder="5 km"
                                    aria-label="Libellé"
                                />
                                <input
                                    type="number"
                                    value={effort.distance_meters}
                                    onChange={(e) => setEffort(i, { distance_meters: Number(e.target.value) })}
                                    className={smallInput}
                                    placeholder="m"
                                    aria-label="Distance (m)"
                                />
                                <input
                                    type="text"
                                    value={effort.duration}
                                    onChange={(e) => setEffort(i, { duration: e.target.value })}
                                    className={smallInput}
                                    placeholder="mm:ss"
                                    aria-label="Temps"
                                />
                                <label className="flex items-center gap-1 text-xs text-neutral-400">
                                    <input
                                        type="checkbox"
                                        checked={effort.is_personal_record}
                                        onChange={(e) => setEffort(i, { is_personal_record: e.target.checked })}
                                    />
                                    RP
                                </label>
                                <button
                                    type="button"
                                    onClick={() => removeEffort(i)}
                                    className="flex justify-center text-neutral-500 hover:text-red-400"
                                    aria-label="Supprimer l'effort"
                                >
                                    <X size={16} />
                                </button>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={addEffort}
                            className="inline-flex items-center gap-1 text-sm text-lime-300 hover:text-lime-200"
                        >
                            <Plus size={15} /> Ajouter un effort
                        </button>
                    </div>
                </Card>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full rounded-lg bg-lime-400 px-4 py-2.5 font-medium text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                >
                    Enregistrer les modifications
                </button>
            </form>
        </>
    );
}

ActivityEdit.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
