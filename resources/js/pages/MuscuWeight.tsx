import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
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

const fmtWeek = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
const fmtDay = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });

const toggleClass = (active: boolean) =>
    `flex flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors ${
        active ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-500'
    }`;

/** Weekly-average trend line — the whole point is seeing week-to-week drift. */
function Sparkline({ weeks }: { weeks: Week[] }) {
    if (weeks.length < 2) return null;
    const vals = weeks.map((w) => w.avgKg);
    const min = Math.min(...vals);
    const max = Math.max(...vals);
    const span = Math.max(max - min, 0.1);
    const W = 280;
    const H = 60;
    const pts = weeks.map((w, i) => [(i / (weeks.length - 1)) * W, H - ((w.avgKg - min) / span) * H] as const);
    const path = pts.map(([x, y], i) => `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');
    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" style={{ height: 60 }} preserveAspectRatio="none">
            <path d={path} fill="none" stroke="#f26722" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            {pts.map(([x, y], i) => (
                <circle key={i} cx={x} cy={y} r={2.5} fill="#f26722" />
            ))}
        </svg>
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

    // Newest week first for the list, each with its drift vs the previous week.
    const list = weeks.map((w, i) => ({ ...w, delta: i > 0 ? Number((w.avgKg - weeks[i - 1].avgKg).toFixed(1)) : null })).reverse();

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
                        <span className="inline-flex items-center gap-1.5">
                            <TrendingUp size={15} className="text-brand-600" /> Moyenne par semaine
                        </span>
                    }
                >
                    {weeks.length === 0 ? (
                        <p className="text-sm text-neutral-400">Aucune pesée pour l'instant — commence ce matin 👆</p>
                    ) : (
                        <>
                            <Sparkline weeks={weeks} />
                            <ul className="mt-3 divide-y divide-neutral-100">
                                {list.map((w) => (
                                    <li key={w.weekStart} className="flex items-center justify-between gap-3 py-2.5">
                                        <span className="flex-1 text-sm text-neutral-600">Sem. du {fmtWeek(w.weekStart)}</span>
                                        <span className="text-[11px] text-neutral-400">
                                            {w.count} pesée{w.count > 1 ? 's' : ''}
                                        </span>
                                        {w.delta !== null && w.delta !== 0 && (
                                            <span className={`text-xs font-medium ${w.delta < 0 ? 'text-emerald-600' : 'text-neutral-400'}`}>
                                                {w.delta < 0 ? '↓' : '↑'} {Math.abs(w.delta).toFixed(1)}
                                            </span>
                                        )}
                                        <span className="w-16 text-right text-sm font-bold tabular-nums text-neutral-900">{w.avgKg.toFixed(1)} kg</span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Card>
            </div>

            {recent.length > 0 && (
                <div className="mt-4">
                    <Card title="Dernières pesées">
                        <ul className="divide-y divide-neutral-100">
                            {recent.map((e, i) => (
                                <li key={i} className="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span className="flex-1 capitalize text-neutral-600">{fmtDay(e.date)}</span>
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
