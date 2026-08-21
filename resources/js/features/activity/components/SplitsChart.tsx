import type { Split } from '@/types';
import { formatPace, paceSecondsPerKm } from '../domain/format';

interface Props {
    splits: Split[];
    /** Reference pace in seconds/km (e.g. sub-40 goal = 240). */
    targetSecondsPerKm?: number;
}

export function SplitsChart({ splits, targetSecondsPerKm = 240 }: Props) {
    const paces = splits.map((s) => paceSecondsPerKm(s.distanceMeters, s.durationSeconds));
    const slowest = Math.max(...paces, targetSecondsPerKm);
    const fastest = Math.min(...paces, targetSecondsPerKm);
    const span = Math.max(slowest - fastest, 1);

    // Map a pace to a bar width % (slower = longer bar).
    const width = (pace: number) => 20 + ((pace - fastest) / span) * 80;

    return (
        <div className="space-y-1.5">
            {splits.map((split, i) => {
                const pace = paces[i];
                const isFast = pace <= targetSecondsPerKm;
                return (
                    <div key={split.index} className="flex items-center gap-3 text-sm">
                        <span className="w-10 shrink-0 tabular-nums text-neutral-500">km {split.index}</span>
                        <div className="relative h-6 flex-1 overflow-hidden rounded bg-neutral-100">
                            <div
                                className={`h-full rounded ${isFast ? 'bg-brand-500/80' : 'bg-orange-400/70'}`}
                                style={{ width: `${width(pace)}%` }}
                            />
                            <span className="absolute inset-y-0 left-2 flex items-center text-xs font-medium tabular-nums text-neutral-900">
                                {formatPace(pace)}
                            </span>
                        </div>
                        <span className="w-12 shrink-0 text-right tabular-nums text-neutral-500">
                            {split.elevationMeters > 0 ? '+' : ''}
                            {split.elevationMeters} m
                        </span>
                    </div>
                );
            })}
            <p className="pt-2 text-xs text-neutral-500">
                <span className="mr-3">
                    <span className="mr-1 inline-block h-2 w-2 rounded-full bg-brand-500/80" />≤ {formatPace(targetSecondsPerKm)} (objectif)
                </span>
                <span>
                    <span className="mr-1 inline-block h-2 w-2 rounded-full bg-orange-400/70" />plus lent
                </span>
            </p>
        </div>
    );
}
