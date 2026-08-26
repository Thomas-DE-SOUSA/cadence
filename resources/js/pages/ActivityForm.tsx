import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { DragEvent, FormEvent, ReactNode } from 'react';
import { ArrowLeft, Camera, MapPin, UploadCloud } from 'lucide-react';
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
    const gpxForm = useForm<{ gpx: File | null }>({ gpx: null });
    const photoForm = useForm<{ photo: File | null; occurred_at: string }>({ photo: null, occurred_at: '' });
    const [dragging, setDragging] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const photoRef = useRef<HTMLInputElement>(null);

    function submitPaste(event: FormEvent) {
        event.preventDefault();
        pasteForm.post('/activites/importer-texte');
    }

    function uploadGpx(file: File | undefined) {
        if (!file) return;
        gpxForm.setData('gpx', file);
        gpxForm.post('/activites/importer-gpx', { forceFormData: true });
    }

    function uploadPhoto(file: File | undefined) {
        if (!file) return;
        photoForm.transform((d) => ({ photo: file, occurred_at: d.occurred_at }));
        photoForm.post('/activites/importer-photo', { forceFormData: true });
    }

    function onDrop(e: DragEvent<HTMLButtonElement>) {
        e.preventDefault();
        setDragging(false);
        uploadGpx(e.dataTransfer.files[0]);
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
                href="/"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900"
            >
                <ArrowLeft size={16} /> Tableau de bord
            </Link>
            <h1 className="mb-6 text-2xl font-bold tracking-tight text-neutral-900">Nouvelle activité</h1>

            <div className="mb-6">
                <Card title="Déposer un itinéraire (GPX)">
                    {gpxForm.errors.gpx && <p className="mb-2 text-sm text-red-600">{gpxForm.errors.gpx}</p>}
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        disabled={gpxForm.processing}
                        className={`flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors ${
                            dragging ? 'border-brand-400 bg-brand-50' : 'border-neutral-300 bg-neutral-50 hover:border-brand-300 hover:bg-brand-50/40'
                        } disabled:opacity-60`}
                    >
                        <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-600">
                            {gpxForm.processing ? <MapPin size={22} className="animate-pulse" /> : <UploadCloud size={22} />}
                        </span>
                        <span className="text-sm font-semibold text-neutral-800">
                            {gpxForm.processing ? 'Import du tracé…' : 'Glisse ton fichier .gpx ici'}
                        </span>
                        <span className="text-xs text-neutral-500">
                            distance, temps, splits, dénivelé, carte et profil — remplis automatiquement
                        </span>
                    </button>
                    <input
                        ref={fileRef}
                        type="file"
                        accept=".gpx,application/gpx+xml,application/xml,text/xml"
                        className="hidden"
                        onChange={(e) => uploadGpx(e.target.files?.[0])}
                    />
                </Card>
            </div>

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
                        L'IA lit le texte et remplit automatiquement distance, temps, splits et meilleurs efforts.
                    </p>
                </Card>
            </div>

            <div className="mb-6">
                <Card title="Depuis une photo (IA)">
                    {photoForm.errors.photo && <p className="mb-2 text-sm text-red-600">{photoForm.errors.photo}</p>}
                    <input
                        ref={photoRef}
                        type="file"
                        accept="image/*"
                        capture="environment"
                        className="hidden"
                        onChange={(e) => uploadPhoto(e.target.files?.[0])}
                    />
                    <div className="mb-3">
                        <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">Date (optionnel — sinon lue/devinée)</span>
                        <input
                            type="date"
                            value={photoForm.data.occurred_at}
                            onChange={(e) => photoForm.setData('occurred_at', e.target.value)}
                            className={inputClass}
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => photoRef.current?.click()}
                        disabled={photoForm.processing}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 font-medium text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                    >
                        <Camera size={17} /> {photoForm.processing ? 'Lecture de la photo…' : 'Prendre / choisir une photo'}
                    </button>
                    <p className="mt-2 text-xs text-neutral-500">
                        Photographie l'écran de ta montre, du tapis ou d'une appli — l'IA lit distance, temps et dénivelé.
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
