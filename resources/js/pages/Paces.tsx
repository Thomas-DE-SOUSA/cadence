import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Coffee, Flame, Gauge, HeartPulse, Mountain, Timer, Zap } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { PagePlaceholder } from '@/components/PagePlaceholder';
import { useCountUp } from '@/lib/useCountUp';
import { formatDuration, formatPace } from '@/features/activity/domain/format';

interface Zone {
    key: string;
    minSeconds: number;
    maxSeconds: number;
}

interface RacePace {
    label: string;
    distanceMeters: number;
    seconds: number;
    paceSeconds: number;
    isBasis: boolean;
}

interface Props {
    vdot: number | null;
    basis: { distanceMeters: number; seconds: number; label: string } | null;
    zones: Zone[];
    racePaces: RacePace[];
}

type ZoneMeta = { name: string; purpose: string; description: string; icon: typeof Coffee; tint: string; bar: string };

const ZONE_META: Record<string, ZoneMeta> = {
    recovery: {
        name: 'Récupération',
        purpose: 'Footing très lent',
        description: 'Décrassage et récup active. On reste vraiment tranquille.',
        icon: Coffee,
        tint: 'bg-slate-100 text-slate-500',
        bar: 'bg-slate-300',
    },
    easy: {
        name: 'Endurance fondamentale',
        purpose: 'La base aérobie',
        description: "L'essentiel de ton volume et tes sorties longues. Allure conversationnelle.",
        icon: HeartPulse,
        tint: 'bg-emerald-100 text-emerald-600',
        bar: 'bg-emerald-400',
    },
    marathon: {
        name: 'Allure marathon',
        purpose: 'Soutenu et régulier',
        description: 'Endurance spécifique et efficacité de course sur longue distance.',
        icon: Mountain,
        tint: 'bg-teal-100 text-teal-600',
        bar: 'bg-teal-400',
    },
    threshold: {
        name: 'Seuil',
        purpose: 'Confortablement dur (~1 h d’effort)',
        description: 'Le plus gros levier du 10 km au marathon. Tempo et intervalles au seuil.',
        icon: Gauge,
        tint: 'bg-amber-100 text-amber-600',
        bar: 'bg-amber-400',
    },
    interval: {
        name: 'Intervalles',
        purpose: 'VO₂max — fractions de 3 à 5 min',
        description: 'Développe la cylindrée aérobie. Dur, par répétitions avec récup.',
        icon: Flame,
        tint: 'bg-brand-100 text-brand-600',
        bar: 'bg-brand-500',
    },
    repetition: {
        name: 'Répétitions',
        purpose: 'Vif et court, récup complète',
        description: 'Vitesse, économie de course, foulée. Pas de stress aérobie.',
        icon: Zap,
        tint: 'bg-violet-100 text-violet-600',
        bar: 'bg-violet-500',
    },
};

function mmss(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function paceLabel(min: number, max: number): string {
    return min === max ? formatPace(min) : `${mmss(min)}–${mmss(max)}/km`;
}

function VdotDigits({ vdot }: { vdot: number }) {
    // Count up on the integer part; keep the decimal steady.
    const whole = Math.floor(vdot);
    const animated = Math.round(useCountUp(whole));
    const decimal = Math.round((vdot - whole) * 10);
    return <>{`${animated}.${decimal}`}</>;
}

export default function Paces({ vdot, basis, zones, racePaces }: Props) {
    if (vdot === null || basis === null) {
        return (
            <>
                <Head title="Allures" />
                <PagePlaceholder
                    title="Allures"
                    description="Enregistre une sortie avec ses splits : tes zones d'allure seront calculées automatiquement à partir de ton VDOT."
                    icon={Gauge}
                />
            </>
        );
    }

    // Scale each zone's bar relative to the slowest/fastest across all zones.
    const allPaces = zones.flatMap((z) => [z.minSeconds, z.maxSeconds]);
    const fastest = Math.min(...allPaces);
    const slowest = Math.max(...allPaces);
    const span = Math.max(slowest - fastest, 1);
    const width = (sec: number) => 30 + ((slowest - sec) / span) * 70; // faster = longer

    return (
        <>
            <Head title="Allures" />
            <h1 className="mb-6 text-2xl font-bold tracking-tight text-neutral-900">Allures</h1>

            {/* VDOT hero */}
            <div className="animate-fade-up mb-5 overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/50 p-5 shadow-sm shadow-neutral-200/60">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-lg shadow-brand-500/30">
                            <Gauge size={28} />
                        </span>
                        <div>
                            <p className="text-3xl font-black leading-none tabular-nums text-neutral-900">
                                <VdotDigits vdot={vdot} /> <span className="text-lg font-bold text-neutral-500">VDOT</span>
                            </p>
                            <p className="mt-1 text-sm text-neutral-500">
                                Calé sur ton {basis.label} en {formatDuration(basis.seconds)}
                            </p>
                        </div>
                    </div>
                    <p className="max-w-xs text-xs text-neutral-500">
                        Tes allures se recalibrent automatiquement dès que tu bats un record. Ce sont des repères — adapte à la
                        chaleur, au dénivelé et à la fatigue.
                    </p>
                </div>
            </div>

            {/* Zones */}
            <div className="animate-fade-up mb-5 grid grid-cols-1 gap-3 md:grid-cols-2" style={{ animationDelay: '60ms' }}>
                {zones.map((zone) => {
                    const meta = ZONE_META[zone.key];
                    if (!meta) return null;
                    const Icon = meta.icon;
                    return (
                        <Card key={zone.key}>
                            <div className="flex items-start gap-3">
                                <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${meta.tint}`}>
                                    <Icon size={19} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <p className="font-bold text-neutral-900">{meta.name}</p>
                                        <p className="whitespace-nowrap text-lg font-black tabular-nums text-neutral-900">
                                            {paceLabel(zone.minSeconds, zone.maxSeconds)}
                                        </p>
                                    </div>
                                    <p className="text-xs font-medium text-neutral-400">{meta.purpose}</p>
                                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                                        <div className={`h-full rounded-full ${meta.bar}`} style={{ width: `${width(zone.minSeconds)}%` }} />
                                    </div>
                                    <p className="mt-2 text-xs leading-relaxed text-neutral-500">{meta.description}</p>
                                </div>
                            </div>
                        </Card>
                    );
                })}
            </div>

            {/* Equivalent race paces */}
            <div className="animate-fade-up" style={{ animationDelay: '90ms' }}>
                <Card title="Allures de course équivalentes">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-[11px] uppercase tracking-wide text-neutral-400">
                                <th className="pb-2 font-semibold">Distance</th>
                                <th className="pb-2 font-semibold">Temps visé</th>
                                <th className="pb-2 text-right font-semibold">Allure</th>
                            </tr>
                        </thead>
                        <tbody>
                            {racePaces.map((r) => (
                                <tr key={r.distanceMeters} className="border-t border-neutral-100">
                                    <td className="py-2.5">
                                        <span className="inline-flex items-center gap-2">
                                            <Timer size={14} className="text-neutral-300" />
                                            <span className="font-medium text-neutral-800">{r.label}</span>
                                            {r.isBasis && (
                                                <span className="rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-600">
                                                    ton record
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                    <td className="py-2.5 font-bold tabular-nums text-neutral-900">{formatDuration(r.seconds)}</td>
                                    <td className="py-2.5 text-right tabular-nums text-neutral-600">{formatPace(r.paceSeconds)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-3 text-xs text-neutral-400">
                        Projections Riegel à partir de ton meilleur effort — indicatives, la course réelle dépend de la préparation
                        spécifique.
                    </p>
                </Card>
            </div>
        </>
    );
}

Paces.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
