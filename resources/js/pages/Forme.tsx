import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { Activity, BatteryCharging, BatteryLow, HeartPulse, Scale, ShieldAlert, Sparkles, TrendingUp } from 'lucide-react';
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

interface Props {
    load: {
        hasData: boolean;
        form?: number;
        fitness?: number;
        fatigue?: number;
        acwr?: number;
        series?: SeriesPoint[];
        zones?: { easy: number; moderate: number; hard: number; total: number };
    };
    adaptation?: Adaptation | null;
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

export default function Forme({ load, adaptation }: Props) {
    if (!load.hasData || !load.series || !load.zones) {
        return (
            <>
                <Head title="Forme & charge" />
                <PagePlaceholder
                    title="Forme & charge"
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

            {adaptation && <RecommendationCard a={adaptation} />}

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
