import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft, Check, RefreshCw, Send, Sparkles } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { formatDate, formatKilometers, formatPace } from '@/features/activity/domain/format';

interface Proposal {
    date: string;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetPaceSecondsPerKm: number | null;
    rationale: string;
}

interface Message {
    id: string;
    role: 'athlete' | 'coach';
    text: string;
    proposal: Proposal | null;
    proposalApplied: boolean;
}

interface Day {
    date: string;
    type: string;
    title: string;
    description: string;
    targetDistanceMeters: number | null;
    targetDurationSeconds: number | null;
    targetPaceSecondsPerKm: number | null;
    actualSummary: string | null;
}

interface Props {
    programId: string;
    cycleId: string;
    programName: string;
    day: Day;
    conversation: { id: string; messages: Message[] } | null;
}

function renderRich(text: string): ReactNode[] {
    // Minimal markdown: render **bold**; newlines are kept via whitespace-pre-wrap.
    return text.split('**').map((part, i) =>
        i % 2 === 1 ? (
            <strong key={i} className="font-semibold text-neutral-100">
                {part}
            </strong>
        ) : (
            <span key={i}>{part}</span>
        ),
    );
}

const SUGGESTIONS = [
    'Je me sens fatigué·e aujourd\'hui, je le sens pas.',
    'Pourquoi cette séance ? Explique-moi.',
    'Je n\'ai que 40 min aujourd\'hui.',
];

export default function Coach({ programId, cycleId, programName, day, conversation }: Props) {
    const form = useForm({ cycle_id: cycleId, date: day.date, message: '' });
    const messages = conversation?.messages ?? [];

    function submit(e: FormEvent) {
        e.preventDefault();
        if (form.data.message.trim()) form.post(`/programme/${programId}/coach/message`, { preserveScroll: true });
    }

    function applyProposal(messageId: string) {
        router.post(
            `/programme/${programId}/coach/apply`,
            { conversation_id: conversation?.id, message_id: messageId, date: day.date, cycle_id: cycleId },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`Coach · ${formatDate(day.date)}`} />
            <Link
                href={`/programme/${programId}`}
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> {programName}
            </Link>

            <div className="mb-6 flex items-center gap-2">
                <Sparkles size={20} className="text-lime-400" />
                <h1 className="text-2xl font-bold tracking-tight">Coach · {formatDate(day.date)}</h1>
            </div>

            <div className="mx-auto max-w-2xl space-y-4">
                <Card>
                    <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">Séance du jour</p>
                    <p className="mt-1 text-base font-semibold text-neutral-100">{day.title}</p>
                    <p className="mt-0.5 text-sm text-neutral-400">{day.description}</p>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
                        {day.targetDistanceMeters && <span>Cible {formatKilometers(day.targetDistanceMeters)} km</span>}
                        {day.targetPaceSecondsPerKm && <span>{formatPace(day.targetPaceSecondsPerKm)}</span>}
                        {day.actualSummary && <span className="text-lime-300">Réalisé : {day.actualSummary}</span>}
                    </div>
                </Card>

                {messages.length === 0 && (
                    <Card>
                        <p className="text-sm text-neutral-400">
                            Discute de cette séance avec ton coach : fatigue, douleurs, contraintes de temps, ou juste « pourquoi
                            cette séance ». Il connaît tes allures et ton objectif, et peut te proposer un ajustement.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {SUGGESTIONS.map((s) => (
                                <button
                                    key={s}
                                    onClick={() => form.setData('message', s)}
                                    className="cursor-pointer rounded-full border border-neutral-800 px-3 py-1.5 text-xs text-neutral-300 transition-colors hover:border-neutral-700 hover:text-neutral-100"
                                >
                                    {s}
                                </button>
                            ))}
                        </div>
                    </Card>
                )}

                <div className="space-y-3">
                    {messages.map((m) =>
                        m.role === 'athlete' ? (
                            <div key={m.id} className="flex justify-end">
                                <p className="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-sm bg-lime-400/15 px-4 py-2.5 text-sm text-neutral-100">
                                    {m.text}
                                </p>
                            </div>
                        ) : (
                            <div key={m.id} className="flex flex-col items-start gap-2">
                                <div className="max-w-[90%] whitespace-pre-wrap rounded-2xl rounded-bl-sm border border-neutral-800 bg-neutral-900/60 px-4 py-2.5 text-sm text-neutral-200">
                                    {renderRich(m.text)}
                                </div>
                                {m.proposal && (
                                    <div className="w-full max-w-[90%] rounded-xl border border-lime-400/30 bg-lime-400/[0.05] p-3">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-lime-300">
                                            Proposition · {formatDate(m.proposal.date)}
                                        </p>
                                        <p className="mt-1 text-sm font-semibold text-neutral-100">{m.proposal.title}</p>
                                        <p className="mt-0.5 text-xs text-neutral-400">{m.proposal.description}</p>
                                        <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-neutral-500">
                                            {m.proposal.targetDistanceMeters && (
                                                <span>{formatKilometers(m.proposal.targetDistanceMeters)} km</span>
                                            )}
                                            {m.proposal.targetPaceSecondsPerKm && (
                                                <span>{formatPace(m.proposal.targetPaceSecondsPerKm)}</span>
                                            )}
                                        </div>
                                        {m.proposalApplied ? (
                                            <p className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-lime-300">
                                                <Check size={15} /> Appliqué au plan
                                            </p>
                                        ) : (
                                            <button
                                                onClick={() => applyProposal(m.id)}
                                                className="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-lime-400 px-4 py-2 text-sm font-semibold text-neutral-950 transition-colors hover:bg-lime-300"
                                            >
                                                <Check size={15} /> Appliquer ce changement
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        ),
                    )}

                    {form.processing && (
                        <div className="flex items-center gap-2 text-sm text-neutral-500">
                            <RefreshCw size={15} className="animate-spin" /> Le coach réfléchit…
                        </div>
                    )}
                </div>

                {form.errors.message && <p className="text-xs text-red-400">{form.errors.message}</p>}

                <form onSubmit={submit} className="sticky bottom-4 flex items-end gap-2">
                    <textarea
                        value={form.data.message}
                        onChange={(e) => form.setData('message', e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                submit(e);
                            }
                        }}
                        rows={2}
                        placeholder="Parle à ton coach…"
                        disabled={form.processing}
                        className="flex-1 resize-none rounded-xl border border-neutral-800 bg-neutral-900 px-4 py-3 text-sm text-neutral-100 outline-none focus:border-lime-400/60 disabled:opacity-60"
                    />
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.message.trim()}
                        className="flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-lime-400 text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                        aria-label="Envoyer"
                    >
                        <Send size={18} />
                    </button>
                </form>
            </div>
        </>
    );
}

Coach.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
