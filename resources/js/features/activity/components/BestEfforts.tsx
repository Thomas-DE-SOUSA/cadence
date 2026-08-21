import type { BestEffort } from '@/types';
import { formatDuration, formatPace, paceSecondsPerKm } from '../domain/format';

interface Props {
    efforts: BestEffort[];
}

export function BestEfforts({ efforts }: Props) {
    if (efforts.length === 0) {
        return <p className="text-sm text-neutral-500">Aucun effort enregistré.</p>;
    }

    return (
        <ul className="divide-y divide-neutral-200">
            {efforts.map((effort) => (
                <li key={effort.label} className="flex items-center justify-between py-2.5">
                    <span className="flex items-center gap-2 font-medium text-neutral-800">
                        {effort.label}
                        {effort.isPersonalRecord && (
                            <span className="rounded bg-brand-500/15 px-1.5 py-0.5 text-xs font-semibold text-brand-600">
                                RP
                            </span>
                        )}
                    </span>
                    <span className="tabular-nums text-neutral-500">
                        {formatDuration(effort.durationSeconds)}
                        <span className="ml-2 text-neutral-500">
                            {formatPace(paceSecondsPerKm(effort.distanceMeters, effort.durationSeconds))}
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}
