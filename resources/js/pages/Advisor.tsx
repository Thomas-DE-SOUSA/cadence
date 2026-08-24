import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { DragEvent, ReactNode } from 'react';
import { Gauge, Lightbulb, Loader2, Sparkles, Trophy, UploadCloud } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { formatDuration, formatPace } from '@/features/activity/domain/format';

interface Effort {
    distanceMeters: number;
    label: string;
    seconds: number;
    paceSeconds: number;
}
interface Projection extends Effort {
    measured: boolean;
}
interface Detected {
    runs: { label: string; distanceMeters: number; movingSeconds: number }[];
    efforts: Effort[];
    vdot: number | null;
    projections: Projection[];
}

const CHRONO_DISTANCES: { meters: number; label: string }[] = [
    { meters: 5000, label: '5 km' },
    { meters: 10000, label: '10 km' },
    { meters: 15000, label: '15 km' },
    { meters: 20000, label: '20 km' },
    { meters: 21097, label: 'Semi' },
];

function xsrfToken(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

function parseTime(value: string): number {
    const v = value.trim();
    if (!v) return 0;
    return v.split(':').reduce((total, part) => total * 60 + (parseInt(part, 10) || 0), 0);
}

const inputClass = 'w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-brand-500/60';

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">{label}</span>
            {children}
        </label>
    );
}

/** Minimal Markdown renderer: ## / ### headings, - bullets, **bold**. */
function Markdown({ text }: { text: string }) {
    const inline = (s: string) => s.split('**').map((p, i) => (i % 2 ? <strong key={i}>{p}</strong> : p));
    const out: ReactNode[] = [];
    let bullets: string[] = [];
    const flush = () => {
        if (bullets.length) {
            out.push(
                <ul key={`ul-${out.length}`} className="my-1.5 ml-4 list-disc space-y-1 text-sm text-neutral-700">
                    {bullets.map((b, i) => (
                        <li key={i}>{inline(b)}</li>
                    ))}
                </ul>,
            );
            bullets = [];
        }
    };
    text.split('\n').forEach((raw, i) => {
        const line = raw.trimEnd();
        if (line.startsWith('## ')) {
            flush();
            out.push(
                <h3 key={i} className="mt-4 mb-1.5 text-sm font-bold uppercase tracking-wide text-brand-600">
                    {line.slice(3)}
                </h3>,
            );
        } else if (line.startsWith('### ')) {
            flush();
            out.push(
                <h4 key={i} className="mt-3 mb-1 font-semibold text-neutral-900">
                    {inline(line.slice(4))}
                </h4>,
            );
        } else if (line.startsWith('- ') || line.startsWith('* ')) {
            bullets.push(line.slice(2));
        } else if (line.trim() === '') {
            flush();
        } else {
            flush();
            out.push(
                <p key={i} className="my-1 text-sm leading-relaxed text-neutral-700">
                    {inline(line)}
                </p>,
            );
        }
    });
    flush();
    return <div>{out}</div>;
}

export default function Advisor() {
    const [detected, setDetected] = useState<Detected | null>(null);
    const [analyzing, setAnalyzing] = useState(false);
    const [dragging, setDragging] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const [form, setForm] = useState<Record<string, string>>({
        displayName: '',
        age: '',
        sex: '',
        weightKg: '',
        level: '',
        weeklyKm: '',
        sessionsPerWeek: '',
        goalDistanceKm: '',
        goalTime: '',
        goalDeadline: '',
        injuries: '',
        notes: '',
    });
    const [chronos, setChronos] = useState<Record<string, string>>({});
    const [result, setResult] = useState('');
    const [generating, setGenerating] = useState(false);

    function set(key: string, value: string) {
        setForm((f) => ({ ...f, [key]: value }));
    }

    async function uploadGpx(files: FileList | null) {
        if (!files || files.length === 0) return;
        setAnalyzing(true);
        const fd = new FormData();
        Array.from(files).forEach((f) => fd.append('gpx[]', f));
        try {
            const res = await fetch('/conseil/analyser-gpx', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                body: fd,
            });
            if (res.ok) setDetected(await res.json());
        } finally {
            setAnalyzing(false);
        }
    }

    function onDrop(e: DragEvent<HTMLButtonElement>) {
        e.preventDefault();
        setDragging(false);
        uploadGpx(e.dataTransfer.files);
    }

    async function generate() {
        setResult('');
        setGenerating(true);
        const efforts = (detected?.efforts ?? []).map((e) => ({ distanceMeters: e.distanceMeters, seconds: e.seconds }));
        const chronosArr = Object.entries(chronos)
            .map(([d, v]) => ({ distanceMeters: parseInt(d, 10), seconds: parseTime(v) }))
            .filter((c) => c.seconds > 0);
        try {
            const res = await fetch('/conseil/diagnostic', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ profile: form, efforts, chronos: chronosArr }),
            });
            if (!res.body) return;
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            for (;;) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let idx: number;
                while ((idx = buffer.indexOf('\n\n')) >= 0) {
                    const chunk = buffer.slice(0, idx);
                    buffer = buffer.slice(idx + 2);
                    let event = 'message';
                    let data = '';
                    for (const line of chunk.split('\n')) {
                        if (line.startsWith('event:')) event = line.slice(6).trim();
                        else if (line.startsWith('data:')) data += line.slice(5).trim();
                    }
                    if (!data) continue;
                    const payload = JSON.parse(data);
                    if (event === 'text') setResult((r) => r + payload.t);
                    else if (event === 'error') setResult((r) => r + '\n\n⚠️ ' + payload.message);
                }
            }
        } finally {
            setGenerating(false);
        }
    }

    const hasData = (detected?.efforts.length ?? 0) > 0 || Object.values(chronos).some((v) => v.trim());

    return (
        <>
            <Head title="Conseil" />
            <div className="mb-6 flex items-center gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-md shadow-brand-500/30">
                    <Lightbulb size={22} />
                </span>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Conseil</h1>
                    <p className="text-sm text-neutral-500">État des lieux d'un coureur + plan de progression, par l'IA.</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div className="space-y-5">
                    {/* GPX */}
                    <Card title="1 · Déposer des GPX (facultatif)">
                        <button
                            type="button"
                            onClick={() => fileRef.current?.click()}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDragging(true);
                            }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={onDrop}
                            disabled={analyzing}
                            className={`flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-7 text-center transition-colors ${
                                dragging ? 'border-brand-400 bg-brand-50' : 'border-neutral-300 bg-neutral-50 hover:border-brand-300 hover:bg-brand-50/40'
                            } disabled:opacity-60`}
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-100 text-brand-600">
                                {analyzing ? <Loader2 size={20} className="animate-spin" /> : <UploadCloud size={20} />}
                            </span>
                            <span className="text-sm font-semibold text-neutral-800">
                                {analyzing ? 'Analyse…' : 'Glisse un ou plusieurs .gpx de la personne'}
                            </span>
                            <span className="text-xs text-neutral-500">On en extrait allures, meilleurs efforts et VDOT</span>
                        </button>
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            accept=".gpx,application/gpx+xml,application/xml,text/xml"
                            className="hidden"
                            onChange={(e) => uploadGpx(e.target.files)}
                        />

                        {detected && detected.efforts.length > 0 && (
                            <div className="mt-4 rounded-xl border border-neutral-100 bg-neutral-50/60 p-3">
                                <div className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                    <Gauge size={14} className="text-brand-500" /> Données détectées
                                    {detected.vdot !== null && <span className="rounded-full bg-brand-100 px-2 py-0.5 text-brand-700">VDOT {detected.vdot}</span>}
                                </div>
                                <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                    {detected.projections.map((p) => (
                                        <div key={p.distanceMeters} className="rounded-lg bg-white p-2 text-center shadow-sm shadow-neutral-200/50">
                                            <p className="text-[10px] font-semibold uppercase tracking-wide text-neutral-400">{p.label}</p>
                                            <p className="text-sm font-bold tabular-nums text-neutral-900">{formatDuration(p.seconds)}</p>
                                            <p className="text-[10px] tabular-nums text-neutral-400">
                                                {formatPace(p.paceSeconds)} {p.measured ? '· mesuré' : '· est.'}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </Card>

                    {/* Chronos manuels */}
                    <Card title="2 · Chronos connus (si pas de GPX)">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {CHRONO_DISTANCES.map((d) => (
                                <Field key={d.meters} label={d.label}>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        placeholder="mm:ss"
                                        value={chronos[d.meters] ?? ''}
                                        onChange={(e) => setChronos((c) => ({ ...c, [d.meters]: e.target.value }))}
                                        className={inputClass}
                                    />
                                </Field>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* Questionnaire */}
                <Card title="3 · Profil de la personne">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Prénom / alias">
                            <input value={form.displayName} onChange={(e) => set('displayName', e.target.value)} className={inputClass} placeholder="Alex" />
                        </Field>
                        <Field label="Âge">
                            <input type="number" value={form.age} onChange={(e) => set('age', e.target.value)} className={inputClass} placeholder="35" />
                        </Field>
                        <Field label="Sexe">
                            <select value={form.sex} onChange={(e) => set('sex', e.target.value)} className={inputClass}>
                                <option value="">—</option>
                                <option value="Homme">Homme</option>
                                <option value="Femme">Femme</option>
                            </select>
                        </Field>
                        <Field label="Poids (kg)">
                            <input type="number" value={form.weightKg} onChange={(e) => set('weightKg', e.target.value)} className={inputClass} placeholder="70" />
                        </Field>
                        <Field label="Niveau">
                            <select value={form.level} onChange={(e) => set('level', e.target.value)} className={inputClass}>
                                <option value="">—</option>
                                <option value="Débutant">Débutant</option>
                                <option value="Régulier">Régulier</option>
                                <option value="Confirmé">Confirmé</option>
                            </select>
                        </Field>
                        <Field label="Volume hebdo (km)">
                            <input type="number" value={form.weeklyKm} onChange={(e) => set('weeklyKm', e.target.value)} className={inputClass} placeholder="30" />
                        </Field>
                        <Field label="Séances / semaine">
                            <input type="number" value={form.sessionsPerWeek} onChange={(e) => set('sessionsPerWeek', e.target.value)} className={inputClass} placeholder="3" />
                        </Field>
                        <Field label="Distance objectif (km)">
                            <input type="number" value={form.goalDistanceKm} onChange={(e) => set('goalDistanceKm', e.target.value)} className={inputClass} placeholder="10" />
                        </Field>
                        <Field label="Temps visé">
                            <input value={form.goalTime} onChange={(e) => set('goalTime', e.target.value)} className={inputClass} placeholder="45:00" />
                        </Field>
                        <Field label="Échéance">
                            <input value={form.goalDeadline} onChange={(e) => set('goalDeadline', e.target.value)} className={inputClass} placeholder="dans 3 mois" />
                        </Field>
                    </div>
                    <div className="mt-3 space-y-3">
                        <Field label="Blessures / contraintes">
                            <input value={form.injuries} onChange={(e) => set('injuries', e.target.value)} className={inputClass} placeholder="genou sensible, peu de temps…" />
                        </Field>
                        <Field label="Notes libres">
                            <textarea value={form.notes} onChange={(e) => set('notes', e.target.value)} rows={2} className={inputClass} placeholder="Contexte, ressenti, historique…" />
                        </Field>
                    </div>
                </Card>
            </div>

            {/* Generate */}
            <div className="mt-5">
                <button
                    onClick={generate}
                    disabled={generating || !hasData}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-6 py-3 font-semibold text-white shadow-md shadow-brand-500/25 transition-all hover:-translate-y-0.5 disabled:translate-y-0 disabled:opacity-50 sm:w-auto"
                >
                    {generating ? <Loader2 size={18} className="animate-spin" /> : <Sparkles size={18} />}
                    {generating ? 'Diagnostic en cours…' : 'Générer le diagnostic IA'}
                </button>
                {!hasData && <p className="mt-2 text-xs text-neutral-400">Dépose au moins un GPX ou saisis un chrono pour lancer le diagnostic.</p>}
            </div>

            {/* Result */}
            {(result || generating) && (
                <div className="animate-fade-up mt-5">
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                <Trophy size={15} className="text-brand-500" /> Diagnostic & plan de progression
                            </span>
                        }
                    >
                        {result ? <Markdown text={result} /> : null}
                        {generating && <Loader2 size={18} className="mt-2 animate-spin text-brand-500" />}
                    </Card>
                </div>
            )}
        </>
    );
}

Advisor.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
