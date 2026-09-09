import { Head } from '@inertiajs/react';
import { Moon, Sun, Sunrise, Utensils } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Meal {
    key: string;
    label: string;
    share: number;
    kcal: number;
    protein: number;
    fat: number;
    carbs: number;
    note: string;
}
interface Daily {
    kcal: number;
    protein: number;
    fat: number;
    carbs: number;
}
interface Props {
    daily: Daily;
    meals: Meal[];
}

const mealIcon: Record<string, LucideIcon> = { matin: Sunrise, midi: Sun, soir: Moon };

/** A stacked bar showing the protein / carbs / fat split of a meal by calories. */
function MacroBar({ protein, fat, carbs }: { protein: number; fat: number; carbs: number }) {
    const kcalP = protein * 4;
    const kcalC = carbs * 4;
    const kcalF = fat * 9;
    const total = kcalP + kcalC + kcalF || 1;
    return (
        <div className="flex h-2.5 overflow-hidden rounded-full bg-neutral-100">
            <div className="bg-brand-500" style={{ width: `${(kcalP / total) * 100}%` }} title="Protéines" />
            <div className="bg-amber-400" style={{ width: `${(kcalC / total) * 100}%` }} title="Glucides" />
            <div className="bg-neutral-400" style={{ width: `${(kcalF / total) * 100}%` }} title="Lipides" />
        </div>
    );
}

function MacroChips({ protein, fat, carbs }: { protein: number; fat: number; carbs: number }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">P {protein} g</span>
            <span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Gluc {carbs} g</span>
            <span className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600">Lip {fat} g</span>
        </div>
    );
}

export default function MuscuNutrition({ daily, meals }: Props) {
    return (
        <AppLayout>
            <Head title="Nutrition" />
            <div className="mx-auto max-w-2xl space-y-5 pb-24">
                <header className="flex items-center gap-3">
                    <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white">
                        <Utensils className="h-5 w-5" />
                    </span>
                    <div>
                        <h1 className="text-xl font-bold text-neutral-900">Nutrition</h1>
                        <p className="text-sm text-neutral-500">Lean bulk — répartition matin / midi / soir</p>
                    </div>
                </header>

                {/* Daily target */}
                <Card title="Objectif du jour">
                    <div className="flex items-end gap-2">
                        <span className="text-4xl font-black text-neutral-900">{daily.kcal.toLocaleString('fr-FR')}</span>
                        <span className="mb-1 text-sm font-semibold text-neutral-500">kcal / jour</span>
                    </div>
                    <div className="mt-3">
                        <MacroBar protein={daily.protein} fat={daily.fat} carbs={daily.carbs} />
                    </div>
                    <div className="mt-3">
                        <MacroChips protein={daily.protein} fat={daily.fat} carbs={daily.carbs} />
                    </div>
                    <p className="mt-3 text-xs text-neutral-500">
                        Protéines réparties ~50-55 g par repas (optimal pour la synthèse). Glucides plus élevés autour de l'entraînement.
                    </p>
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
                                    <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-500">{m.share} %</span>
                                </div>
                                <div className="text-right">
                                    <span className="text-lg font-black text-neutral-900">{m.kcal.toLocaleString('fr-FR')}</span>
                                    <span className="ml-1 text-xs font-semibold text-neutral-400">kcal</span>
                                </div>
                            </div>
                            <MacroBar protein={m.protein} fat={m.fat} carbs={m.carbs} />
                            <div className="mt-3">
                                <MacroChips protein={m.protein} fat={m.fat} carbs={m.carbs} />
                            </div>
                            <p className="mt-3 text-sm text-neutral-600">{m.note}</p>
                        </Card>
                    );
                })}

                <p className="px-1 text-center text-xs text-neutral-400">
                    Pas de plat imposé — juste la répartition. Pilote au poids (moyenne hebdo), tour de taille et charges, pas à la balance quotidienne.
                </p>
            </div>
        </AppLayout>
    );
}
