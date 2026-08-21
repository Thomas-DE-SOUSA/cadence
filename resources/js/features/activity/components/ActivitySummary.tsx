import type { Activity } from '@/types';
import { formatDate, formatDuration, formatKilometers, formatPace } from '../domain/format';

interface Props {
    activity: Activity;
}

function Stat({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
    return (
        <div>
            <div className="text-xs uppercase tracking-wide text-neutral-500">{label}</div>
            <div className={`mt-0.5 text-2xl font-semibold tabular-nums ${accent ? 'text-brand-600' : 'text-neutral-900'}`}>
                {value}
            </div>
        </div>
    );
}

export function ActivitySummary({ activity }: Props) {
    return (
        <div>
            <div className="text-sm text-neutral-500">{formatDate(activity.occurredAt)}</div>
            <div className="mt-4 grid grid-cols-2 gap-5 sm:grid-cols-4">
                <Stat label="Distance" value={`${formatKilometers(activity.distanceMeters)} km`} />
                <Stat label="Temps" value={formatDuration(activity.movingSeconds)} />
                <Stat label="Allure moy." value={formatPace(activity.averagePaceSecondsPerKm)} accent />
                <Stat label="Dénivelé +" value={`${activity.elevationGainMeters} m`} />
            </div>
        </div>
    );
}
