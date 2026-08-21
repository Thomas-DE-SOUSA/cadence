export interface Split {
    index: number;
    distanceMeters: number;
    durationSeconds: number;
    elevationMeters: number;
}

export interface BestEffort {
    label: string;
    distanceMeters: number;
    durationSeconds: number;
    isPersonalRecord: boolean;
}

export interface ActivitySummary {
    id: string;
    occurredAt: string;
    source: string;
    distanceMeters: number;
    movingSeconds: number;
    averagePaceSecondsPerKm: number;
}

export interface Activity {
    id: string;
    occurredAt: string;
    source: string;
    distanceMeters: number;
    movingSeconds: number;
    elapsedSeconds: number;
    elevationGainMeters: number;
    averagePaceSecondsPerKm: number;
    splits: Split[];
    bestEfforts: BestEffort[];
    track: [number, number][] | null;
}
