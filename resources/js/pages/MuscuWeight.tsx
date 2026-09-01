import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Check, Moon, Scale, Sunrise, TrendingUp } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Week {
    weekStart: string;
    avgKg: number;
    count: number;
}
interface Entry {
    date: string;
    moment: string;
    momentLabel: string;
    weightKg: number;
}
interface Props {
    today: string;
    weeks: Week[];
    recent: Entry[];
}

/** Formats a Y-M-D as "25 août" at local midnight (no timezone drift). */
function shortDate(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-');
    return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

const toggleClass = (active: boolean) =>
    `flex flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors ${
        active ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-500'
    }`;

/**
 * Weekly-average curve, styled after the running Progression chart: real-pixel
 * width, area gradient, labelled points and dates. The vertical scale keeps a
 * minimum span (~2 kg) so small week-to-week wobbles stay gentle instead of
 * filling the whole height and looking dramatic.
 */
function WeightChart({ weeks }: { weeks: Week[] }) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(600);

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

    const H = 220;
    const padL = 16;
    const padR = 16;
    const padTop = 30;
    const padBottom = 28;

    const containerW = Math.max(width, 300);
    const minStep = 62;
    const neededW = padL + padR + Math.max(weeks.length - 1, 1) * minStep;
    const W = Math.max(containerW, neededW);
    const scrollable = W > containerW + 1;

    const plotW = W - padL - padR;
    const plotH = H - padTop - padBottom;

    const vals = weeks.map((w) => w.avgKg);
    const minV = Math.min(...vals);
    const maxV = Math.max(...vals);
    // Floor the visible span so a ±0.4 kg wiggle doesn't stretch edge-to-edge.
    const span = Math.max(maxV - minV, 2);
    const mid = (minV + maxV) / 2;
    const top = mid + span / 2;

    const y = (kg: number) => padTop + (0.1 + (0.8 * (top - kg)) / span) * plotH;
    const x = (i: number) => (weeks.length === 1 ? padL + plotW / 2 : padL + (i / (weeks.length - 1)) * plotW);

    // Keep the latest week in view (scroll to the far right) once sized.
    useEffect(() => {
        const el = wrapRef.current;
        if (el) el.scrollLeft = el.scrollWidth;
    }, [W, weeks.length]);

    const linePath = weeks.map((w, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(w.avgKg).toFixed(1)}`).join(' ');
    const areaPath =
        weeks.length > 1
            ? `${linePath} L ${x(weeks.length - 1).toFixed(1)} ${(H - padBottom).toFixed(1)} L ${x(0).toFixed(1)} ${(H - padBottom).toFixed(1)} Z`
            : '';

    return (
        <>
            <div ref={wrapRef} className="w-full overflow-x-auto">
                <svg width={W} height={H} viewBox={`0 0 ${W} ${H}`} className="block">
                    <defs>
                        <linearGradient id="weight-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stopColor="#f26722" stopOpacity="0.16" />
                            <stop offset="1" stopColor="#f26722" stopOpacity="0" />
                        </linearGradient>
                    </defs>

                    <line x1={padL} y1={H - padBottom} x2={W - padR} y2={H - padBottom} stroke="#f1f1f2" strokeWidth={1} />

                    {areaPath && <path d={areaPath} fill="url(#weight-area)" />}
                    {weeks.length > 1 && (
                        <path d={linePath} fill="none" stroke="#f26722" strokeWidth={2.5} strokeLinejoin="round" strokeLinecap="round" />
                    )}

                    {weeks.map((w, i) => (
                        <g key={w.weekStart}>
                            <circle cx={x(i)} cy={y(w.avgKg)} r={4} fill="#f26722" stroke="#fff" strokeWidth={2} />
                            <text x={x(i)} y={y(w.avgKg) - 11} textAnchor="middle" fontSize={12} className="fill-neutral-800 font-bold">
                                {w.avgKg.toFixed(1)}
                            </text>
                            <text x={x(i)} y={H - 9} textAnchor="middle" fontSize={11} className="fill-neutral-400">
                                {shortDate(w.weekStart)}
                            </text>
                        </g>
                    ))}
                </svg>
            </div>
            {scrollable && <p className="mt-1 text-center text-[11px] text-neutral-400">← fais défiler pour parcourir l'historique →</p>}
        </>
    );
}

export default function MuscuWeight({ today, weeks, recent }: Props) {
    const [date, setDate] = useState(today);
    const [moment, setMoment] = useState<'MORNING' | 'EVENING'>('MORNING');
    const [weight, setWeight] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        const kg = Number(weight.replace(',', '.'));
        if (!Number.isFinite(kg) || kg <= 0) {
            toast.error('Entre un poids valide.');
            return;
        }
        setSaving(true);
        router.post(
            '/muscu/poids',
            { date, moment, weightKg: kg },
            {
                preserveScroll: true,
                onError: (e) => toast.error(Object.values(e)[0] ?? "Impossible d'enregistrer la pesée."),
                onSuccess: () => setWeight(''),
                onFinish: () => setSaving(false),
            },
        );
    };

    const overall = weeks.length > 1 ? Number((weeks[weeks.length - 1].avgKg - weeks[0].avgKg).toFixed(1)) : null;

    return (
        <>
            <Head title="Poids" />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Poids</h1>
                <p className="mt-1 text-sm text-neutral-500">Pèse-toi matin et soir ; on compare la moyenne de chaque semaine.</p>
            </div>

            <Card
                title={
                    <span className="inline-flex items-center gap-1.5">
                        <Scale size={15} className="text-brand-600" /> Nouvelle pesée
                    </span>
                }
            >
                <div className="space-y-3">
                    <div className="flex gap-2">
                        <button type="button" onClick={() => setMoment('MORNING')} className={toggleClass(moment === 'MORNING')}>
                            <Sunrise size={15} /> Matin
                        </button>
                        <button type="button" onClick={() => setMoment('EVENING')} className={toggleClass(moment === 'EVENING')}>
                            <Moon size={15} /> Soir
                        </button>
                    </div>
                    <div className="flex gap-2">
                        <input
                            type="date"
                            value={date}
                            max={today}
                            onChange={(e) => setDate(e.target.value)}
                            className="rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-600 focus:border-neutral-400 focus:outline-none"
                        />
                        <div className="relative flex-1">
                            <input
                                inputMode="decimal"
                                value={weight}
                                onChange={(e) => setWeight(e.target.value)}
                                placeholder="Poids"
                                className="w-full rounded-lg border border-neutral-200 px-3 py-2 pr-10 text-center text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                            />
                            <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-neutral-400">kg</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={saving || weight.trim() === ''}
                        className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-neutral-900 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-40"
                    >
                        <Check size={16} /> {saving ? 'Enregistrement…' : 'Enregistrer la pesée'}
                    </button>
                </div>
            </Card>

            <div className="mt-4">
                <Card
                    title={
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="inline-flex items-center gap-1.5">
                                <TrendingUp size={15} className="text-brand-600" /> Moyenne par semaine
                            </span>
                            {overall !== null && overall !== 0 && (
                                <span className={`text-xs font-semibold normal-case ${overall < 0 ? 'text-emerald-600' : 'text-neutral-500'}`}>
                                    {overall < 0 ? '↓' : '↑'} {Math.abs(overall).toFixed(1)} kg sur la période
                                </span>
                            )}
                        </div>
                    }
                >
                    {weeks.length === 0 ? (
                        <p className="text-sm text-neutral-400">Aucune pesée pour l'instant — commence ce matin 👆</p>
                    ) : weeks.length === 1 ? (
                        <p className="py-4 text-center text-sm text-neutral-500">
                            <span className="text-2xl font-bold tabular-nums text-neutral-900">{weeks[0].avgKg.toFixed(1)} kg</span>
                            <br />
                            de moyenne cette semaine — la courbe se dessinera semaine après semaine.
                        </p>
                    ) : (
                        <WeightChart weeks={weeks} />
                    )}
                </Card>
            </div>

            {recent.length > 0 && (
                <div className="mt-4">
                    <Card title="Dernières pesées">
                        <ul className="divide-y divide-neutral-100">
                            {recent.map((e, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span className="flex-1 capitalize text-neutral-600">{shortDate(e.date)}</span>
                                    <span className="text-neutral-400">{e.momentLabel}</span>
                                    <span className="w-16 text-right font-semibold tabular-nums text-neutral-900">{e.weightKg.toFixed(1)} kg</span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}
        </>
    );
}

MuscuWeight.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
