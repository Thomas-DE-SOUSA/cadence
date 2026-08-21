import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Check, RefreshCw, Send, Sparkles } from 'lucide-react';
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

const SUGGESTIONS = [
    'Je me sens fatigué·e, je le sens pas.',
    'Pourquoi cette séance ?',
    'Je n\'ai que 40 min aujourd\'hui.',
];

function renderRich(text: string): ReactNode[] {
    return text.split('**').map((part, i) =>
        i % 2 === 1 ? (
            <strong key={i} className="font-semibold text-neutral-900">
                {part}
            </strong>
        ) : (
            <span key={i}>{part}</span>
        ),
    );
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function CoachThread({
    programId,
    cycleId,
    date,
    onApplied,
}: {
    programId: string;
    cycleId: string;
    date: string;
    onApplied?: () => void;
}) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [liveText, setLiveText] = useState('');
    const [pendingAthlete, setPendingAthlete] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const bottomRef = useRef<HTMLDivElement>(null);

    const loadThread = useCallback(async () => {
        const res = await fetch(`/programme/${programId}/coach/thread?date=${encodeURIComponent(date)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        setMessages(data.conversation?.messages ?? []);
        setConversationId(data.conversation?.id ?? null);
    }, [programId, date]);

    useEffect(() => {
        void loadThread();
    }, [loadThread]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length, liveText, streaming]);

    function handleEvent(chunk: string) {
        let event = 'message';
        let data = '';
        for (const line of chunk.split('\n')) {
            if (line.startsWith('event:')) event = line.slice(6).trim();
            else if (line.startsWith('data:')) data += line.slice(5).trim();
        }
        if (!data) return;
        let parsed: { t?: string; message?: string };
        try {
            parsed = JSON.parse(data);
        } catch {
            return;
        }
        if (event === 'text') setLiveText((prev) => prev + (parsed.t ?? ''));
        else if (event === 'done')
            void loadThread().then(() => {
                setStreaming(false);
                setLiveText('');
                setPendingAthlete(null);
            });
        else if (event === 'error') {
            setError(parsed.message ?? 'Le coach est indisponible.');
            setStreaming(false);
            setPendingAthlete(null);
        }
    }

    async function submit(e: FormEvent) {
        e.preventDefault();
        const text = message.trim();
        if (!text || streaming) return;

        setMessage('');
        setPendingAthlete(text);
        setLiveText('');
        setError(null);
        setStreaming(true);

        try {
            const res = await fetch(`/programme/${programId}/coach/stream`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ cycle_id: cycleId, date, message: text }),
            });
            if (!res.ok || !res.body) throw new Error('stream failed');

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let idx;
                while ((idx = buffer.indexOf('\n\n')) >= 0) {
                    handleEvent(buffer.slice(0, idx));
                    buffer = buffer.slice(idx + 2);
                }
            }
        } catch {
            setError('Le coach est indisponible.');
            setStreaming(false);
            setPendingAthlete(null);
        }
    }

    async function applyProposal(messageId: string) {
        await fetch(`/programme/${programId}/coach/apply`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ conversation_id: conversationId, message_id: messageId }),
        });
        await loadThread();
        onApplied?.();
    }

    return (
        <div className="flex h-full min-h-0 flex-col">
            <p className="mb-3 flex shrink-0 items-center gap-1.5 text-sm font-semibold text-neutral-800">
                <Sparkles size={16} className="text-brand-500" /> Coach
            </p>

            <div className="min-h-0 flex-1 overflow-y-auto">
            {messages.length === 0 && !streaming && (
                <div className="mb-3">
                    <p className="text-sm text-neutral-500">
                        Discute de cette séance : fatigue, douleurs, temps limité, ou « pourquoi cette séance ». Il connaît tes
                        allures et ton objectif.
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {SUGGESTIONS.map((s) => (
                            <button
                                key={s}
                                onClick={() => setMessage(s)}
                                className="cursor-pointer rounded-full border border-neutral-200 px-2.5 py-1 text-xs text-neutral-700 transition-colors hover:border-neutral-300 hover:text-neutral-900"
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="space-y-3">
                {messages.map((m) =>
                    m.role === 'athlete' ? (
                        <div key={m.id} className="flex justify-end">
                            <p className="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-sm bg-brand-500/15 px-3.5 py-2 text-sm text-neutral-900">
                                {m.text}
                            </p>
                        </div>
                    ) : (
                        <div key={m.id} className="flex flex-col items-start gap-2">
                            <div className="max-w-[90%] whitespace-pre-wrap rounded-2xl rounded-bl-sm border border-neutral-200 bg-white px-3.5 py-2 text-sm text-neutral-800">
                                {renderRich(m.text)}
                            </div>
                            {m.proposal && (
                                <div className="w-full rounded-xl border border-brand-500/30 bg-brand-500/[0.05] p-3">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-600">
                                        Proposition · {formatDate(m.proposal.date)}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold text-neutral-900">{m.proposal.title}</p>
                                    <p className="mt-0.5 text-xs text-neutral-500">{m.proposal.description}</p>
                                    <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-neutral-500">
                                        {m.proposal.targetDistanceMeters && (
                                            <span>{formatKilometers(m.proposal.targetDistanceMeters)} km</span>
                                        )}
                                        {m.proposal.targetPaceSecondsPerKm && (
                                            <span>{formatPace(m.proposal.targetPaceSecondsPerKm)}</span>
                                        )}
                                    </div>
                                    {m.proposalApplied ? (
                                        <p className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                                            <Check size={15} /> Appliqué au plan
                                        </p>
                                    ) : (
                                        <button
                                            onClick={() => applyProposal(m.id)}
                                            className="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-brand-500 px-3.5 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-brand-600"
                                        >
                                            <Check size={15} /> Appliquer
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    ),
                )}

                {pendingAthlete && (
                    <div className="flex justify-end">
                        <p className="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-sm bg-brand-500/15 px-3.5 py-2 text-sm text-neutral-900">
                            {pendingAthlete}
                        </p>
                    </div>
                )}
                {streaming &&
                    (liveText === '' ? (
                        <div className="flex items-center gap-2 text-sm text-neutral-500">
                            <RefreshCw size={15} className="animate-spin" /> Le coach réfléchit…
                        </div>
                    ) : (
                        <div className="flex flex-col items-start">
                            <div className="max-w-[90%] whitespace-pre-wrap rounded-2xl rounded-bl-sm border border-neutral-200 bg-white px-3.5 py-2 text-sm text-neutral-800">
                                {renderRich(liveText)}
                                <span className="ml-0.5 inline-block h-3.5 w-1.5 animate-pulse bg-brand-500/70 align-middle" />
                            </div>
                        </div>
                    ))}
                <div ref={bottomRef} />
            </div>
            </div>

            {error && <p className="mt-2 shrink-0 text-xs text-red-600">{error}</p>}

            <form onSubmit={submit} className="mt-4 flex shrink-0 items-end gap-2">
                <textarea
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            submit(e);
                        }
                    }}
                    rows={2}
                    placeholder="Parle à ton coach…"
                    disabled={streaming}
                    className="flex-1 resize-none rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none focus:border-brand-500/60 disabled:opacity-60"
                />
                <button
                    type="submit"
                    disabled={streaming || !message.trim()}
                    className="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-brand-500 text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                    aria-label="Envoyer"
                >
                    <Send size={17} />
                </button>
            </form>
        </div>
    );
}
