import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft, CheckCircle2, Plus, X } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface ObjectiveRow {
    type: string;
    label: string;
    distance_km: string;
    time: string;
    pace: string;
    count: string;
}

interface PlanPhase {
    name: string;
    focus: string;
    weeks: number;
}

interface Plan {
    key: string;
    name: string;
    summary: string;
    goal: string;
    targetRaceName: string;
    daysPerWeek: number;
    totalWeeks: number;
    phases: PlanPhase[];
}

const OBJECTIVE_TYPES: { value: string; label: string; hint: string }[] = [
    { value: 'RACE_TIME', label: 'Chrono course', hint: 'distance + temps' },
    { value: 'PACE_OVER_DISTANCE', label: 'Allure tenue', hint: 'distance + allure' },
    { value: 'LONGEST_RUN', label: 'Sortie longue', hint: 'distance' },
    { value: 'TOTAL_VOLUME', label: 'Volume total', hint: 'distance cumulée' },
    { value: 'SESSION_COUNT', label: 'Nb de séances', hint: 'nombre' },
];

function parseTime(v: string): number {
    return v.split(':').reduce((t, p) => t * 60 + (parseInt(p, 10) || 0), 0);
}

const inputClass =
    'w-full rounded-lg border border-neutral-800 bg-neutral-900 px-3 py-2 text-neutral-100 outline-none focus:border-lime-400/60';
const smallInput =
    'w-full rounded-md border border-neutral-800 bg-neutral-900 px-2 py-1.5 text-sm text-neutral-100 outline-none focus:border-lime-400/60';

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs uppercase tracking-wide text-neutral-500">{label}</span>
            {children}
        </label>
    );
}

export default function ProgramForm({ plans = [] }: { plans?: Plan[] }) {
    const form = useForm({
        plan_key: '',
        name: '',
        goal: '',
        target_race_name: '',
        target_race_date: '',
        start_date: '',
        end_date: '',
        priority: 'A',
        objectives: [] as ObjectiveRow[],
    });

    function choosePlan(plan: Plan) {
        form.setData((data) => ({
            ...data,
            plan_key: plan.key,
            name: data.name || `Prépa ${plan.targetRaceName}`,
            goal: plan.goal,
            target_race_name: plan.targetRaceName,
        }));
    }

    function setObjective(i: number, patch: Partial<ObjectiveRow>) {
        form.setData(
            'objectives',
            form.data.objectives.map((o, idx) => (idx === i ? { ...o, ...patch } : o)),
        );
    }
    function addObjective() {
        form.setData('objectives', [
            ...form.data.objectives,
            { type: 'RACE_TIME', label: '', distance_km: '', time: '', pace: '', count: '' },
        ]);
    }
    function removeObjective(i: number) {
        form.setData('objectives', form.data.objectives.filter((_, idx) => idx !== i));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            plan_key: d.plan_key || null,
            name: d.name,
            goal: d.goal,
            target_race_name: d.target_race_name,
            target_race_date: d.target_race_date ? new Date(d.target_race_date).toISOString() : null,
            start_date: d.start_date ? new Date(d.start_date).toISOString() : '',
            end_date: d.end_date ? new Date(d.end_date).toISOString() : null,
            priority: d.priority,
            objectives: d.objectives.map((o) => ({
                type: o.type,
                label: o.label,
                target_distance_meters: o.distance_km ? Math.round(parseFloat(o.distance_km) * 1000) : null,
                target_seconds: o.time ? parseTime(o.time) : null,
                target_pace_seconds_per_km: o.pace ? parseTime(o.pace) : null,
                target_count: o.count ? parseInt(o.count, 10) : null,
            })),
        }));
        form.post('/programme');
    }

    const errors = Object.values(form.errors);

    return (
        <>
            <Head title="Nouveau programme" />
            <Link
                href="/programme"
                className="mb-4 inline-flex items-center gap-1 text-sm text-neutral-400 transition-colors hover:text-neutral-100"
            >
                <ArrowLeft size={16} /> Programmes
            </Link>
            <h1 className="mb-6 text-xl font-bold tracking-tight">Nouveau programme</h1>

            {errors.length > 0 && (
                <ul className="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    {errors.map((m) => (
                        <li key={m}>{m}</li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="space-y-6">
                {plans.length > 0 && (
                    <Card title="Choisis un plan tout fait">
                        <p className="-mt-1 mb-4 text-sm text-neutral-400">
                            Le premier cycle arrive déjà détaillé (séances, allures, km). Tu débloqueras les cycles suivants au fur et
                            à mesure.
                        </p>
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            {plans.map((plan) => {
                                const selected = form.data.plan_key === plan.key;
                                return (
                                    <button
                                        type="button"
                                        key={plan.key}
                                        onClick={() => choosePlan(plan)}
                                        className={`cursor-pointer rounded-xl border p-4 text-left transition-colors ${
                                            selected
                                                ? 'border-lime-400/70 bg-lime-400/10'
                                                : 'border-neutral-800 bg-neutral-900/60 hover:border-neutral-700'
                                        }`}
                                    >
                                        <div className="mb-1 flex items-center justify-between">
                                            <span className="font-semibold text-neutral-100">{plan.name}</span>
                                            {selected && <CheckCircle2 size={16} className="text-lime-400" />}
                                        </div>
                                        <p className="mb-3 text-xs leading-relaxed text-neutral-400">{plan.summary}</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            <span className="rounded bg-neutral-800 px-1.5 py-0.5 text-[11px] text-neutral-300">
                                                {plan.totalWeeks} sem.
                                            </span>
                                            <span className="rounded bg-neutral-800 px-1.5 py-0.5 text-[11px] text-neutral-300">
                                                {plan.daysPerWeek} j/sem
                                            </span>
                                            <span className="rounded bg-neutral-800 px-1.5 py-0.5 text-[11px] text-neutral-300">
                                                {plan.phases.length} cycles
                                            </span>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                        {form.data.plan_key && (
                            <button
                                type="button"
                                onClick={() => form.setData('plan_key', '')}
                                className="mt-3 text-xs text-neutral-500 hover:text-neutral-300"
                            >
                                Repartir d'un programme vide
                            </button>
                        )}
                    </Card>
                )}

                <Card title="Le programme">
                    <div className="space-y-4">
                        <Field label="Nom">
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Prépa Odysséa 10K"
                                className={inputClass}
                                required
                            />
                        </Field>
                        <Field label="Objectif (texte libre)">
                            <input
                                type="text"
                                value={form.data.goal}
                                onChange={(e) => form.setData('goal', e.target.value)}
                                placeholder="Passer sous 40 min au 10 km"
                                className={inputClass}
                            />
                        </Field>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Course cible">
                                <input
                                    type="text"
                                    value={form.data.target_race_name}
                                    onChange={(e) => form.setData('target_race_name', e.target.value)}
                                    placeholder="Odysséa Paris 10 km"
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Date de la course">
                                <input
                                    type="date"
                                    value={form.data.target_race_date}
                                    onChange={(e) => form.setData('target_race_date', e.target.value)}
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <Field label="Début">
                                <input
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(e) => form.setData('start_date', e.target.value)}
                                    className={inputClass}
                                    required
                                />
                            </Field>
                            <Field label="Fin">
                                <input
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={(e) => form.setData('end_date', e.target.value)}
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Priorité">
                                <select
                                    value={form.data.priority}
                                    onChange={(e) => form.setData('priority', e.target.value)}
                                    className={inputClass}
                                >
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                </select>
                            </Field>
                        </div>
                    </div>
                </Card>

                <Card title="Objectifs">
                    <div className="space-y-3">
                        {form.data.objectives.map((o, i) => (
                            <div key={i} className="rounded-lg border border-neutral-800 p-3">
                                <div className="mb-2 flex items-center gap-2">
                                    <select
                                        value={o.type}
                                        onChange={(e) => setObjective(i, { type: e.target.value })}
                                        className={smallInput}
                                    >
                                        {OBJECTIVE_TYPES.map((t) => (
                                            <option key={t.value} value={t.value}>
                                                {t.label} ({t.hint})
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={() => removeObjective(i)}
                                        className="text-neutral-500 hover:text-red-400"
                                        aria-label="Supprimer l'objectif"
                                    >
                                        <X size={16} />
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    value={o.label}
                                    onChange={(e) => setObjective(i, { label: e.target.value })}
                                    placeholder="Libellé (ex. 10 km sous 40:00)"
                                    className={`${smallInput} mb-2`}
                                />
                                <div className="grid grid-cols-4 gap-2">
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={o.distance_km}
                                        onChange={(e) => setObjective(i, { distance_km: e.target.value })}
                                        placeholder="dist. km"
                                        className={smallInput}
                                    />
                                    <input
                                        type="text"
                                        value={o.time}
                                        onChange={(e) => setObjective(i, { time: e.target.value })}
                                        placeholder="temps mm:ss"
                                        className={smallInput}
                                    />
                                    <input
                                        type="text"
                                        value={o.pace}
                                        onChange={(e) => setObjective(i, { pace: e.target.value })}
                                        placeholder="allure mm:ss"
                                        className={smallInput}
                                    />
                                    <input
                                        type="number"
                                        value={o.count}
                                        onChange={(e) => setObjective(i, { count: e.target.value })}
                                        placeholder="nombre"
                                        className={smallInput}
                                    />
                                </div>
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={addObjective}
                            className="inline-flex items-center gap-1 text-sm text-lime-300 hover:text-lime-200"
                        >
                            <Plus size={15} /> Ajouter un objectif
                        </button>
                    </div>
                </Card>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full rounded-lg bg-lime-400 px-4 py-2.5 font-medium text-neutral-950 transition-colors hover:bg-lime-300 disabled:opacity-50"
                >
                    Créer le programme
                </button>
            </form>
        </>
    );
}

ProgramForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
