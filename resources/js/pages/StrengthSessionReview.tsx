import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRight, Dumbbell, Repeat2, Trophy } from 'lucide-react';
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

function Stat({ value, label }: { value: string; label: string }) {
    return (
        <div className="text-center">
            <p className="text-lg font-bold tabular-nums text-neutral-900">{value}</p>
            <p className="text-[11px] text-neutral-400">{label}</p>
        </div>
    );
}

function ProgressionCard({ p }: { p: Progression }) {
    const isWeight = p.kind === 'weight';
    const bodyweight = p.toWeightKg === null || p.toWeightKg === 0;

    return (
        <li className="flex items-center gap-3 rounded-2xl border border-neutral-100 bg-white p-3.5 shadow-sm">
            <span
                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${isWeight ? 'bg-brand-50 text-brand-600' : 'bg-emerald-50 text-emerald-600'}`}
            >
                {isWeight ? <Dumbbell size={17} /> : <Repeat2 size={17} />}
            </span>

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold text-neutral-900">{p.name}</p>
                {isWeight ? (
                    <p className="mt-0.5 flex items-center gap-1.5 text-sm tabular-nums text-neutral-500">
                        <span>{fmtKg(p.fromWeightKg ?? 0)} kg</span>
                        <ArrowRight size={13} className="text-neutral-300" />
                        <span className="font-semibold text-neutral-900">{fmtKg(p.toWeightKg ?? 0)} kg</span>
                        {p.toReps ? <span className="text-neutral-400">· × {p.toReps}</span> : null}
                    </p>
                ) : (
                    <p className="mt-0.5 flex items-center gap-1.5 text-sm tabular-nums text-neutral-500">
                        {!bodyweight && <span>{fmtKg(p.toWeightKg ?? 0)} kg ·</span>}
                        <span>{p.fromReps} reps</span>
                        <ArrowRight size={13} className="text-neutral-300" />
                        <span className="font-semibold text-neutral-900">{p.toReps} reps</span>
                        <span className="text-neutral-400">· {bodyweight ? 'poids du corps' : 'même charge'}</span>
                    </p>
                )}
            </div>

            <span
                className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-bold tabular-nums ${isWeight ? 'bg-brand-500 text-white' : 'bg-emerald-500 text-white'}`}
            >
                {isWeight ? `+${fmtKg(p.weightDeltaKg)} kg` : `+${p.repsDelta} reps`}
            </span>
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

            <div className="mb-6 grid grid-cols-3 gap-2 rounded-2xl bg-neutral-50 py-3.5">
                <Stat value={`${session.exerciseCount}`} label={`exercice${session.exerciseCount > 1 ? 's' : ''}`} />
                <Stat value={`${session.totalSets}`} label={`série${session.totalSets > 1 ? 's' : ''}`} />
                <Stat value={fmtKg(session.volumeKg)} label="kg soulevés" />
            </div>

            {hasProgress ? (
                <section>
                    <h2 className="mb-3 text-sm font-bold text-neutral-800">
                        🏆 Tu as progressé sur {count} exercice{count > 1 ? 's' : ''}
                    </h2>
                    <ul className="space-y-2.5">
                        {progressions.map((p) => (
                            <ProgressionCard key={p.exerciseId} p={p} />
                        ))}
                    </ul>
                </section>
            ) : (
                <section className="rounded-2xl bg-neutral-50 px-4 py-6 text-center">
                    <p className="text-sm font-semibold text-neutral-800">Rien de neuf côté charges cette fois.</p>
                    <p className="mt-1 text-sm text-neutral-500">Mais la séance est dans la boîte — la régularité paie. 👊</p>
                </section>
            )}

            {restParts.length > 0 && <p className="mt-4 text-center text-xs text-neutral-400">Sur le reste : {restParts.join(' · ')}.</p>}

            <Link
                href={backUrl}
                className="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-neutral-900 py-3.5 text-sm font-bold text-white transition-transform hover:-translate-y-0.5"
            >
                Retour à l'agenda
            </Link>
        </>
    );
}

StrengthSessionReview.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
