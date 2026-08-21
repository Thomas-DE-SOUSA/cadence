import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowLeft, Clock, Gauge, Mountain, Route as RouteIcon, Timer, Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { HelpTip } from '@/components/HelpTip';
import type { Activity } from '@/types';
import { BestEfforts } from '@/features/activity/components/BestEfforts';
import { RouteLeaflet } from '@/features/activity/components/RouteLeaflet';
import { RouteMap } from '@/features/activity/components/RouteMap';
import { ProfileChart } from '@/features/activity/components/ProfileChart';
import { formatDate, formatDuration, formatKilometers, formatPace, paceSecondsPerKm } from '@/features/activity/domain/format';

interface Props {
    activity: Activity;
}

function HeroStat({
    icon: Icon,
    label,
    value,
    accent,
    tint,
    help,
}: {
    icon: typeof Gauge;
    label: string;
    value: string;
    accent?: boolean;
    tint: string;
    help?: string;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <span className={`flex h-9 w-9 items-center justify-center rounded-xl ${tint}`}>
                <Icon size={17} />
            </span>
            <div>
                <p className={`text-xl font-bold leading-none tabular-nums ${accent ? 'text-brand-500' : 'text-neutral-900'}`}>
                    {value}
                </p>
                <p className="mt-1 flex items-center gap-1 text-[11px] uppercase tracking-wide text-neutral-400">
                    {label}
                    {help && <HelpTip label={label} text={help} size={12} />}
                </p>
            </div>
        </div>
    );
}

export default function ActivityDetail({ activity }: Props) {
    const del = useForm();

    function remove() {
        if (window.confirm('Supprimer définitivement cette activité ?')) {
            del.delete(`/activites/${activity.id}`);
        }
    }

    const paces = activity.splits.map((s) => paceSecondsPerKm(s.distanceMeters, s.durationSeconds));
    const fastest = paces.length ? Math.min(...paces) : 0;
    const slowest = paces.length ? Math.max(...paces) : 1;
    const span = Math.max(slowest - fastest, 1);

    return (
        <>
            <Head title="Activité" />
            <div className="mb-4 flex items-center justify-between">
                <Link
                    href="/"
                    className="inline-flex items-center gap-1 text-sm text-neutral-500 transition-colors hover:text-neutral-900"
                >
                    <ArrowLeft size={16} /> Tableau de bord
                </Link>
                <div className="flex items-center gap-2">
                    <Link
                        href={`/activites/${activity.id}/modifier`}
                        className="rounded-lg border border-neutral-300 px-3 py-1.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-100"
                    >
                        Modifier
                    </Link>
                    <button
                        onClick={remove}
                        disabled={del.processing}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50"
                    >
                        <Trash2 size={15} /> Supprimer
                    </button>
                </div>
            </div>

            {/* Hero */}
            <div className="animate-fade-up mb-5 rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/40 p-5 shadow-sm shadow-neutral-200/60">
                <p className="text-sm text-neutral-500">
                    {formatDate(activity.occurredAt)} · {activity.source === 'STRAVA' ? 'Strava' : activity.source === 'GPX' ? 'GPX' : 'Manuel'}
                </p>
                <div className="mt-4 flex flex-wrap gap-x-8 gap-y-4 border-t border-neutral-100 pt-4">
                    <HeroStat icon={RouteIcon} label="Distance" tint="bg-sky-100 text-sky-600" value={`${formatKilometers(activity.distanceMeters)} km`} />
                    <HeroStat
                        icon={Clock}
                        label="Temps"
                        tint="bg-neutral-100 text-neutral-600"
                        value={formatDuration(activity.movingSeconds)}
                        help="Temps en mouvement, hors pauses (arrêts, feux). C’est lui qui sert à calculer ton allure."
                    />
                    <HeroStat
                        icon={Gauge}
                        label="Allure moy."
                        tint="bg-brand-100 text-brand-600"
                        value={formatPace(activity.averagePaceSecondsPerKm)}
                        accent
                        help="Ton rythme moyen, en minutes par kilomètre (min/km). Plus le chiffre est bas, plus tu cours vite."
                    />
                    <HeroStat
                        icon={Mountain}
                        label="Dénivelé +"
                        tint="bg-emerald-100 text-emerald-600"
                        value={`${activity.elevationGainMeters} m`}
                        help="Le cumul de montée sur toute la sortie (on additionne uniquement les portions qui montent)."
                    />
                    <HeroStat
                        icon={Timer}
                        label="Temps écoulé"
                        tint="bg-neutral-100 text-neutral-600"
                        value={formatDuration(activity.elapsedSeconds)}
                        help="Durée totale entre le départ et l’arrivée, pauses comprises."
                    />
                </div>
            </div>

            {/* Map */}
            {activity.track && (
                <div className="animate-fade-up mb-5 overflow-hidden rounded-2xl border border-neutral-200 shadow-sm shadow-neutral-200/60" style={{ animationDelay: '60ms' }}>
                    {activity.track.length > 1 ? (
                        <RouteLeaflet track={activity.track} className="h-[22rem] w-full" />
                    ) : (
                        <RouteMap track={activity.track} className="h-72 w-full bg-neutral-50" />
                    )}
                </div>
            )}

            {/* Profile */}
            {activity.stream && activity.stream.length > 1 && (
                <div className="animate-fade-up mb-5" style={{ animationDelay: '90ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Allure &amp; dénivelé
                                <HelpTip
                                    label="Allure & dénivelé"
                                    text="Ton allure (courbe orange) et l’altitude du terrain (zone grise) tout au long du parcours. Survole le graphique pour lire les valeurs à un point précis."
                                />
                            </span>
                        }
                    >
                        <ProfileChart stream={activity.stream} />
                    </Card>
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Temps intermédiaires
                                <HelpTip label="Temps intermédiaires" text="Ton allure kilomètre par kilomètre. La barre est d’autant plus longue que le km est rapide." />
                            </span>
                        }
                    >
                        <div className="overflow-hidden">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-[11px] uppercase tracking-wide text-neutral-400">
                                        <th className="pb-2 font-semibold">Km</th>
                                        <th className="pb-2 font-semibold">Allure</th>
                                        <th className="pb-2 text-right font-semibold">
                                            <span className="inline-flex items-center gap-1">
                                                D±
                                                <HelpTip label="D±" side="bottom" text="Dénivelé positif : le cumul de montée sur ce kilomètre." />
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {activity.splits.map((s, i) => {
                                        const pace = paces[i];
                                        const w = 25 + ((slowest - pace) / span) * 75; // faster = longer bar
                                        return (
                                            <tr key={s.index} className="border-t border-neutral-100">
                                                <td className="py-2 tabular-nums text-neutral-500">{s.index}</td>
                                                <td className="py-2">
                                                    <div className="relative h-5 w-full max-w-[220px] overflow-hidden rounded bg-neutral-100">
                                                        <div
                                                            className={`h-full rounded ${pace <= fastest + span * 0.25 ? 'bg-brand-500' : 'bg-brand-300'}`}
                                                            style={{ width: `${w}%` }}
                                                        />
                                                        <span className="absolute inset-y-0 left-2 flex items-center text-xs font-semibold tabular-nums text-neutral-900">
                                                            {formatPace(pace)}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-2 text-right tabular-nums text-neutral-500">
                                                    {s.elevationMeters > 0 ? '+' : ''}
                                                    {s.elevationMeters} m
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>
                <div>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                Meilleurs efforts
                                <HelpTip
                                    label="Meilleurs efforts"
                                    text="Tes segments les plus rapides pendant cette sortie, pour chaque distance (1 km, 5 km…). Utile pour repérer tes accélérations."
                                />
                            </span>
                        }
                    >
                        <BestEfforts efforts={activity.bestEfforts} />
                    </Card>
                </div>
            </div>
        </>
    );
}

ActivityDetail.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
