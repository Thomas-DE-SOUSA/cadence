interface StreamPoint {
    d: number; // cumulative distance (m)
    e: number; // elevation (m)
    p: number; // pace (s/km)
}

function paceLabel(seconds: number): string {
    const s = Math.round(seconds);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** Strava-like profile: grey elevation area + orange pace line over distance. */
export function ProfileChart({ stream }: { stream: StreamPoint[] }) {
    if (stream.length < 2) return null;

    const W = 800;
    const H = 220;
    const padT = 12;
    const padB = 26;
    const padX = 4;

    const maxD = Math.max(...stream.map((s) => s.d)) || 1;
    const elevs = stream.map((s) => s.e);
    const paces = stream.map((s) => s.p);
    const minE = Math.min(...elevs);
    const maxE = Math.max(...elevs);
    // Trim pace outliers a touch for a smoother axis.
    const sortedP = [...paces].sort((a, b) => a - b);
    const minP = sortedP[Math.floor(sortedP.length * 0.02)];
    const maxP = sortedP[Math.floor(sortedP.length * 0.98)];

    const x = (d: number) => padX + (d / maxD) * (W - 2 * padX);
    const elevTop = H * 0.45;
    const elevBottom = H - padB;
    const ey = (e: number) => elevBottom - ((e - minE) / (maxE - minE || 1)) * (elevBottom - elevTop);
    const clamp = (v: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, v));
    const py = (p: number) => padT + (clamp(p, minP, maxP) - minP) / (maxP - minP || 1) * (H - padT - padB);

    let area = `M ${x(0).toFixed(1)} ${elevBottom}`;
    for (const s of stream) area += ` L ${x(s.d).toFixed(1)} ${ey(s.e).toFixed(1)}`;
    area += ` L ${x(maxD).toFixed(1)} ${elevBottom} Z`;

    const line = stream.map((s, i) => `${i ? 'L' : 'M'} ${x(s.d).toFixed(1)} ${py(s.p).toFixed(1)}`).join(' ');

    const kmTicks: number[] = [];
    for (let km = 0; km * 1000 <= maxD + 1; km += Math.max(1, Math.round(maxD / 1000 / 6))) kmTicks.push(km);

    const avgPace = Math.round(paces.reduce((a, b) => a + b, 0) / paces.length);

    return (
        <div>
            <div className="mb-2 flex items-center gap-4 text-xs text-neutral-500">
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-0.5 w-4 rounded bg-brand-500" /> Allure ({paceLabel(avgPace)}/km moy.)
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-2.5 w-3 rounded-sm bg-neutral-300" /> Dénivelé
                </span>
            </div>
            <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full" preserveAspectRatio="none">
                {[0.25, 0.5, 0.75].map((f) => (
                    <line key={f} x1={0} x2={W} y1={padT + f * (H - padT - padB)} y2={padT + f * (H - padT - padB)} stroke="#f0f0f2" strokeWidth={1} />
                ))}
                <path d={area} fill="#e6e6ea" />
                <path d={line} fill="none" stroke="#fc4c02" strokeWidth={2.4} strokeLinejoin="round" strokeLinecap="round" />
                {kmTicks.map((km) => (
                    <text key={km} x={x(km * 1000)} y={H - 6} fontSize={12} fill="#9a9aa2" textAnchor={km === 0 ? 'start' : 'middle'}>
                        {km} km
                    </text>
                ))}
            </svg>
        </div>
    );
}
