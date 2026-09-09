import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ChevronLeft, ChevronRight, Loader2, Moon, Sun, Sunrise, Trash2, Utensils } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Macro {
    kcal: number;
    protein: number;
    fat: number;
    carbs: number;
}
interface Entry {
    id: string;
    description: string;
    kcal: number;
    protein: number;
    fat: number;
    carbs: number;
}
interface Meal extends Macro {
    key: 'matin' | 'midi' | 'soir';
    label: string;
    share: number;
    note: string;
    entries: Entry[];
    subtotal: Macro;
}
interface Props {
    date: string;
    daily: Macro;
    meals: Meal[];
    totals: Macro;
    remaining: Macro;
}

const mealIcon: Record<string, LucideIcon> = { matin: Sunrise, midi: Sun, soir: Moon };

/** Y-M-D + day offset, at local midnight (no timezone drift). */
function shiftDate(iso: string, days: number): string {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    const dt = new Date(y, m - 1, d + days);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}
function longDate(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}
function todayIso(): string {
    const dt = new Date();
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}
function guessMeal(): 'matin' | 'midi' | 'soir' {
    const h = new Date().getHours();
    if (h < 11) return 'matin';
    if (h < 17) return 'midi';
    return 'soir';
}

function MacroRow({ label, done, target, unit = 'g' }: { label: string; done: number; target: number; unit?: string }) {
    const pct = Math.min(100, target > 0 ? (done / target) * 100 : 0);
    const over = done > target;
    return (
        <div>
            <div className="flex justify-between text-xs font-semibold">
                <span className="text-neutral-500">{label}</span>
                <span className={over ? 'text-red-500' : 'text-neutral-700'}>
                    {done} / {target} {unit}
                </span>
            </div>
            <div className="mt-1 h-2 overflow-hidden rounded-full bg-neutral-100">
                <div className={`h-full rounded-full ${over ? 'bg-red-400' : 'bg-brand-500'}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

export default function MuscuNutrition({ date, daily, meals, totals, remaining }: Props) {
    const [text, setText] = useState('');
    const [meal, setMeal] = useState<'matin' | 'midi' | 'soir'>(guessMeal());
    const [busy, setBusy] = useState(false);
    const isToday = date === todayIso();

    const go = (d: string) => router.get('/muscu/nutrition', { date: d }, { preserveScroll: true });

    const submit = () => {
        if (text.trim() === '' || busy) return;
        router.post(
            '/muscu/nutrition',
            { text: text.trim(), meal, date },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onSuccess: () => setText(''),
            },
        );
    };

    const remove = (id: string) => router.post(`/muscu/nutrition/${id}/supprimer`, { date }, { preserveScroll: true });

    const kcalPct = Math.min(100, daily.kcal > 0 ? (totals.kcal / daily.kcal) * 100 : 0);
    const kcalOver = totals.kcal > daily.kcal;

    return (
        <>
            <Head title="Nutrition" />
            <div className="mx-auto max-w-2xl space-y-5 pb-24">
                <header className="flex items-center gap-3">
                    <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white">
                        <Utensils className="h-5 w-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900">Nutrition</h1>
                        <p className="text-sm text-neutral-500">Suivi journalier — lean bulk</p>
                    </div>
                </header>

                {/* Date navigation */}
                <div className="flex items-center justify-between rounded-2xl border border-neutral-200 bg-white px-3 py-2 shadow-sm shadow-neutral-200/60">
                    <button onClick={() => go(shiftDate(date, -1))} className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100" aria-label="Jour précédent">
                        <ChevronLeft size={18} />
                    </button>
                    <div className="text-center">
                        <p className="text-sm font-semibold capitalize text-neutral-800">{longDate(date)}</p>
                        {!isToday && (
                            <button onClick={() => go(todayIso())} className="text-xs font-semibold text-brand-600">
                                Revenir à aujourd'hui
                            </button>
                        )}
                    </div>
                    <button
                        onClick={() => go(shiftDate(date, 1))}
                        disabled={isToday}
                        className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100 disabled:opacity-30"
                        aria-label="Jour suivant"
                    >
                        <ChevronRight size={18} />
                    </button>
                </div>

                {/* Daily summary */}
                <Card title="Total du jour">
                    <div className="flex items-end justify-between">
                        <div className="flex items-end gap-2">
                            <span className={`text-4xl font-black ${kcalOver ? 'text-red-500' : 'text-neutral-900'}`}>{totals.kcal.toLocaleString('fr-FR')}</span>
                            <span className="mb-1 text-sm font-semibold text-neutral-500">/ {daily.kcal.toLocaleString('fr-FR')} kcal</span>
                        </div>
                        <span className={`text-sm font-semibold ${remaining.kcal < 0 ? 'text-red-500' : 'text-brand-600'}`}>
                            {remaining.kcal >= 0 ? `${remaining.kcal.toLocaleString('fr-FR')} restantes` : `${Math.abs(remaining.kcal).toLocaleString('fr-FR')} au-dessus`}
                        </span>
                    </div>
                    <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-neutral-100">
                        <div className={`h-full rounded-full ${kcalOver ? 'bg-red-400' : 'bg-brand-500'}`} style={{ width: `${kcalPct}%` }} />
                    </div>
                    <div className="mt-4 space-y-2.5">
                        <MacroRow label="Protéines" done={totals.protein} target={daily.protein} />
                        <MacroRow label="Glucides" done={totals.carbs} target={daily.carbs} />
                        <MacroRow label="Lipides" done={totals.fat} target={daily.fat} />
                    </div>
                </Card>

                {/* Add food */}
                <Card title="Ajouter ce que tu as mangé">
                    <div className="mb-2 flex gap-1.5">
                        {(['matin', 'midi', 'soir'] as const).map((m) => (
                            <button
                                key={m}
                                onClick={() => setMeal(m)}
                                className={`flex-1 rounded-lg border px-2 py-1.5 text-sm font-semibold capitalize transition-colors ${
                                    meal === m ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-neutral-200 bg-white text-neutral-500'
                                }`}
                            >
                                {m}
                            </button>
                        ))}
                    </div>
                    <textarea
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        rows={2}
                        maxLength={500}
                        placeholder="ex : une énorme part de lasagne, un Monster, 25 bâtons du berger"
                        className="w-full resize-none rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none"
                    />
                    <button
                        onClick={submit}
                        disabled={busy || text.trim() === ''}
                        className="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-600 disabled:opacity-40"
                    >
                        {busy ? (
                            <>
                                <Loader2 className="h-4 w-4 animate-spin" /> Estimation IA…
                            </>
                        ) : (
                            'Estimer & ajouter'
                        )}
                    </button>
                    <p className="mt-2 text-xs text-neutral-400">L'IA estime kcal + macros. Approximatif — supprime et reformule si c'est à côté.</p>
                </Card>

                {/* Meals */}
                {meals.map((m) => {
                    const Icon = mealIcon[m.key] ?? Utensils;
                    return (
                        <Card key={m.key}>
                            <div className="mb-3 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Icon className="h-5 w-5 text-brand-600" />
                                    <h2 className="text-base font-bold text-neutral-900">{m.label}</h2>
                                </div>
                                <span className="text-sm font-semibold text-neutral-700">
                                    {m.subtotal.kcal.toLocaleString('fr-FR')}
                                    <span className="text-xs font-medium text-neutral-400"> / {m.kcal.toLocaleString('fr-FR')} kcal</span>
                                </span>
                            </div>
                            {m.entries.length === 0 ? (
                                <p className="text-sm text-neutral-400">Rien pour l'instant.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {m.entries.map((e) => (
                                        <li key={e.id} className="flex items-start justify-between gap-2 rounded-lg bg-neutral-50 px-3 py-2">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-neutral-800">{e.description}</p>
                                                <p className="text-xs text-neutral-500">
                                                    {e.kcal} kcal · P {e.protein} · G {e.carbs} · L {e.fat}
                                                </p>
                                            </div>
                                            <button onClick={() => remove(e.id)} className="shrink-0 rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-500" aria-label="Supprimer">
                                                <Trash2 size={15} />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    );
                })}

                <p className="px-1 text-center text-xs text-neutral-400">
                    Cible lean bulk : {daily.kcal.toLocaleString('fr-FR')} kcal · {daily.protein} g protéines · {daily.carbs} g glucides · {daily.fat} g lipides.
                </p>
            </div>
        </>
    );
}

MuscuNutrition.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
