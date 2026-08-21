import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

function parseTimeToSeconds(value: string): number {
    const parts = value.split(':').map((p) => parseInt(p, 10) || 0);
    return parts.reduce((total, part) => total * 60 + part, 0);
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">{label}</span>
            {children}
        </label>
    );
}

const inputClass =
    'w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-neutral-900 outline-none focus:border-brand-500/60';

export default function ActivityForm() {
    const form = useForm({
        occurredAt: '',
        distanceKm: '',
        movingTime: '',
        elapsedTime: '',
        elevationGainMeters: '0',
        source: 'MANUAL',
    });

    const pasteForm = useForm({ text: '', occurred_at: '' });

    function submitPaste(event: FormEvent) {
        event.preventDefault();
        pasteForm.post('/activites/importer-texte');
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            occurred_at: new Date(data.occurredAt).toISOString(),
            source: data.source,
            distance_meters: Math.round(parseFloat(data.distanceKm || '0') * 1000),
            moving_seconds: parseTimeToSeconds(data.movingTime),
            elapsed_seconds: parseTimeToSeconds(data.elapsedTime || data.movingTime),
            elevation_gain_meters: parseInt(data.elevationGainMeters || '0', 10),
        }));
        form.post('/activites');
    }

    const errors = Object.values(form.errors);

    return (
        <>
            <Head title="Nouvelle activité" />
            <Link
                href="/historique"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900"
            >
                <ArrowLeft size={16} /> Historique
            </Link>
            <h1 className="mb-6 text-xl font-bold tracking-tight">Nouvelle activité</h1>

            <div className="mb-6">
                <Card title="Coller depuis Strava (IA)">
                    {pasteForm.errors.text && (
                        <p className="mb-2 text-sm text-red-600">{pasteForm.errors.text}</p>
                    )}
                    <form onSubmit={submitPaste} className="space-y-3">
                        <textarea
                            value={pasteForm.data.text}
                            onChange={(e) => pasteForm.setData('text', e.target.value)}
                            rows={6}
                            placeholder="Colle ici le résumé de ta sortie Strava (distance, temps, splits km, meilleurs efforts)…"
                            className={inputClass}
                        />
                        <div>
                            <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">
                                Date (optionnel — sinon devinée par l'IA)
                            </span>
                            <input
                                type="date"
                                value={pasteForm.data.occurred_at}
                                onChange={(e) => pasteForm.setData('occurred_at', e.target.value)}
                                className={inputClass}
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={pasteForm.processing}
                            className="w-full rounded-lg bg-brand-500 px-4 py-2.5 font-medium text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                        >
                            {pasteForm.processing ? 'Analyse en cours…' : "Importer avec l'IA"}
                        </button>
                    </form>
                    <p className="mt-2 text-xs text-neutral-500">
                        Claude lit le texte et remplit automatiquement distance, temps, splits et meilleurs efforts.
                    </p>
                </Card>
            </div>

            <Card title="Ou saisie manuelle">
                {errors.length > 0 && (
                    <ul className="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-600">
                        {errors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <Field label="Date">
                        <input
                            type="date"
                            value={form.data.occurredAt}
                            onChange={(e) => form.setData('occurredAt', e.target.value)}
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
                                placeholder="10,01"
                                value={form.data.distanceKm}
                                onChange={(e) => form.setData('distanceKm', e.target.value)}
                                className={inputClass}
                                required
                            />
                        </Field>
                        <Field label="Dénivelé + (m)">
                            <input
                                type="number"
                                value={form.data.elevationGainMeters}
                                onChange={(e) => form.setData('elevationGainMeters', e.target.value)}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Temps de déplacement (mm:ss)">
                            <input
                                type="text"
                                inputMode="numeric"
                                placeholder="42:35"
                                value={form.data.movingTime}
                                onChange={(e) => form.setData('movingTime', e.target.value)}
                                className={inputClass}
                                required
                            />
                        </Field>
                        <Field label="Temps écoulé (mm:ss)">
                            <input
                                type="text"
                                inputMode="numeric"
                                placeholder="44:13"
                                value={form.data.elapsedTime}
                                onChange={(e) => form.setData('elapsedTime', e.target.value)}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <Field label="Source">
                        <select
                            value={form.data.source}
                            onChange={(e) => form.setData('source', e.target.value)}
                            className={inputClass}
                        >
                            <option value="MANUAL">Manuel</option>
                            <option value="STRAVA">Strava</option>
                        </select>
                    </Field>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded-lg bg-brand-500 px-4 py-2.5 font-medium text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                    >
                        Enregistrer
                    </button>
                </form>
            </Card>
        </>
    );
}

ActivityForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
