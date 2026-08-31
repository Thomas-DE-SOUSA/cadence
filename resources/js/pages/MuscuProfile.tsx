import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Check } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';

interface Option {
    value: string;
    label: string;
}
interface ProfileData {
    exists: boolean;
    goal: string;
    level: string;
    bodyweightKg: number | null;
    weeklyFrequency: number;
    split: string;
    equipment: string;
    priorities: string[];
    limitations: string[];
    note: string;
}
interface Props {
    profile: ProfileData;
    options: {
        goals: Option[];
        levels: Option[];
        splits: Option[];
        equipments: Option[];
        muscles: Option[];
    };
}

function Pills({ options, value, onChange }: { options: Option[]; value: string; onChange: (v: string) => void }) {
    return (
        <div className="flex flex-wrap gap-2">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    onClick={() => onChange(o.value)}
                    className={`rounded-xl px-3 py-2 text-sm font-semibold transition ${
                        value === o.value ? 'bg-neutral-900 text-white shadow-sm' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                    }`}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

function MuscleChips({ options, selected, onToggle }: { options: Option[]; selected: string[]; onToggle: (v: string) => void }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((o) => {
                const on = selected.includes(o.value);
                return (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => onToggle(o.value)}
                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                            on ? 'bg-brand-500 text-white' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                        }`}
                    >
                        {o.label}
                    </button>
                );
            })}
        </div>
    );
}

function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
    return (
        <div className="mb-5">
            <p className="mb-2 text-sm font-semibold text-neutral-700">{label}</p>
            {hint && <p className="mb-2 -mt-1 text-xs text-neutral-400">{hint}</p>}
            {children}
        </div>
    );
}

export default function MuscuProfile({ profile, options }: Props) {
    const [goal, setGoal] = useState(profile.goal);
    const [level, setLevel] = useState(profile.level);
    const [bodyweight, setBodyweight] = useState(profile.bodyweightKg?.toString() ?? '');
    const [frequency, setFrequency] = useState(profile.weeklyFrequency);
    const [split, setSplit] = useState(profile.split);
    const [equipment, setEquipment] = useState(profile.equipment);
    const [priorities, setPriorities] = useState<string[]>(profile.priorities);
    const [limitations, setLimitations] = useState<string[]>(profile.limitations);
    const [note, setNote] = useState(profile.note);
    const [saving, setSaving] = useState(false);

    const toggle = (list: string[], set: (v: string[]) => void, v: string) => set(list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);

    const save = () => {
        setSaving(true);
        router.post(
            '/muscu/profil',
            {
                goal,
                level,
                bodyweightKg: bodyweight.trim() === '' ? null : Number(bodyweight.replace(',', '.')),
                weeklyFrequency: frequency,
                split,
                equipment,
                priorities,
                limitations,
                note,
            },
            { onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Profil muscu" />
            <div className="mb-4 flex items-center gap-2">
                <Link href="/muscu/progression" className="rounded-lg p-2 text-neutral-500 hover:bg-neutral-100">
                    <ArrowLeft size={18} />
                </Link>
                <h1 className="text-2xl font-bold tracking-tight text-neutral-900">Profil muscu</h1>
            </div>
            <p className="mb-4 text-sm text-neutral-500">Ton profil personnalise tes graphes de progression, tes cycles et les conseils du coach.</p>

            <Card className="pb-2">
                <Field label="Objectif" hint="Le levier principal : il oriente ce qu'on met en avant partout.">
                    <Pills options={options.goals} value={goal} onChange={setGoal} />
                </Field>
                <Field label="Niveau">
                    <Pills options={options.levels} value={level} onChange={setLevel} />
                </Field>
                <Field label="Poids de corps (kg)" hint="Optionnel — débloque la force relative et la charge des exos au poids du corps.">
                    <input
                        inputMode="decimal"
                        value={bodyweight}
                        onChange={(e) => setBodyweight(e.target.value)}
                        placeholder="ex. 72"
                        className="w-32 rounded-lg border border-neutral-200 px-3 py-2 text-sm tabular-nums focus:border-neutral-400 focus:outline-none"
                    />
                </Field>
                <Field label="Fréquence cible (séances / semaine)">
                    <div className="flex gap-1.5">
                        {[2, 3, 4, 5, 6].map((n) => (
                            <button
                                key={n}
                                type="button"
                                onClick={() => setFrequency(n)}
                                className={`h-10 w-10 rounded-lg text-sm font-bold tabular-nums transition ${
                                    frequency === n ? 'bg-neutral-900 text-white' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                                }`}
                            >
                                {n}
                            </button>
                        ))}
                    </div>
                </Field>
                <Field label="Split préféré">
                    <Pills options={options.splits} value={split} onChange={setSplit} />
                </Field>
                <Field label="Matériel" hint="Filtre les exercices que le coach te conseillera.">
                    <Pills options={options.equipments} value={equipment} onChange={setEquipment} />
                </Field>
                <Field label="Priorités musculaires" hint="Groupes à pousser en priorité (cibles de volume + reco coach).">
                    <MuscleChips options={options.muscles} selected={priorities} onToggle={(v) => toggle(priorities, setPriorities, v)} />
                </Field>
                <Field label="Zones à ménager" hint="Douleurs / limitations — le coach évitera les exos à risque.">
                    <MuscleChips options={options.muscles} selected={limitations} onToggle={(v) => toggle(limitations, setLimitations, v)} />
                </Field>
                <Field label="Note (optionnel)">
                    <textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        rows={2}
                        maxLength={500}
                        placeholder="Contexte, contraintes, objectif esthétique…"
                        className="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-700 placeholder:text-neutral-400 focus:border-neutral-400 focus:outline-none"
                    />
                </Field>
            </Card>

            <div className="mt-4 flex justify-end">
                <button
                    onClick={save}
                    disabled={saving}
                    className="inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-6 py-2.5 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 disabled:opacity-50"
                >
                    <Check size={16} /> {saving ? 'Enregistrement…' : 'Enregistrer mon profil'}
                </button>
            </div>
        </>
    );
}

MuscuProfile.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
