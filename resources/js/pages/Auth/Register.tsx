import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { UserPlus } from 'lucide-react';
import { GuestLayout } from '@/layouts/GuestLayout';
import { PasswordInput } from '@/components/PasswordInput';

export default function Register() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Créer un compte" />
            <div className="mb-5 text-center">
                <h1 className="text-lg font-extrabold text-neutral-900">Crée ton espace</h1>
                <p className="mt-1 text-sm text-neutral-500">Un compte privé, tes données rien qu'à toi.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Prénom / nom</span>
                    <input
                        type="text"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        autoComplete="name"
                        autoFocus
                        required
                        className="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                    />
                    {form.errors.name && <span className="mt-1 block text-xs font-medium text-red-600">{form.errors.name}</span>}
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Email</span>
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        autoComplete="email"
                        required
                        className="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                    />
                    {form.errors.email && <span className="mt-1 block text-xs font-medium text-red-600">{form.errors.email}</span>}
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Mot de passe</span>
                    <PasswordInput
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        autoComplete="new-password"
                        required
                        className="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                    />
                    {form.errors.password && <span className="mt-1 block text-xs font-medium text-red-600">{form.errors.password}</span>}
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Confirme le mot de passe</span>
                    <PasswordInput
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                        className="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                    />
                </label>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-500/25 transition-transform hover:-translate-y-0.5 disabled:opacity-60"
                >
                    <UserPlus size={17} /> Créer mon compte
                </button>
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500">
                Déjà un compte ?{' '}
                <Link href="/login" className="font-semibold text-brand-600 hover:text-brand-700">
                    Se connecter
                </Link>
            </p>
        </>
    );
}

Register.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
