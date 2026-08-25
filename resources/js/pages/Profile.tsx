import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Calendar, CheckCircle2, Gauge, Heart, HeartPulse, Lock, Target, User } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card } from '@/components/Card';
import { HelpTip } from '@/components/HelpTip';
import { PasswordInput } from '@/components/PasswordInput';

interface HrZone {
    key: string;
    label: string;
    minBpm: number;
    maxBpm: number;
}

interface Goal {
    label: string;
    distanceMeters: number;
    targetSeconds: number;
    paceSeconds: number;
    raceName: string | null;
    raceDate: string | null;
}

interface ProfileValues {
    displayName: string;
    birthDate: string | null;
    heightCm: number | null;
    weightKg: number | null;
    restingHr: number | null;
    maxHr: number | null;
    sessionsPerWeek: number | null;
    weeklyVolumeKm: number | null;
    preferredDays: number[];
    raceName: string | null;
    raceDate: string | null;
    goalDistanceKm: number | null;
    goalTime: string | null;
    longTermGoal: string | null;
    units: string;
    weekStart: string;
    theme: string;
    sessionReminders: boolean;
}

interface Props {
    profile: ProfileValues;
    derived: {
        age: number | null;
        vdot: number | null;
        basisLabel: string | null;
        hrZones: HrZone[] | null;
        goal: Goal | null;
        hasProfile: boolean;
    };
}

const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

const inputClass = 'w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-neutral-900 outline-none focus:border-brand-500/60';

function Field({ label, help, children }: { label: string; help?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 flex items-center gap-1 text-xs uppercase tracking-wide text-neutral-500">
                {label}
                {help && <HelpTip label={label} text={help} size={13} />}
            </span>
            {children}
        </label>
    );
}

export default function Profile({ profile, derived }: Props) {
    const form = useForm({
        display_name: profile.displayName ?? '',
        birth_date: profile.birthDate ?? '',
        height_cm: profile.heightCm ?? '',
        weight_kg: profile.weightKg ?? '',
        resting_hr: profile.restingHr ?? '',
        max_hr: profile.maxHr ?? '',
        sessions_per_week: profile.sessionsPerWeek ?? '',
        weekly_volume_km: profile.weeklyVolumeKm ?? '',
        preferred_days: profile.preferredDays ?? [],
        race_name: profile.raceName ?? '',
        race_date: profile.raceDate ?? '',
        goal_distance_km: profile.goalDistanceKm ?? '',
        goal_time: profile.goalTime ?? '',
        long_term_goal: profile.longTermGoal ?? '',
        units: profile.units,
        week_start: profile.weekStart,
        theme: profile.theme,
        session_reminders: profile.sessionReminders,
    });

    function set<K extends keyof typeof form.data>(key: K, value: (typeof form.data)[K]) {
        form.setData(key, value);
    }

    function toggleDay(day: number) {
        const days = form.data.preferred_days.includes(day)
            ? form.data.preferred_days.filter((d) => d !== day)
            : [...form.data.preferred_days, day].sort((a, b) => a - b);
        set('preferred_days', days);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/profil', { preserveScroll: true });
    }

    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submitPassword(e: FormEvent) {
        e.preventDefault();
        passwordForm.post('/profil/mot-de-passe', {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    }

    const displayName = form.data.display_name.trim() || 'Ton profil';
    const errors = Object.values(form.errors);

    return (
        <>
            <Head title="Profil" />
            <h1 className="mb-6 text-2xl font-bold tracking-tight text-neutral-900">Profil</h1>

            {/* Athlete card */}
            <div className="animate-fade-up mb-5 overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-brand-50/50 p-5 shadow-sm shadow-neutral-200/60">
                <div className="flex flex-wrap items-center gap-x-8 gap-y-4">
                    <div className="flex items-center gap-4">
                        <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 text-2xl font-black text-white shadow-lg shadow-brand-500/30">
                            {displayName.charAt(0).toUpperCase()}
                        </span>
                        <div>
                            <p className="text-xl font-bold leading-tight text-neutral-900">{displayName}</p>
                            <p className="text-sm text-neutral-500">
                                {derived.age !== null ? `${derived.age} ans` : 'Coureur'}
                                {derived.goal ? ` · objectif ${derived.goal.label}` : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        <div>
                            <p className="flex items-center gap-1 text-[11px] uppercase tracking-wide text-neutral-400">
                                VDOT
                                <HelpTip label="VDOT" text="Score de forme calculé depuis ton meilleur effort. Sert de base à tes allures." size={12} />
                            </p>
                            <p className="text-lg font-bold tabular-nums text-brand-500">{derived.vdot ?? '—'}</p>
                        </div>
                        {derived.basisLabel && (
                            <div>
                                <p className="text-[11px] uppercase tracking-wide text-neutral-400">Basé sur</p>
                                <p className="text-lg font-bold text-neutral-900">{derived.basisLabel}</p>
                            </div>
                        )}
                        {derived.goal && (
                            <div>
                                <p className="text-[11px] uppercase tracking-wide text-neutral-400">Allure cible</p>
                                <p className="text-lg font-bold tabular-nums text-neutral-900">
                                    {Math.floor(derived.goal.paceSeconds / 60)}:{(derived.goal.paceSeconds % 60).toString().padStart(2, '0')}/km
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {errors.length > 0 && (
                <ul className="animate-fade-up mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}

            <form onSubmit={submit} className="space-y-5">
                {/* Identity & physiology */}
                <div className="animate-fade-up" style={{ animationDelay: '40ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                <User size={15} className="text-neutral-400" /> Identité &amp; physiologie
                            </span>
                        }
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Nom affiché">
                                <input type="text" value={form.data.display_name} onChange={(e) => set('display_name', e.target.value)} className={inputClass} placeholder="Thomas" />
                            </Field>
                            <Field label="Date de naissance">
                                <input type="date" value={form.data.birth_date} onChange={(e) => set('birth_date', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Taille (cm)">
                                <input type="number" value={form.data.height_cm} onChange={(e) => set('height_cm', e.target.value)} className={inputClass} placeholder="180" />
                            </Field>
                            <Field label="Poids (kg)">
                                <input type="number" step="0.1" value={form.data.weight_kg} onChange={(e) => set('weight_kg', e.target.value)} className={inputClass} placeholder="72" />
                            </Field>
                            <Field label="FC repos (bpm)" help="Ta fréquence cardiaque au repos, mesurée au réveil. Affine tes zones cardio (méthode Karvonen).">
                                <input type="number" value={form.data.resting_hr} onChange={(e) => set('resting_hr', e.target.value)} className={inputClass} placeholder="48" />
                            </Field>
                            <Field label="FC max (bpm)" help="Ta fréquence cardiaque maximale. Débloque tes 5 zones d'entraînement cardio ci-dessous.">
                                <input type="number" value={form.data.max_hr} onChange={(e) => set('max_hr', e.target.value)} className={inputClass} placeholder="190" />
                            </Field>
                        </div>

                        {/* HR zones */}
                        <div className="mt-4 rounded-xl border border-neutral-100 bg-neutral-50/60 p-3">
                            <p className="mb-2 flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <HeartPulse size={14} className="text-rose-500" /> Zones cardio
                                <HelpTip label="Zones cardio" text="Cinq intensités calculées depuis ta FC max (et ta FC repos si fournie). De Z1 (facile) à Z5 (maximal)." />
                            </p>
                            {derived.hrZones ? (
                                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-5">
                                    {derived.hrZones.map((z) => (
                                        <div key={z.key} className="rounded-lg bg-white p-2 text-center shadow-sm shadow-neutral-200/50">
                                            <p className="text-[10px] font-semibold uppercase tracking-wide text-neutral-400">{z.label.split(' · ')[0]}</p>
                                            <p className="text-sm font-bold tabular-nums text-neutral-900">
                                                {z.minBpm}–{z.maxBpm}
                                            </p>
                                            <p className="text-[10px] text-neutral-400">{z.label.split(' · ')[1]}</p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="flex items-center gap-1.5 text-xs text-neutral-500">
                                    <Heart size={13} className="text-rose-400" /> Renseigne ta FC max pour débloquer tes zones cardio.
                                </p>
                            )}
                        </div>
                    </Card>
                </div>

                {/* Goal */}
                <div className="animate-fade-up" style={{ animationDelay: '80ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                <Target size={15} className="text-neutral-400" /> Objectifs
                                <HelpTip label="Objectifs" text="Ta course cible et son temps visé. Ce que tu saisis ici alimente le suivi d'objectif sur la page Progression." />
                            </span>
                        }
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Course cible">
                                <input type="text" value={form.data.race_name} onChange={(e) => set('race_name', e.target.value)} className={inputClass} placeholder="Odysséa Paris 10 km" />
                            </Field>
                            <Field label="Date de la course">
                                <input type="date" value={form.data.race_date} onChange={(e) => set('race_date', e.target.value)} className={inputClass} />
                            </Field>
                            <Field label="Distance (km)">
                                <input type="number" step="0.1" value={form.data.goal_distance_km} onChange={(e) => set('goal_distance_km', e.target.value)} className={inputClass} placeholder="10" />
                            </Field>
                            <Field label="Temps visé (mm:ss)" help="Le chrono que tu vises sur cette distance. Ex : 40:00 pour un 10 km sous 40 minutes.">
                                <input type="text" inputMode="numeric" value={form.data.goal_time} onChange={(e) => set('goal_time', e.target.value)} className={inputClass} placeholder="40:00" />
                            </Field>
                        </div>
                        <div className="mt-4">
                            <Field label="Objectif long terme" help="Ton cap à ~1 an (ex : un trail longue distance). Texte libre, pour garder le nord.">
                                <textarea value={form.data.long_term_goal} onChange={(e) => set('long_term_goal', e.target.value)} rows={2} className={inputClass} placeholder="Un trail longue distance dans un an…" />
                            </Field>
                        </div>
                    </Card>
                </div>

                {/* Availability */}
                <div className="animate-fade-up" style={{ animationDelay: '120ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                <Calendar size={15} className="text-neutral-400" /> Disponibilité
                                <HelpTip label="Disponibilité" text="Combien tu peux t'entraîner. Ces repères nourrissent le coach IA et la génération de plan." />
                            </span>
                        }
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Séances / semaine">
                                <input type="number" value={form.data.sessions_per_week} onChange={(e) => set('sessions_per_week', e.target.value)} className={inputClass} placeholder="4" />
                            </Field>
                            <Field label="Volume hebdo visé (km)">
                                <input type="number" value={form.data.weekly_volume_km} onChange={(e) => set('weekly_volume_km', e.target.value)} className={inputClass} placeholder="40" />
                            </Field>
                        </div>
                        <div className="mt-4">
                            <span className="mb-2 block text-xs uppercase tracking-wide text-neutral-500">Jours préférés</span>
                            <div className="flex flex-wrap gap-2">
                                {WEEKDAYS.map((label, i) => {
                                    const day = i + 1;
                                    const active = form.data.preferred_days.includes(day);
                                    return (
                                        <button
                                            key={day}
                                            type="button"
                                            onClick={() => toggleDay(day)}
                                            className={`h-10 w-12 rounded-lg text-sm font-semibold transition-colors ${
                                                active ? 'bg-brand-500 text-white' : 'bg-neutral-100 text-neutral-500 hover:bg-neutral-200'
                                            }`}
                                        >
                                            {label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Settings */}
                <div className="animate-fade-up" style={{ animationDelay: '160ms' }}>
                    <Card
                        title={
                            <span className="inline-flex items-center gap-1.5">
                                <Gauge size={15} className="text-neutral-400" /> Réglages
                            </span>
                        }
                    >
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Unités">
                                <select value={form.data.units} onChange={(e) => set('units', e.target.value)} className={inputClass}>
                                    <option value="metric">Métrique (km)</option>
                                </select>
                            </Field>
                            <Field label="Début de semaine">
                                <select value={form.data.week_start} onChange={(e) => set('week_start', e.target.value)} className={inputClass}>
                                    <option value="monday">Lundi</option>
                                    <option value="sunday">Dimanche</option>
                                </select>
                            </Field>
                        </div>
                        <label className="mt-4 flex items-center gap-3">
                            <input
                                type="checkbox"
                                checked={form.data.session_reminders}
                                onChange={(e) => set('session_reminders', e.target.checked)}
                                className="h-5 w-5 rounded border-neutral-300 text-brand-500 focus:ring-brand-500"
                            />
                            <span className="text-sm text-neutral-700">Rappels de séance</span>
                        </label>
                    </Card>
                </div>

                {/* Save */}
                <div className="flex items-center justify-end gap-3 pt-1">
                    {form.recentlySuccessful && (
                        <span className="flex items-center gap-1.5 text-sm font-medium text-emerald-600">
                            <CheckCircle2 size={16} /> Enregistré
                        </span>
                    )}
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-lg bg-brand-500 px-6 py-2.5 font-medium text-white transition-colors hover:bg-brand-600 disabled:opacity-50"
                    >
                        {form.processing ? 'Enregistrement…' : 'Enregistrer le profil'}
                    </button>
                </div>
            </form>

            {/* Security — change password */}
            <form onSubmit={submitPassword} className="animate-fade-up mt-5" style={{ animationDelay: '200ms' }}>
                <Card
                    title={
                        <span className="inline-flex items-center gap-1.5">
                            <Lock size={15} className="text-neutral-400" /> Sécurité
                        </span>
                    }
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Mot de passe actuel">
                            <PasswordInput
                                autoComplete="current-password"
                                value={passwordForm.data.current_password}
                                onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                className={inputClass}
                            />
                            {passwordForm.errors.current_password && (
                                <span className="mt-1 block text-xs font-medium text-red-600">{passwordForm.errors.current_password}</span>
                            )}
                        </Field>
                        <Field label="Nouveau mot de passe">
                            <PasswordInput
                                autoComplete="new-password"
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                className={inputClass}
                            />
                            {passwordForm.errors.password && (
                                <span className="mt-1 block text-xs font-medium text-red-600">{passwordForm.errors.password}</span>
                            )}
                        </Field>
                        <Field label="Confirme le nouveau">
                            <PasswordInput
                                autoComplete="new-password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                className={inputClass}
                            />
                        </Field>
                    </div>
                    <div className="mt-4 flex items-center justify-end gap-3">
                        {passwordForm.recentlySuccessful && (
                            <span className="flex items-center gap-1.5 text-sm font-medium text-emerald-600">
                                <CheckCircle2 size={16} /> Modifié
                            </span>
                        )}
                        <button
                            type="submit"
                            disabled={passwordForm.processing}
                            className="rounded-lg border border-neutral-300 px-5 py-2.5 font-medium text-neutral-700 transition-colors hover:bg-neutral-50 disabled:opacity-50"
                        >
                            {passwordForm.processing ? 'Modification…' : 'Changer le mot de passe'}
                        </button>
                    </div>
                </Card>
            </form>
        </>
    );
}

Profile.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
