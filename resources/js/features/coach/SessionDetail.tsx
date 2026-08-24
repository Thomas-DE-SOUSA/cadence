import { formatKilometers } from '@/features/activity/domain/format';

interface Paces {
    easy: number;
    marathon: number;
    threshold: number;
    interval: number;
    repetition: number;
}

interface Step {
    label: string;
    repeat: number;
    distanceMeters: number | null;
    durationSeconds: number | null;
    paceSecondsPerKm: number | null;
    recoverySeconds: number | null;
    note: string;
}

interface Session {
    date: string;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetDurationSeconds: number | null;
    targetPaceSecondsPerKm: number | null;
    steps: Step[];
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

const ZONES: { key: keyof Paces; label: string; hint: string }[] = [
    { key: 'easy', label: 'Facile (E)', hint: 'footing, récup — construit le foncier' },
    { key: 'marathon', label: 'Marathon (M)', hint: 'endurance active soutenue' },
    { key: 'threshold', label: 'Seuil (T)', hint: '~1 h d\'effort, "comfortably hard"' },
    { key: 'interval', label: 'Intervalle (I)', hint: 'VMA, VO₂max (3–5 min)' },
    { key: 'repetition', label: 'Répétition (R)', hint: 'vitesse, économie de course' },
];

// Sessions run as reps: show per-rep time. Others are continuous: show cumulative splits.
const REP_TYPES = ['INTERVALS', 'REPETITION', 'RACE_PACE'];

function mmss(seconds: number): string {
    const s = Math.round(seconds);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function clock(seconds: number): string {
    const s = Math.round(seconds);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const ss = s % 60;
    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${String(ss).padStart(2, '0')}` : `${m}:${String(ss).padStart(2, '0')}`;
}

function duration(seconds: number): string {
    const s = Math.round(seconds);
    if (s < 3600) return `${Math.floor(s / 60)} min ${String(s % 60).padStart(2, '0')}`;
    return `${Math.floor(s / 3600)} h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}`;
}

function shortDuration(seconds: number): string {
    const s = Math.round(seconds);
    if (s < 3600) return s % 60 === 0 ? `${s / 60} min` : `${Math.floor(s / 60)}′${String(s % 60).padStart(2, '0')}`;
    return `${Math.floor(s / 3600)} h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}`;
}

function stepEffort(step: Step): string {
    if (step.durationSeconds) return shortDuration(step.durationSeconds);
    if (step.distanceMeters) return step.distanceMeters >= 1000 ? `${formatKilometers(step.distanceMeters)} km` : `${step.distanceMeters} m`;
    return '';
}

export function SessionDetail({ session, paces }: { session: Session; paces: Paces | null }) {
    const pace = session.targetPaceSecondsPerKm;
    const zone = TYPE_ZONE[session.type];
    const isReps = REP_TYPES.includes(session.type);

    const distanceMeters =
        session.targetDistanceMeters ??
        (pace && session.targetDurationSeconds ? Math.round((session.targetDurationSeconds / pace) * 1000) : null);
    const estimatedSeconds =
        session.targetDurationSeconds ?? (pace && session.targetDistanceMeters ? Math.round((session.targetDistanceMeters / 1000) * pace) : null);
    const distanceEstimated = session.targetDistanceMeters === null && distanceMeters !== null;

    // Continuous → cumulative split at each km mark; reps → time of one rep.
    let splits: { label: string; time: string }[] = [];
    if (pace) {
        if (isReps) {
            splits = [200, 400, 800, 1000].map((m) => ({ label: m === 1000 ? '1 km' : `${m} m`, time: mmss((pace * m) / 1000) }));
        } else {
            const km = distanceMeters ? distanceMeters / 1000 : 5;
            splits = [1, 2, 3, 5, 10, 15, 20]
                .filter((k) => k <= km + 0.1)
                .slice(0, 6)
                .map((k) => ({ label: `${k} km`, time: clock(pace * k) }));
        }
    }

    // Objectif vs réalisé — only when the day has a linked run and a pace target.
    const actual = session.actual;
    const comparison =
        actual && pace
            ? (() => {
                  const actualSeconds = Math.round((actual.distanceMeters / 1000) * actual.averagePaceSecondsPerKm);
                  const paceDelta = Math.round(actual.averagePaceSecondsPerKm - pace); // <0 = plus rapide
                  const distDelta = distanceMeters != null ? actual.distanceMeters - distanceMeters : null;
                  const durDelta = estimatedSeconds != null ? actualSeconds - estimatedSeconds : null;
                  const easy = zone === 'easy';
                  const abs = Math.abs(paceDelta);
                  let tone: 'ok' | 'warn' = 'ok';
                  let text = 'Allure conforme à la cible 👍';
                  if (abs > 12) {
                      if (easy) {
                          tone = paceDelta < 0 ? 'warn' : 'ok';
                          text =
                              paceDelta < 0
                                  ? `Trop rapide de ${abs} s/km pour une séance facile — le facile doit rester facile pour bien récupérer.`
                                  : "Un peu plus lent que la cible, mais sur un footing c'est parfait.";
                      } else {
                          tone = paceDelta < 0 ? 'ok' : 'warn';
                          text =
                              paceDelta < 0
                                  ? `Plus rapide que la cible de ${abs} s/km — belle intensité.`
                                  : `En-deçà de la cible de ${abs} s/km — l'intensité visée n'a pas été atteinte.`;
                      }
                  }
                  return { actualSeconds, paceDelta, distDelta, durDelta, tone, text };
              })()
            : null;

    return (
        <div className="space-y-5">
            <div>
                <span className="text-[11px] font-semibold uppercase tracking-wide text-brand-600">
                    {TYPE_LABEL[session.type] ?? session.type}
                </span>
                <h3 className="mt-0.5 text-lg font-bold text-neutral-900">{session.title}</h3>
                <p className="mt-1 text-sm leading-relaxed text-neutral-500">{session.description}</p>
            </div>

            {session.steps.length > 0 && (
                <div>
                    <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Déroulé de la séance</p>
                    <ol className="space-y-2">
                        {session.steps.map((step, i) => {
                            const effort = stepEffort(step);
                            const recovery =
                                step.repeat > 1 && step.recoverySeconds ? `récup ${shortDuration(step.recoverySeconds)}` : '';
                            const sub = [recovery, step.note].filter(Boolean).join(' · ');
                            return (
                                <li key={i} className="rounded-lg border border-neutral-200 bg-neutral-50 p-2.5">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-sm text-neutral-900">
                                            {step.repeat > 1 && (
                                                <span className="font-semibold text-brand-600">{step.repeat} × </span>
                                            )}
                                            {effort && <span className="font-semibold">{effort}</span>}
                                            {effort && ' · '}
                                            {step.label}
                                        </span>
                                        {step.paceSecondsPerKm && (
                                            <span className="shrink-0 text-sm tabular-nums text-brand-600">
                                                {mmss(step.paceSecondsPerKm)}/km
                                            </span>
                                        )}
                                    </div>
                                    {sub && <p className="mt-0.5 text-xs text-neutral-500">{sub}</p>}
                                </li>
                            );
                        })}
                    </ol>
                </div>
            )}

            <div className="grid grid-cols-3 gap-3">
                {pace && (
                    <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">Allure</p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-brand-500">{mmss(pace)}/km</p>
                        <p className="text-[11px] text-neutral-400">{(3600 / pace).toFixed(1)} km/h</p>
                    </div>
                )}
                {distanceMeters && (
                    <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">
                            Distance{distanceEstimated ? ' ~' : ''}
                        </p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-neutral-900">
                            {formatKilometers(distanceMeters)} km
                        </p>
                    </div>
                )}
                {estimatedSeconds && (
                    <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                        <p className="text-[11px] uppercase tracking-wide text-neutral-500">
                            {session.targetDurationSeconds ? 'Durée' : 'Temps ~'}
                        </p>
                        <p className="mt-0.5 text-lg font-semibold tabular-nums text-neutral-900">
                            {duration(estimatedSeconds)}
                        </p>
                    </div>
                )}
            </div>

            {pace && splits.length > 0 && (
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                        {isReps ? 'Temps de chaque répétition' : 'Temps de passage'}
                    </p>
                    <p className="mb-2 text-xs text-neutral-500">
                        {isReps
                            ? `À ${mmss(pace)}/km, voilà combien doit durer chaque répétition selon sa distance.`
                            : `Si tu tiens ${mmss(pace)}/km, voilà à quel temps tu dois passer chaque borne.`}
                    </p>
                    <div className="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                        {splits.map((s) => (
                            <div key={s.label} className="rounded-md border border-neutral-200 bg-neutral-50 p-2 text-center">
                                <p className="text-[10px] text-neutral-500">{s.label}</p>
                                <p className="text-sm font-semibold tabular-nums text-neutral-900">{s.time}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {session.actual && comparison && (
                <div className="rounded-lg border border-brand-500/30 bg-brand-500/[0.06] p-3">
                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-brand-600">Objectif vs réalisé</p>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-[10px] uppercase tracking-wide text-neutral-400">
                                <th className="font-medium" />
                                <th className="pb-1 font-medium">Objectif</th>
                                <th className="pb-1 font-medium">Réalisé</th>
                                <th className="pb-1 text-right font-medium">Écart</th>
                            </tr>
                        </thead>
                        <tbody className="tabular-nums">
                            {distanceMeters != null && (
                                <tr className="border-t border-brand-500/10">
                                    <td className="py-1 text-neutral-500">Distance</td>
                                    <td className="py-1 text-neutral-600">{formatKilometers(distanceMeters)} km</td>
                                    <td className="py-1 font-semibold text-neutral-900">{formatKilometers(session.actual.distanceMeters)} km</td>
                                    <td className="py-1 text-right text-neutral-500">
                                        {comparison.distDelta != null
                                            ? `${comparison.distDelta >= 0 ? '+' : '−'}${formatKilometers(Math.abs(comparison.distDelta))} km`
                                            : '—'}
                                    </td>
                                </tr>
                            )}
                            <tr className="border-t border-brand-500/10">
                                <td className="py-1 text-neutral-500">Allure</td>
                                <td className="py-1 text-neutral-600">{mmss(pace!)}/km</td>
                                <td className="py-1 font-semibold text-neutral-900">{mmss(session.actual.averagePaceSecondsPerKm)}/km</td>
                                <td className={`py-1 text-right font-semibold ${comparison.tone === 'warn' ? 'text-amber-600' : 'text-emerald-600'}`}>
                                    {`${comparison.paceDelta >= 0 ? '+' : '−'}${Math.abs(comparison.paceDelta)} s`}
                                </td>
                            </tr>
                            {estimatedSeconds != null && (
                                <tr className="border-t border-brand-500/10">
                                    <td className="py-1 text-neutral-500">Durée</td>
                                    <td className="py-1 text-neutral-600">{duration(estimatedSeconds)}</td>
                                    <td className="py-1 font-semibold text-neutral-900">{duration(comparison.actualSeconds)}</td>
                                    <td className="py-1 text-right text-neutral-500">
                                        {comparison.durDelta != null ? `${comparison.durDelta >= 0 ? '+' : '−'}${clock(Math.abs(comparison.durDelta))}` : '—'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <p className={`mt-2 text-xs leading-relaxed ${comparison.tone === 'warn' ? 'text-amber-700' : 'text-emerald-700'}`}>
                        {comparison.text}
                    </p>
                </div>
            )}

            {session.actual && !comparison && (
                <div className="rounded-lg border border-brand-500/30 bg-brand-500/[0.06] p-3">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-600">Réalisé ce jour</p>
                    <p className="mt-0.5 text-sm tabular-nums text-neutral-900">
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
                                className={`flex items-baseline justify-between gap-3 rounded-md px-2 py-1.5 ${
                                    zone === z.key ? 'bg-brand-500/10' : ''
                                }`}
                            >
                                <span className={`text-sm ${zone === z.key ? 'font-medium text-brand-700' : 'text-neutral-700'}`}>
                                    {z.label}
                                </span>
                                <span className="hidden flex-1 text-[11px] text-neutral-400 sm:block">{z.hint}</span>
                                <span className={`text-sm tabular-nums ${zone === z.key ? 'text-brand-700' : 'text-neutral-500'}`}>
                                    {mmss(paces[z.key])}/km
                                </span>
                            </div>
                        ))}
                    </div>
                    {zone && (
                        <p className="mt-2 text-xs text-neutral-500">
                            Cette séance se court en zone{' '}
                            <span className="text-brand-600">{ZONES.find((z) => z.key === zone)?.label}</span>.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
