import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import {
    Activity,
    AlertTriangle,
    BatteryCharging,
    BatteryLow,
    Check,
    Footprints,
    HeartPulse,
    Moon,
    PencilLine,
    Scale,
    ShieldAlert,
    ShieldCheck,
    Smile,
    Sparkles,
    TrendingUp,
    Zap,
} from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { HelpTip } from '@/components/HelpTip';
import { PagePlaceholder } from '@/components/PagePlaceholder';

const FORM_HELP =
    'La « forme » (TSB) = fitness − fatigue. Positive tu es frais/affûté, très négative tu es fatigué (charge en cours). C’est l’indicateur de fraîcheur.';
const FITNESS_HELP = 'La fitness (CTL) : ta charge d’entraînement moyenne sur 6 semaines. Elle monte quand tu t’entraînes régulièrement.';
const FATIGUE_HELP = 'La fatigue (ATL) : ta charge moyenne sur 7 jours. Elle réagit vite à une grosse séance.';
const ACWR_HELP =
    'Ratio charge aiguë (7 j) / chronique (28 j). 0,8–1,3 = zone sûre. Au-dessus de 1,5 tu montes trop vite → risque de blessure.';
const RATIO_HELP =
    'Répartition de ton temps par intensité sur 4 semaines, d’après tes allures. L’idéal « polarisé » : ~80 % facile, ~20 % dur, très peu de zone grise.';

interface SeriesPoint {
    date: string;
    ctl: number;
    tsb: number;
}

interface Adaptation {
    verdict: 'progress' | 'hold' | 'rebalance' | 'deload';
    headline: string;
    reasons: string[];
    suggestions: string[];
    consigne: string;
    done: number;
    planned: number;
    cycleName: string;
}

interface Readiness {
    score: number;
    level: 'green' | 'amber' | 'red';
    label: string;
    headline: string;
    advice: string;
}

interface CheckIn {
    sleep: number;
    energy: number;
    legs: number;
    motivation: number;
    painLevel: number;
    painLocation: string;
    note: string;
    readiness: Readiness;
}

interface Props {
    load: {
        hasData: boolean;
        reliable?: boolean;
        form?: number;
        fitness?: number;
        fatigue?: number;
        acwr?: number;
        series?: SeriesPoint[];
        zones?: { easy: number; moderate: number; hard: number; total: number };
    };
    adaptation?: Adaptation | null;
    checkin?: CheckIn | null;
}

const VERDICT: Record<Adaptation['verdict'], { label: string; icon: ComponentType<{ size?: number; className?: string }>; ring: string; tint: string; chip: string }> = {
    progress: { label: 'Progresser', icon: TrendingUp, ring: 'border-brand-200', tint: 'text-brand-600', chip: 'bg-brand-500' },
    hold: { label: 'Consolider', icon: Activity, ring: 'border-sky-200', tint: 'text-sky-600', chip: 'bg-sky-500' },
    rebalance: { label: 'Rééquilibrer', icon: Scale, ring: 'border-amber-200', tint: 'text-amber-600', chip: 'bg-amber-500' },
    deload: { label: 'Alléger', icon: BatteryLow, ring: 'border-rose-200', tint: 'text-rose-600', chip: 'bg-rose-500' },
};

function RecommendationCard({ a }: { a: Adaptation }) {
    const v = VERDICT[a.verdict];
    const Icon = v.icon;

    const applyToProgram = () => {
        try {
            sessionStorage.setItem('cadence.adaptationConsigne', a.consigne);
        } catch {
            /* storage may be unavailable */
        }
        router.visit('/programme');
    };

    return (
        <div className={`animate-fade-up mb-4 rounded-2xl border bg-white p-5 shadow-sm shadow-neutral-200/60 ${v.ring}`}>
            <div className="flex items-start gap-3">
                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white ${v.chip}`}>
                    <Icon size={20} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                        <Sparkles size={12} /> Reco de la semaine · {a.done}/{a.planned} séances
                    </p>
                    <p className={`mt-0.5 text-lg font-bold ${v.tint}`}>{a.headline}</p>

                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {a.reasons.map((r) => (
                            <span key={r} className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-500">
                                {r}
                            </span>
                        ))}
                    </div>

                    <ul className="mt-3 space-y-1">
                        {a.suggestions.map((s) => (
                            <li key={s} className="flex items-start gap-2 text-sm text-neutral-600">
                                <span className={`mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full ${v.chip}`} />
                                {s}
                            </li>
                        ))}
                    </ul>

                    <button
                        onClick={applyToProgram}
                        className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5"
                    >
                        <Sparkles size={15} /> Générer le prochain cycle en tenant compte
                    </button>
                </div>
            </div>
        </div>
    );
}

function StatCell({
    icon: Icon,
    tint,
    value,
    label,
    help,
}: {
    icon: ComponentType<{ size?: number; className?: string }>;
    tint: string;
    value: string;
    label: string;
    help?: string;
}) {
    return (
        <div className="flex flex-col items-center gap-1 px-2 py-4 text-center">
            <p className="text-xl font-extrabold leading-none tabular-nums text-neutral-900 sm:text-2xl">{value}</p>
            <p className="flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-neutral-400">
                <Icon size={11} className={tint} />
                {label}
                {help && <HelpTip label={label} text={help} size={12} />}
            </p>
        </div>
    );
}

/** Fitness (CTL) line + form (TSB) area on a shared axis, sized in real pixels. */
function FormChart({ series }: { series: SeriesPoint[] }) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(760);

    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return;
        const ro = new ResizeObserver((entries) => {
            const w = entries[0]?.contentRect.width;
            if (w) setWidth(w);
        });
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const H = 240;
    const padX = 8;
    const padTop = 16;
    const padBottom = 24;
    const W = Math.max(width, 300);
    const plotW = W - padX * 2;
    const plotH = H - padTop - padBottom;

    const vals = series.flatMap((p) => [p.ctl, p.tsb]);
    const maxV = Math.max(...vals, 1);
    const minV = Math.min(...vals, 0);
    const span = Math.max(maxV - minV, 1);

    const y = (v: number) => padTop + (1 - (v - minV) / span) * plotH;
    const x = (i: number) => (series.length <= 1 ? padX + plotW / 2 : padX + (i / (series.length - 1)) * plotW);
    const y0 = y(0);

    const ctlPath = series.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.ctl).toFixed(1)}`).join(' ');
    const tsbLine = series.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.tsb).toFixed(1)}`).join(' ');
    const tsbArea =
        series.length > 1
            ? `${tsbLine} L ${x(series.length - 1).toFixed(1)} ${y0.toFixed(1)} L ${x(0).toFixed(1)} ${y0.toFixed(1)} Z`
            : '';

    return (
        <div ref={wrapRef} className="w-full">
            <svg width={W} height={H} viewBox={`0 0 ${W} ${H}`} className="block">
                <defs>
                    <linearGradient id="form-pos" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stopColor="#1c855a" stopOpacity="0.22" />
                        <stop offset="1" stopColor="#1c855a" stopOpacity="0" />
                    </linearGradient>
                </defs>
                {/* zero line (form reference) */}
                <line x1={padX} y1={y0} x2={W - padX} y2={y0} stroke="#d4d4d8" strokeWidth={1} strokeDasharray="4 4" />
                {tsbArea && <path d={tsbArea} fill="url(#form-pos)" />}
                {series.length > 1 && <path d={tsbLine} fill="none" stroke="#1c855a" strokeWidth={2} />}
                {series.length > 1 && <path d={ctlPath} fill="none" stroke="#0ea5e9" strokeWidth={2.5} strokeDasharray="2 3" />}
                {series.length > 0 && (
                    <>
                        <circle cx={x(series.length - 1)} cy={y(series[series.length - 1].tsb)} r={4} fill="#1c855a" stroke="#fff" strokeWidth={2} />
                        <circle cx={x(series.length - 1)} cy={y(series[series.length - 1].ctl)} r={4} fill="#0ea5e9" stroke="#fff" strokeWidth={2} />
                    </>
                )}
            </svg>
            <div className="mt-2 flex justify-center gap-4 text-[11px] text-neutral-500">
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-2 w-4 rounded-full bg-brand-500" /> Forme
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-0.5 w-4 rounded-full bg-sky-500" /> Fitness
                </span>
            </div>
        </div>
    );
}

const READINESS_STYLE: Record<Readiness['level'], { ring: string; chip: string; tint: string; bg: string; icon: ComponentType<{ size?: number; className?: string }> }> = {
    green: { ring: 'border-brand-200', chip: 'bg-brand-500', tint: 'text-brand-600', bg: 'bg-brand-50', icon: ShieldCheck },
    amber: { ring: 'border-amber-200', chip: 'bg-amber-500', tint: 'text-amber-600', bg: 'bg-amber-50', icon: ShieldAlert },
    red: { ring: 'border-rose-200', chip: 'bg-rose-500', tint: 'text-rose-600', bg: 'bg-rose-50', icon: AlertTriangle },
};

const SENSATIONS: { key: 'sleep' | 'energy' | 'legs' | 'motivation'; label: string; icon: ComponentType<{ size?: number; className?: string }> }[] = [
    { key: 'sleep', label: 'Sommeil', icon: Moon },
    { key: 'energy', label: 'Énergie', icon: Zap },
    { key: 'legs', label: 'Jambes', icon: Footprints },
    { key: 'motivation', label: 'Motivation', icon: Smile },
];

const PAIN_OPTIONS = [
    { value: 0, label: 'Aucune' },
    { value: 1, label: 'Légère' },
    { value: 2, label: 'Modérée' },
    { value: 3, label: 'Limitante' },
];

function SensationRow({
    icon: Icon,
    label,
    value,
    onChange,
}: {
    icon: ComponentType<{ size?: number; className?: string }>;
    label: string;
    value: number;
    onChange: (v: number) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2 text-sm font-medium text-neutral-600">
                <Icon size={15} className="text-neutral-400" /> {label}
            </span>
            <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => onChange(n)}
                        aria-label={`${label} ${n} sur 5`}
                        className={`h-8 w-8 rounded-lg text-sm font-semibold tabular-nums transition ${
                            value === n ? 'bg-neutral-900 text-white shadow-sm' : 'bg-neutral-100 text-neutral-400 hover:bg-neutral-200'
                        }`}
                    >
                        {n}
                    </button>
                ))}
            </div>
        </div>
    );
}

function CheckInCard({ checkin }: { checkin?: CheckIn | null }) {
    const [editing, setEditing] = useState(!checkin);
    const [sleep, setSleep] = useState(checkin?.sleep ?? 3);
    const [energy, setEnergy] = useState(checkin?.energy ?? 3);
    const [legs, setLegs] = useState(checkin?.legs ?? 3);
    const [motivation, setMotivation] = useState(checkin?.motivation ?? 3);
    const [painLevel, setPainLevel] = useState(checkin?.painLevel ?? 0);
    const [painLocation, setPainLocation] = useState(checkin?.painLocation ?? '');
    const [note, setNote] = useState(checkin?.note ?? '');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.post(
            '/forme/check-in',
            { sleep, energy, legs, motivation, painLevel, painLocation, note },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    // Done state — show the readiness verdict with an edit affordance.
    if (checkin && !editing) {
        const r = checkin.readiness;
        const s = READINESS_STYLE[r.level];
        const Icon = s.icon;
        return (
            <div className={`animate-fade-up mb-4 rounded-2xl border bg-white p-5 shadow-sm shadow-neutral-200/60 ${s.ring}`}>
                <div className="flex items-start gap-3">
                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white ${s.chip}`}>
                        <Icon size={20} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                            <HeartPulse size={12} /> Ressenti du jour · readiness {r.score}/100
                        </p>
                        <p className={`mt-0.5 text-lg font-bold ${s.tint}`}>
                            {r.label} — {r.headline}
                        </p>
                        <p className="mt-1 text-sm text-neutral-600">{r.advice}</p>
                        <div className="mt-2 flex flex-wrap gap-1.5 text-[11px] text-neutral-500">
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5">Sommeil {checkin.sleep}/5</span>
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5">Énergie {checkin.energy}/5</span>
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5">Jambes {checkin.legs}/5</span>
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5">Motiv. {checkin.motivation}/5</span>
                            {checkin.painLevel > 0 && (
                                <span className={`rounded-full px-2 py-0.5 font-medium ${s.bg} ${s.tint}`}>
                                    Douleur : {PAIN_OPTIONS[checkin.painLevel].label}
                                    {checkin.painLocation ? ` (${checkin.painLocation})` : ''}
                                </span>
                            )}
                        </div>
                        <button
                            onClick={() => setEditing(true)}
                            className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-semibold text-neutral-600 transition hover:bg-neutral-50"
                        >
                            <PencilLine size={14} /> Modifier
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="animate-fade-up mb-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm shadow-neutral-200/60">
            <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                <HeartPulse size={12} /> Comment tu te sens aujourd'hui ?
            </p>
            <p className="mt-0.5 mb-3 text-sm text-neutral-500">Tes sensations affinent ta forme et guident le coach (1 = très bas, 5 = au top).</p>

            <div className="space-y-2.5">
                {SENSATIONS.map((s) => {
                    const value = { sleep, energy, legs, motivation }[s.key];
                    const setter = { sleep: setSleep, energy: setEnergy, legs: setLegs, motivation: setMotivation }[s.key];
                    return <SensationRow key={s.key} icon={s.icon} label={s.label} value={value} onChange={setter} />;
                })}
            </div>

            <div className="mt-4 border-t border-neutral-100 pt-3">
                <p className="mb-2 text-sm font-medium text-neutral-600">Douleur / gêne ?</p>
                <div className="grid grid-cols-4 gap-1.5">
                    {PAIN_OPTIONS.map((p) => {
                        const active = painLevel === p.value;
                        const danger = p.value >= 2;
                        return (
                            <button
                                key={p.value}
                                type="button"
                                onClick={() => setPainLevel(p.value)}
                                className={`rounded-lg px-2 py-2 text-xs font-semibold transition ${
                                    active
                                        ? danger
                                            ? 'bg-rose-500 text-white shadow-sm'
                                            : 'bg-neutral-900 text-white shadow-sm'
                                        : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                                }`}
                            >
                                {p.label}
                            </button>
                        );
                    })}
                </div>
                {painLevel > 0 && (
                    <input
                        type="text"
                        value={painLocation}
                        onChange={(e) => setPainLocation(e.target.value)}
                        placeholder="Où ? (genou, mollet, tendon d'Achille…)"
                        maxLength={120}
                        className="mt-2 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-400 focus:outline-none"
                    />
                )}
            </div>

            <input
                type="text"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="Une note ? (optionnel)"
                maxLength={500}
                className="mt-3 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-400 focus:outline-none"
            />

            <div className="mt-4 flex items-center gap-2">
                <button
                    onClick={submit}
                    disabled={saving}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50"
                >
                    <Check size={15} /> {saving ? 'Enregistrement…' : checkin ? 'Mettre à jour' : 'Enregistrer mon ressenti'}
                </button>
                {checkin && (
                    <button onClick={() => setEditing(false)} className="rounded-lg px-3 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">
                        Annuler
                    </button>
                )}
            </div>
        </div>
    );
}

function formStatus(tsb: number): { label: string; tint: string } {
    if (tsb > 8) return { label: 'Frais / affûté', tint: 'text-brand-600' };
    if (tsb >= -10) return { label: 'Équilibré', tint: 'text-neutral-500' };
    if (tsb >= -30) return { label: 'En charge', tint: 'text-amber-600' };
    return { label: 'Grosse fatigue', tint: 'text-rose-600' };
}

function acwrStatus(r: number): { label: string; tint: string } {
    if (r === 0) return { label: '—', tint: 'text-neutral-400' };
    if (r < 0.8) return { label: 'Sous-charge', tint: 'text-sky-600' };
    if (r <= 1.3) return { label: 'Zone sûre', tint: 'text-brand-600' };
    if (r <= 1.5) return { label: 'Attention', tint: 'text-amber-600' };
    return { label: 'Risque ↑', tint: 'text-rose-600' };
}

function pct(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 100) : 0;
}

export default function Forme({ load, adaptation, checkin }: Props) {
    if (!load.hasData || !load.series || !load.zones) {
        return (
            <>
                <Head title="Forme & charge" />
                <div className="mb-6">
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Forme &amp; charge</h1>
                    <p className="mt-1 text-sm text-neutral-500">Commence par ton ressenti du jour — tes sorties viendront compléter le tableau.</p>
                </div>
                <CheckInCard checkin={checkin} />
                <PagePlaceholder
                    title="Ta courbe de forme arrive bientôt"
                    description="Enregistre quelques sorties et renseigne ton profil : ta courbe de forme, ta charge et ton équilibre 80/20 apparaîtront ici."
                    icon={Activity}
                />
            </>
        );
    }

    const { form = 0, fitness = 0, fatigue = 0, acwr = 0, series, zones } = load;
    const fs = formStatus(form);
    const as = acwrStatus(acwr);
    const easyPct = pct(zones.easy, zones.total);
    const modPct = pct(zones.moderate, zones.total);
    const hardPct = pct(zones.hard, zones.total);
    const balanced = easyPct >= 78;

    return (
        <>
            <Head title="Forme & charge" />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Forme &amp; charge</h1>
                <p className="mt-1 text-sm text-neutral-500">Ta fraîcheur, ta charge d'entraînement et ton équilibre 80/20.</p>
            </div>

            <CheckInCard checkin={checkin} />

            {adaptation && <RecommendationCard a={adaptation} />}

            {load.reliable === false && (
                <p className="animate-fade-up mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    ⏳ Charge &amp; forme <strong>en calibration</strong> : encore peu d'historique. Le ratio de charge et la forme
                    se stabilisent après ~3 semaines de sorties — d'ici là, on se fie surtout à ton assiduité et à ton 80/20.
                </p>
            )}

            {/* Stat strip */}
            <div className="animate-fade-up mb-4 grid grid-cols-4 divide-x divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm shadow-neutral-200/60">
                <StatCell icon={TrendingUp} tint={fs.tint} value={form > 0 ? `+${form}` : `${form}`} label="Forme" help={FORM_HELP} />
                <StatCell icon={Activity} tint="text-sky-500" value={`${fitness}`} label="Fitness" help={FITNESS_HELP} />
                <StatCell icon={BatteryCharging} tint="text-amber-500" value={`${fatigue}`} label="Fatigue" help={FATIGUE_HELP} />
                <StatCell icon={ShieldAlert} tint={as.tint} value={acwr > 0 ? acwr.toFixed(2) : '—'} label="Ratio charge" help={ACWR_HELP} />
            </div>

            {/* Status line */}
            <div className="animate-fade-up mb-4 flex flex-wrap gap-2" style={{ animationDelay: '60ms' }}>
                <span className={`inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-sm font-semibold ${fs.tint}`}>
                    <HeartPulse size={14} /> {fs.label}
                </span>
                <span className={`inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-sm font-semibold ${as.tint}`}>
                    <ShieldAlert size={14} /> Charge : {as.label}
                </span>
            </div>

            {/* Form curve */}
            <div className="animate-fade-up mb-4" style={{ animationDelay: '90ms' }}>
                <Card
                    title={
                        <span className="inline-flex items-center gap-1.5">
                            Courbe de forme
                            <HelpTip label="Courbe de forme" text={FORM_HELP} />
                        </span>
                    }
                >
                    <FormChart series={series} />
                </Card>
            </div>

            {/* 80/20 distribution */}
            <div className="animate-fade-up" style={{ animationDelay: '120ms' }}>
                <Card
                    title={
                        <span className="inline-flex items-center gap-1.5">
                            Équilibre 80/20
                            <HelpTip label="Équilibre 80/20" text={RATIO_HELP} />
                        </span>
                    }
                >
                    {zones.total === 0 ? (
                        <p className="text-sm text-neutral-400">Pas assez de données récentes pour l'analyse d'intensité.</p>
                    ) : (
                        <>
                            <div className="flex h-4 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div className="bg-brand-500" style={{ width: `${easyPct}%` }} title={`Facile ${easyPct}%`} />
                                <div className="bg-amber-400" style={{ width: `${modPct}%` }} title={`Modéré ${modPct}%`} />
                                <div className="bg-rose-500" style={{ width: `${hardPct}%` }} title={`Dur ${hardPct}%`} />
                            </div>
                            <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p className="text-lg font-extrabold tabular-nums text-brand-600">{easyPct}%</p>
                                    <p className="text-[11px] text-neutral-400">Facile</p>
                                </div>
                                <div>
                                    <p className="text-lg font-extrabold tabular-nums text-amber-600">{modPct}%</p>
                                    <p className="text-[11px] text-neutral-400">Modéré (zone grise)</p>
                                </div>
                                <div>
                                    <p className="text-lg font-extrabold tabular-nums text-rose-600">{hardPct}%</p>
                                    <p className="text-[11px] text-neutral-400">Dur</p>
                                </div>
                            </div>
                            <p className="mt-3 rounded-lg bg-neutral-50 px-3 py-2 text-sm text-neutral-600">
                                {balanced ? (
                                    <>
                                        <span className="font-semibold text-brand-600">Bel équilibre polarisé 🎯</span> — tu tiens ~80 % en facile.
                                    </>
                                ) : (
                                    <>
                                        <span className="font-semibold text-amber-600">Trop d'intensité.</span> Tu es à {easyPct}% facile ; vise ~80 %. Ralentis tes footings faciles.
                                    </>
                                )}
                            </p>
                        </>
                    )}
                </Card>
            </div>
        </>
    );
}

Forme.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
