/** Pure formatting helpers for activity data. No framework, no side effects. */

export function paceSecondsPerKm(distanceMeters: number, durationSeconds: number): number {
    if (distanceMeters <= 0) {
        return 0;
    }
    return durationSeconds / (distanceMeters / 1000);
}

export function formatPace(secondsPerKm: number): string {
    if (secondsPerKm <= 0) {
        return '—';
    }
    const rounded = Math.round(secondsPerKm);
    const minutes = Math.floor(rounded / 60);
    const seconds = rounded % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}/km`;
}

export function formatDuration(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

export function formatKilometers(meters: number): string {
    return (meters / 1000).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}
