import { useState, type InputHTMLAttributes } from 'react';
import { Eye, EyeOff } from 'lucide-react';

/** Text input for passwords with a show/hide eye toggle. */
export function PasswordInput({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) {
    const [show, setShow] = useState(false);

    return (
        <div className="relative">
            <input {...props} type={show ? 'text' : 'password'} className={`${className} pr-10`} />
            <button
                type="button"
                tabIndex={-1}
                onClick={() => setShow((s) => !s)}
                aria-label={show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-neutral-400 transition-colors hover:text-neutral-700"
            >
                {show ? <EyeOff size={17} /> : <Eye size={17} />}
            </button>
        </div>
    );
}
