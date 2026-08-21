interface Props {
    track: [number, number][] | null;
    className?: string;
}

/** Draws a GPS track as a stylised SVG route (no map tiles / API needed). */
export function RouteMap({ track, className = '' }: Props) {
    if (!track || track.length < 2) return null;

    const lats = track.map((p) => p[0]);
    const lons = track.map((p) => p[1]);
    const minLat = Math.min(...lats);
    const maxLat = Math.max(...lats);
    const midLat = (minLat + maxLat) / 2;
    // Longitudes compress toward the poles — scale by cos(lat) so the shape isn't stretched.
    const k = Math.cos((midLat * Math.PI) / 180);
    const xs = lons.map((l) => l * k);
    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);

    const W = 100;
    const H = 64;
    const pad = 8;
    const spanX = maxX - minX || 1e-6;
    const spanLat = maxLat - minLat || 1e-6;
    const scale = Math.min((W - 2 * pad) / spanX, (H - 2 * pad) / spanLat);
    const offX = (W - spanX * scale) / 2;
    const offY = (H - spanLat * scale) / 2;

    const px = (lon: number) => offX + (lon * k - minX) * scale;
    const py = (lat: number) => H - offY - (lat - minLat) * scale;

    const d = track.map((p, i) => `${i ? 'L' : 'M'}${px(p[1]).toFixed(1)} ${py(p[0]).toFixed(1)}`).join(' ');
    const start = track[0];
    const end = track[track.length - 1];

    return (
        <svg viewBox="0 0 100 64" className={className} preserveAspectRatio="xMidYMid meet" aria-hidden>
            <defs>
                <linearGradient id="routeGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#ff6f38" />
                    <stop offset="1" stopColor="#1c855a" />
                </linearGradient>
            </defs>
            <path d={d} fill="none" stroke="url(#routeGrad)" strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={px(start[1])} cy={py(start[0])} r={2.8} fill="#10b981" stroke="#fff" strokeWidth={1} />
            <circle cx={px(end[1])} cy={py(end[0])} r={2.8} fill="#1c855a" stroke="#fff" strokeWidth={1} />
        </svg>
    );
}
