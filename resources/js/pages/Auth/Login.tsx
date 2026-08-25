import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LogIn } from 'lucide-react';
import { GuestLayout } from '@/layouts/GuestLayout';
import { PasswordInput } from '@/components/PasswordInput';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Connexion" />
            <div className="mb-5 text-center">
                <h1 className="text-lg font-extrabold text-neutral-900">Content de te revoir</h1>
                <p className="mt-1 text-sm text-neutral-500">Connecte-toi pour retrouver ton entraînement.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <label className="block">
                    <span className="mb-1 block text-xs font-semibold uppercase tracking-wide text-neutral-500">Email</span>
                    <input
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        autoComplete="email"
                        autoFocus
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
                        autoComplete="current-password"
                        required
                        className="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
                    />
                    {form.errors.password && <span className="mt-1 block text-xs font-medium text-red-600">{form.errors.password}</span>}
                </label>

                <label className="flex items-center gap-2 text-sm text-neutral-600">
                    <input
                        type="checkbox"
                        checked={form.data.remember}
                        onChange={(e) => form.setData('remember', e.target.checked)}
                        className="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-300"
                    />
                    Rester connecté
                </label>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-500/25 transition-transform hover:-translate-y-0.5 disabled:opacity-60"
                >
                    <LogIn size={17} /> Se connecter
                </button>
            </form>

            <p className="mt-5 text-center text-sm text-neutral-500">
                Pas encore de compte ?{' '}
                <Link href="/register" className="font-semibold text-brand-600 hover:text-brand-700">
                    Créer un compte
                </Link>
            </p>
        </>
    );
}

Login.layout = (page: ReactNode) => <GuestLayout>{page}</GuestLayout>;
