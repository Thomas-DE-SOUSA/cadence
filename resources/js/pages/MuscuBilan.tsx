import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Dumbbell, Scale, Send, Sparkles } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Strength {
    hasData: boolean;
    sessions: number;
    workingSets: number;
    tonnageKg: number;
    legSessions: number;
    prevSessions: number;
    prevTonnageKg: number;
    daysSinceLast: number | null;
    muscleBalance: { muscle: string; label: string; sets: number }[];
    weightAvgKg: number | null;
    weightPrevAvgKg: number | null;
}
interface ThreadMessage {
    role: 'athlete' | 'coach';
    text: string;
}
interface Props {
    today: string;
    weekStart: string;
    weekEnd: string;
    goal: string | null;
    strength: Strength;
    thread: ThreadMessage[];
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const fmtDay = (iso: string) => new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
const fmtKg = (n: number) => n.toLocaleString('fr-FR', { maximumFractionDigits: 1 });

/** Minimal Markdown for the coach verdict: ## / ### headings, - bullets, **bold**, paragraphs. */
function Markdown({ text }: { text: string }) {
    const blocks: ReactNode[] = [];
    let list: string[] = [];
    const flush = () => {
        if (list.length) {
            blocks.push(
                <ul key={`u${blocks.length}`} className="my-1 list-disc space-y-0.5 pl-5">
                    {list.map((li, i) => (
                        <li key={i}>{rich(li)}</li>
                    ))}
                </ul>,
            );
            list = [];
        }
    };
    for (const raw of text.split('\n')) {
        const line = raw.trimEnd();
        if (/^###\s+/.test(line)) {
            flush();
            blocks.push(<h3 key={blocks.length} className="mt-3 text-sm font-bold text-neutral-900">{rich(line.replace(/^###\s+/, ''))}</h3>);
        } else if (/^##\s+/.test(line)) {
            flush();
            blocks.push(<h2 key={blocks.length} className="mt-3 text-base font-bold text-neutral-900">{rich(line.replace(/^##\s+/, ''))}</h2>);
        } else if (/^[-*]\s+/.test(line)) {
            list.push(line.replace(/^[-*]\s+/, ''));
        } else if (line.trim() === '') {
            flush();
        } else {
            flush();
            blocks.push(<p key={blocks.length} className="my-1">{rich(line)}</p>);
        }
    }
    flush();
    return <div className="text-sm leading-relaxed text-neutral-700">{blocks}</div>;
}

function rich(text: string): ReactNode[] {
    return text.split('**').map((part, i) => (i % 2 === 1 ? <strong key={i} className="font-semibold text-neutral-900">{part}</strong> : <span key={i}>{part}</span>));
}

function StrengthCard({ s }: { s: Strength }) {
    if (!s.hasData) {
        return <p className="text-sm text-neutral-400">Aucune séance de muscu cette semaine ni la précédente.</p>;
    }
    const weightDelta = s.weightAvgKg !== null && s.weightPrevAvgKg !== null ? s.weightAvgKg - s.weightPrevAvgKg : null;
    const max = Math.max(...s.muscleBalance.map((m) => m.sets), 1);
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-3 gap-2 text-center">
                <Stat value={`${s.sessions}`} label={`séance${s.sessions > 1 ? 's' : ''}`} hint={`${s.prevSessions} sem. préc.`} />
                <Stat value={fmtKg(s.tonnageKg)} label="kg soulevés" hint={`${fmtKg(s.prevTonnageKg)} préc.`} />
                <Stat value={`${s.workingSets}`} label="séries" hint={`${s.legSessions} jambes`} />
            </div>
            {s.muscleBalance.length > 0 && (
                <ul className="space-y-1.5">
                    {s.muscleBalance.map((m) => (
                        <li key={m.muscle} className="flex items-center gap-3">
                            <span className="w-24 shrink-0 truncate text-xs text-neutral-600">{m.label}</span>
                            <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-neutral-100">
                                <div className="h-full rounded-full bg-brand-500" style={{ width: `${(m.sets / max) * 100}%` }} />
                            </div>
                            <span className="w-6 shrink-0 text-right text-xs font-bold tabular-nums text-neutral-900">{m.sets}</span>
                        </li>
                    ))}
                </ul>
            )}
            {s.weightAvgKg !== null && (
                <p className="rounded-lg bg-neutral-50 px-3 py-2 text-sm text-neutral-600">
                    <Scale size={13} className="mr-1 inline text-brand-600" /> Poids moyen <span className="font-semibold text-neutral-900">{fmtKg(s.weightAvgKg)} kg</span>
                    {weightDelta !== null && weightDelta !== 0 && (
                        <span className={weightDelta < 0 ? 'text-emerald-600' : 'text-neutral-500'}> ({weightDelta < 0 ? '↓' : '↑'} {fmtKg(Math.abs(weightDelta))} kg)</span>
                    )}
                </p>
            )}
            {s.daysSinceLast !== null && (
                <p className="text-xs text-neutral-400">Dernière séance muscu il y a {s.daysSinceLast} jour{s.daysSinceLast > 1 ? 's' : ''}.</p>
            )}
        </div>
    );
}

function Stat({ value, label, hint }: { value: string; label: string; hint: string }) {
    return (
        <div className="rounded-xl bg-neutral-50 px-2 py-3">
            <p className="text-xl font-extrabold tabular-nums text-neutral-900">{value}</p>
            <p className="text-[11px] text-neutral-500">{label}</p>
            <p className="text-[10px] text-neutral-400">{hint}</p>
        </div>
    );
}

export default function MuscuBilan({ weekStart, weekEnd, goal, strength, thread: initialThread }: Props) {
    const [thread, setThread] = useState<ThreadMessage[]>(initialThread);
    const [input, setInput] = useState('');
    const [live, setLive] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState('');
    const liveRef = useRef('');

    const send = async (message: string) => {
        if (streaming || message.trim() === '') return;
        setError('');
        setStreaming(true);
        setLive('');
        liveRef.current = '';
        setThread((t) => [...t, { role: 'athlete', text: message }]);
        setInput('');

        try {
            const res = await fetch('/muscu/bilan/stream', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ week_start: weekStart, message }),
            });
            if (!res.ok || !res.body) throw new Error('Le coach est indisponible.');
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });
                let idx: number;
                while ((idx = buffer.indexOf('\n\n')) >= 0) {
                    const chunk = buffer.slice(0, idx);
                    buffer = buffer.slice(idx + 2);
                    let event = 'message';
                    let data = '';
                    for (const l of chunk.split('\n')) {
                        if (l.startsWith('event:')) event = l.slice(6).trim();
                        else if (l.startsWith('data:')) data += l.slice(5).trim();
                    }
                    if (!data) continue;
                    let payload: { t?: string; message?: string; thread?: ThreadMessage[] };
                    try {
                        payload = JSON.parse(data);
                    } catch {
                        continue; // skip a malformed / non-JSON frame (heartbeat, partial)
                    }
                    if (event === 'text') {
                        liveRef.current += payload.t ?? '';
                        setLive(liveRef.current);
                    } else if (event === 'done') {
                        const res2 = await fetch(`/muscu/bilan/thread?week_start=${weekStart}`, { headers: { Accept: 'application/json' } });
                        if (res2.ok) {
                            const json = await res2.json();
                            setThread(json.thread ?? []);
                        } else {
                            // Refetch failed — keep the streamed verdict rather than blanking it.
                            setThread((t) => [...t, { role: 'coach', text: liveRef.current }]);
                        }
                        setLive('');
                    } else if (event === 'error') {
                        setError(payload.message ?? 'Le coach est indisponible.');
                    }
                }
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Le coach est indisponible.');
        } finally {
            setStreaming(false);
        }
    };

    const hasThread = thread.length > 0;

    return (
        <>
            <Head title="Bilan" />
            <div className="mb-6 flex items-start gap-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-md shadow-brand-500/25">
                    <Sparkles size={22} />
                </span>
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Bilan de la semaine</h1>
                    <p className="mt-0.5 text-sm text-neutral-500">
                        {fmtDay(weekStart)} – {fmtDay(weekEnd)}
                        {goal ? ` · ${goal}` : ''}
                    </p>
                </div>
            </div>

            <Card title={<span className="inline-flex items-center gap-1.5"><Dumbbell size={15} className="text-brand-600" /> Ta semaine — muscu</span>}>
                <StrengthCard s={strength} />
            </Card>

            <div className="mt-4">
                <Card title="Le verdict du coach">
                    {!hasThread && !streaming && !live && (
                        <p className="mb-3 text-sm text-neutral-500">Le coach croise ta course et ta muscu pour te dire si la semaine est bonne et quoi ajuster.</p>
                    )}

                    <div className="space-y-3" aria-live="polite">
                        {thread.map((m, i) =>
                            m.role === 'athlete' ? (
                                <div key={i} className="ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-brand-600 px-3.5 py-2 text-sm text-white">{m.text}</div>
                            ) : (
                                <div key={i} className="max-w-[95%] rounded-2xl rounded-bl-sm bg-neutral-50 px-3.5 py-2.5"><Markdown text={m.text} /></div>
                            ),
                        )}
                        {live && (
                            <div className="max-w-[95%] rounded-2xl rounded-bl-sm bg-neutral-50 px-3.5 py-2.5">
                                <Markdown text={live} />
                                <span className="ml-0.5 inline-block h-3.5 w-1.5 animate-pulse bg-brand-400 align-middle" />
                            </div>
                        )}
                    </div>

                    {error && <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-600">⚠️ {error}</p>}

                    {!hasThread && !streaming && (
                        <button
                            onClick={() => send('Fais le bilan de ma semaine.')}
                            className="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 py-3.5 text-sm font-bold text-white shadow-md shadow-brand-500/25 transition-transform hover:-translate-y-0.5"
                        >
                            <Sparkles size={18} /> Générer le bilan
                        </button>
                    )}

                    {hasThread && (
                        <div className="mt-4 flex gap-2">
                            <input
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && send(input)}
                                placeholder="Une question sur ta semaine…"
                                disabled={streaming}
                                className="flex-1 rounded-xl border border-neutral-200 px-3 py-2 text-sm focus:border-neutral-400 focus:outline-none disabled:opacity-50"
                            />
                            <button
                                onClick={() => send(input)}
                                disabled={streaming || input.trim() === ''}
                                aria-label="Envoyer"
                                className="flex items-center justify-center rounded-xl bg-neutral-900 px-4 text-white disabled:opacity-40"
                            >
                                <Send size={16} />
                            </button>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

MuscuBilan.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
