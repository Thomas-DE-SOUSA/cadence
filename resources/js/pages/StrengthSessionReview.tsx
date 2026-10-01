import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Trophy } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';

type ProgressionKind = 'weight' | 'reps';

interface Progression {
    exerciseId: string;
    name: string;
    kind: ProgressionKind;
    fromWeightKg: number | null;
    toWeightKg: number | null;
    weightDeltaKg: number;
    fromReps: number | null;
    toReps: number | null;
    repsDelta: number;
}

interface SessionSummary {
    id: string;
    title: string;
    date: string;
    durationSeconds: number | null;
    exerciseCount: number;
    totalSets: number;
    volumeKg: number;
    comparedVolumeKg: number | null;
    comparedPreviousVolumeKg: number | null;
}

interface Props {
    session: SessionSummary;
    progressions: Progression[];
    firstTimeCount: number;
    stableCount: number;
    backUrl: string;
}

const fmtDay = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
const fmtKg = (n: number) => n.toLocaleString('fr-FR', { maximumFractionDigits: 1 });

function fmtDuration(seconds: number | null): string | null {
    if (seconds === null || seconds <= 0) return null;
    const min = Math.round(seconds / 60);
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m === 0 ? `${h} h` : `${h} h ${m}`;
}

function ProgressionRow({ p }: { p: Progression }) {
    const isWeight = p.kind === 'weight';
    const bodyweight = p.toWeightKg === null || p.toWeightKg === 0;
    // "Soulevé sur la série" = load × reps of the top set (only when both are known).
    const tonnage = p.toWeightKg && p.toReps ? p.toWeightKg * p.toReps : null;

    return (
        <li className="py-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate font-bold text-neutral-900">{p.name}</p>
                    {isWeight ? (
                        <p className="mt-0.5 text-xs tabular-nums text-neutral-500">
                            {fmtKg(p.fromWeightKg ?? 0)} kg → <span className="font-semibold text-neutral-700">{fmtKg(p.toWeightKg ?? 0)} kg</span>
                            {p.toReps ? <span className="text-neutral-400"> · × {p.toReps}</span> : null}
                        </p>
                    ) : (
                        <p className="mt-0.5 text-xs tabular-nums text-neutral-500">
                            {!bodyweight ? `${fmtKg(p.toWeightKg ?? 0)} kg · ` : ''}
                            {p.fromReps} → <span className="font-semibold text-neutral-700">{p.toReps} reps</span>
                            <span className="text-neutral-400"> · {bodyweight ? 'poids du corps' : 'même charge'}</span>
                        </p>
                    )}
                    {tonnage !== null && (
                        <p className="mt-0.5 text-xs text-neutral-400">
                            ce qui représente <span className="font-semibold text-neutral-600">+{fmtKg(tonnage)} kg</span> soulevés en plus sur ta séance
                        </p>
                    )}
                </div>
                <div className="shrink-0 text-right">
                    <p className="text-lg font-black leading-none tabular-nums text-neutral-900">
                        {isWeight ? `+${fmtKg(p.weightDeltaKg)}` : `+${p.repsDelta}`}
                        <span className="text-xs font-semibold"> {isWeight ? 'kg' : 'reps'}</span>
                    </p>
                    <p className="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{isWeight ? 'charge' : 'reps'}</p>
                </div>
            </div>
        </li>
    );
}

export default function StrengthSessionReview({ session, progressions, firstTimeCount, stableCount, backUrl }: Props) {
    const count = progressions.length;
    const hasProgress = count > 0;
    const duration = fmtDuration(session.durationSeconds);

    const restParts: string[] = [];
    if (stableCount > 0) restParts.push(`${stableCount} stable${stableCount > 1 ? 's' : ''}`);
    if (firstTimeCount > 0) restParts.push(`${firstTimeCount} nouveau${firstTimeCount > 1 ? 'x' : ''}`);

    return (
        <>
            <Head title="Bilan de séance" />

            <div className="mb-6 flex items-start gap-3">
                <span
                    className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-md ${hasProgress ? 'bg-gradient-to-br from-amber-400 to-amber-600 shadow-amber-500/25' : 'bg-gradient-to-br from-brand-400 to-brand-600 shadow-brand-500/25'}`}
                >
                    <Trophy size={22} />
                </span>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Séance validée 💪</h1>
                    <p className="mt-0.5 text-sm capitalize text-neutral-500">
                        {session.title ? `${session.title} · ` : ''}
                        {fmtDay(session.date)}
                        {duration ? ` · ${duration}` : ''}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-3 gap-4 border-y border-neutral-100 py-4">
                <Stat value={`${session.exerciseCount}`} label={`exercice${session.exerciseCount > 1 ? 's' : ''}`} />
                <Stat value={`${session.totalSets}`} label={`série${session.totalSets > 1 ? 's' : ''}`} />
                <Stat value={fmtKg(session.volumeKg)} label="kg soulevés" />
            </div>

            {session.comparedVolumeKg !== null && session.comparedPreviousVolumeKg !== null && session.comparedPreviousVolumeKg > 0 && (
                <p className="mt-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-sm text-neutral-500">
                    <span className="text-neutral-400">Charge, à séries égales</span>
                    <span className="tabular-nums">
                        <span className="text-neutral-400">{fmtKg(session.comparedPreviousVolumeKg)} kg</span>
                        {' → '}
                        <span className="font-bold text-neutral-900">{fmtKg(session.comparedVolumeKg)} kg</span>
                    </span>
                    {(() => {
                        const delta = session.comparedVolumeKg - session.comparedPreviousVolumeKg;
                        if (delta === 0) return <span className="text-xs text-neutral-400">= dernière fois</span>;
                        return (
                            <span className={`rounded-full px-2 py-0.5 text-xs font-bold tabular-nums ${delta > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-neutral-100 text-neutral-500'}`}>
                                {delta > 0 ? '+' : '−'}
                                {fmtKg(Math.abs(delta))} kg
                            </span>
                        );
                    })()}
                </p>
            )}

            {hasProgress ? (
                <section className="mt-6">
                    <h2 className="mb-1 inline-flex items-center gap-1.5 text-sm font-bold text-neutral-800">
                        <Trophy size={15} className="text-amber-500" /> Progression sur {count} exercice{count > 1 ? 's' : ''}
                    </h2>
                    <ul className="divide-y divide-neutral-100">
                        {progressions.map((p) => (
                            <ProgressionRow key={p.exerciseId} p={p} />
                        ))}
                    </ul>
                </section>
            ) : (
                <p className="mt-6 rounded-lg bg-neutral-50 px-4 py-5 text-center text-sm text-neutral-500">
                    Rien de neuf côté charges cette fois — mais la séance est dans la boîte. La régularité paie. 👊
                </p>
            )}

            {restParts.length > 0 && <p className="mt-4 text-xs text-neutral-400">Sur le reste : {restParts.join(' · ')}.</p>}

            <Link
                href={backUrl}
                className="mt-6 flex w-full items-center justify-center rounded-xl bg-neutral-900 py-3.5 text-sm font-bold text-white transition-transform hover:-translate-y-0.5"
            >
                Retour à l'agenda
            </Link>
        </>
    );
}

function Stat({ value, label }: { value: string; label: string }) {
    return (
        <div className="text-center">
            <p className="text-lg font-black tabular-nums text-neutral-900">{value}</p>
            <p className="text-[11px] text-neutral-400">{label}</p>
        </div>
    );
}

StrengthSessionReview.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
