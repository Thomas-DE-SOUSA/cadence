import { formatKilometers } from '@/features/activity/domain/format';

interface Paces {
    easy: number;
    marathon: number;
    threshold: number;
    interval: number;
    repetition: number;
}

interface Session {
    date: string;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetDurationSeconds: number | null;
    targetPaceSecondsPerKm: number | null;
    actual: { distanceMeters: number; averagePaceSecondsPerKm: number } | null;
}

const TYPE_LABEL: Record<string, string> = {
    EASY: 'Footing',
    LONG: 'Sortie longue',
    THRESHOLD: 'Seuil',
    INTERVALS: 'Fractionné',
    RECOVERY: 'Récupération',
    RACE_PACE: 'Allure course',
    RACE: 'Course',
    CROSS: 'Cross-training',
    REST: 'Repos',
};

const TYPE_ZONE: Record<string, keyof Paces | undefined> = {
    EASY: 'easy',
    RECOVERY: 'easy',
    LONG: 'easy',
    THRESHOLD: 'threshold',
    INTERVALS: 'interval',
};

const ZONES: { key: keyof Paces; label: string }[] = [
    { key: 'easy', label: 'Facile (E)' },
    { key: 'marathon', label: 'Marathon (M)' },
    { key: 'threshold', label: 'Seuil (T)' },
    { key: 'interval', label: 'Intervalle (I)' },
    { key: 'repetition', label: 'Répétition (R)' },
];

function mmss(seconds: number): string {
    const s = Math.round(seconds);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function duration(seconds: number): string {
    const s = Math.round(seconds);
    if (s < 3600) return `${Math.floor(s / 60)} min ${String(s % 60).padStart(2, '0')}`;
    return `${Math.floor(s / 3600)} h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}`;
}

const SPLITS: { meters: number; label: string }[] = [
    { meters: 1000, label: '1 km' },
    { meters: 800, label: '800 m' },
    { meters: 500, label: '500 m' },
    { meters: 400, label: '400 m' },
    { meters: 200, label: '200 m' },
];

export function SessionDetail({ session, paces }: { session: Session; paces: Paces | null }) {
    const pace = session.targetPaceSecondsPerKm;
    const zone = TYPE_ZONE[session.type];
    const estimatedSeconds =
        session.targetDurationSeconds ??
        (pace && session.targetDistanceMeters ? Math.round((session.targetDistanceMeters / 1000) * pace) : null);

    return (
        <div className="space-y-5">
            <div>
                <span className="text-[11px] font-semibold uppercase tracking-wide text-lime-300">
                    {TYPE_LABEL[session.type] ?? session.type}
                </span>
                <h3 className="mt-0.5 text-lg font-bold text-neutral-100">{session.title}</h3>
                <p className="mt-1 text-sm leading-relaxed text-neutral-400">{session.description}</p>
            </div>

            <div className="grid grid-cols-3 gap-3">
                {pace && (
                    <div className="rounded-lg border border-neutral-800 bg-neutral-900/50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">Allure</p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-lime-400">{mmss(pace)}/km</p>
                        <p className="text-[11px] text-neutral-600">{(3600 / pace).toFixed(1)} km/h</p>
                    </div>
                )}
                {session.targetDistanceMeters && (
                    <div className="rounded-lg border border-neutral-800 bg-neutral-900/50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">Distance</p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-neutral-100">
                            {formatKilometers(session.targetDistanceMeters)} km
                        </p>
                    </div>
                )}
                {estimatedSeconds && (
                    <div className="rounded-lg border border-neutral-800 bg-neutral-900/50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">
                            {session.targetDurationSeconds ? 'Durée' : 'Temps estimé'}
                        </p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-neutral-100">
                            {duration(estimatedSeconds)}
                        </p>
                    </div>
                )}
            </div>

            {pace && (
                <div>
                    <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                        Temps à tenir, par distance
                    </p>
                    <div className="grid grid-cols-5 gap-1.5">
                        {SPLITS.map((split) => (
                            <div key={split.meters} className="rounded-md border border-neutral-800 bg-neutral-900/50 p-2 text-center">
                                <p className="text-[10px] text-neutral-500">{split.label}</p>
                                <p className="text-sm font-semibold tabular-nums text-neutral-100">
                                    {mmss((pace * split.meters) / 1000)}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {session.actual && (
                <div className="rounded-lg border border-lime-400/30 bg-lime-400/[0.06] p-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-lime-300">Réalisé ce jour</p>
                    <p className="mt-0.5 text-sm tabular-nums text-neutral-100">
                        {formatKilometers(session.actual.distanceMeters)} km · {mmss(session.actual.averagePaceSecondsPerKm)}/km
                    </p>
                </div>
            )}

            {paces && session.type !== 'REST' && (
                <div>
                    <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                        Tes allures de référence
                    </p>
                    <div className="space-y-1">
                        {ZONES.map((z) => (
                            <div
                                key={z.key}
                                className={`flex items-baseline justify-between rounded-md px-2 py-1 text-sm ${
                                    zone === z.key ? 'bg-lime-400/10 text-lime-200' : 'text-neutral-400'
                                }`}
                            >
                                <span>{z.label}</span>
                                <span className="tabular-nums">{mmss(paces[z.key])}/km</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
