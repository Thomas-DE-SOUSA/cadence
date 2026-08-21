import { useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';

interface StreamPoint {
    d: number; // cumulative distance (m)
    e: number; // elevation (m)
    p: number; // pace (s/km)
}

function paceLabel(seconds: number): string {
    const s = Math.round(seconds);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** Strava-like profile: grey elevation area + orange pace line, with a hover crosshair. */
export function ProfileChart({ stream }: { stream: StreamPoint[] }) {
    const ref = useRef<HTMLDivElement>(null);
    const [hover, setHover] = useState<number | null>(null);

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
    const sortedP = [...paces].sort((a, b) => a - b);
    const minP = sortedP[Math.floor(sortedP.length * 0.02)];
    const maxP = sortedP[Math.floor(sortedP.length * 0.98)];

    const x = (d: number) => padX + (d / maxD) * (W - 2 * padX);
    const elevTop = H * 0.45;
    const elevBottom = H - padB;
    const ey = (e: number) => elevBottom - ((e - minE) / (maxE - minE || 1)) * (elevBottom - elevTop);
    const clamp = (v: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, v));
    const py = (p: number) => padT + ((clamp(p, minP, maxP) - minP) / (maxP - minP || 1)) * (H - padT - padB);

    let area = `M ${x(0).toFixed(1)} ${elevBottom}`;
    for (const s of stream) area += ` L ${x(s.d).toFixed(1)} ${ey(s.e).toFixed(1)}`;
    area += ` L ${x(maxD).toFixed(1)} ${elevBottom} Z`;

    const line = stream.map((s, i) => `${i ? 'L' : 'M'} ${x(s.d).toFixed(1)} ${py(s.p).toFixed(1)}`).join(' ');

    const kmTicks: number[] = [];
    for (let km = 0; km * 1000 <= maxD + 1; km += Math.max(1, Math.round(maxD / 1000 / 6))) kmTicks.push(km);

    const avgPace = Math.round(paces.reduce((a, b) => a + b, 0) / paces.length);

    function onMove(e: ReactPointerEvent<HTMLDivElement>) {
        if (!ref.current) return;
        const rect = ref.current.getBoundingClientRect();
        const ratio = clamp((e.clientX - rect.left) / rect.width, 0, 1);
        const targetD = ratio * maxD;
        let best = 0;
        let bestDelta = Infinity;
        for (let i = 0; i < stream.length; i++) {
            const delta = Math.abs(stream[i].d - targetD);
            if (delta < bestDelta) {
                bestDelta = delta;
                best = i;
            }
        }
        setHover(best);
    }

    const hovered = hover !== null ? stream[hover] : null;
    const hoverLeft = hovered ? (x(hovered.d) / W) * 100 : 0;

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

            <div ref={ref} className="relative" onPointerMove={onMove} onPointerLeave={() => setHover(null)}>
                <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full touch-none" preserveAspectRatio="none">
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
                    {hovered && (
                        <g>
                            <line x1={x(hovered.d)} x2={x(hovered.d)} y1={padT} y2={elevBottom} stroke="#fc4c02" strokeWidth={1} strokeDasharray="4 3" opacity={0.6} />
                            <circle cx={x(hovered.d)} cy={py(hovered.p)} r={4} fill="#fc4c02" stroke="#fff" strokeWidth={1.5} />
                            <circle cx={x(hovered.d)} cy={ey(hovered.e)} r={3.5} fill="#9a9aa2" stroke="#fff" strokeWidth={1.5} />
                        </g>
                    )}
                </svg>

                {hovered && (
                    <div
                        className="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-xs shadow-md"
                        style={{ left: `${clamp(hoverLeft, 6, 94)}%` }}
                    >
                        <span className="font-semibold tabular-nums text-neutral-900">{(hovered.d / 1000).toFixed(2)} km</span>
                        <span className="mx-1.5 text-neutral-300">·</span>
                        <span className="font-semibold tabular-nums text-brand-600">{paceLabel(hovered.p)}/km</span>
                        <span className="mx-1.5 text-neutral-300">·</span>
                        <span className="tabular-nums text-neutral-500">{hovered.e} m</span>
                    </div>
                )}
            </div>
        </div>
    );
}
